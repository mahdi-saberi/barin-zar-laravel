<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageStudio
{
    public const IMAGE_RULE = 'required|image|mimes:png,jpg,jpeg,webp|max:8192';

    public function __construct(private readonly Integrations $integrations) {}

    public function presets(): array
    {
        return json_decode(file_get_contents(public_path('assets/presets.json')), true);
    }

    public function settings(): array
    {
        $settings = Entry::getData('settings', 'store');
        $host = strtolower((string) parse_url($settings['ai_url'] ?? '', PHP_URL_HOST));
        $model = trim($settings['ai_model'] ?? '');
        if ($host === 'api.openai.com' && $model === 'gpt-image-1') {
            $model = '';
        }
        $default = $host === '' || $host === 'api.openai.com' ? 'gpt-image-2.5-sunburst' : 'gpt-image-1';

        return ['ai_model' => $model ?: $default] + $settings;
    }

    public function generate(UploadedFile $image, int $presetId, bool $forCatalog = false): string
    {
        $preset = collect($this->presets())->firstWhere('id', $presetId);
        if (!$preset) {
            throw ValidationException::withMessages(['preset' => 'یک طرح عکاسی از فهرست استودیو انتخاب کنید.']);
        }

        $settings = $this->settings();
        if (empty($settings['ai_url'])) {
            throw ValidationException::withMessages(['image' => 'نشانی سرویس استودیو را در تنظیمات فروشگاه وارد کنید.']);
        }

        $stream = fopen($image->path(), 'rb');
        abort_unless(is_resource($stream), 500);
        try {
            $response = $this->integrations->endpoint($settings, 'ai')
                ->attach('image', $stream, 'product.'.$image->extension())
                ->post($settings['ai_url'], [
                    'model' => $settings['ai_model'],
                    'prompt' => $preset['prompt'],
                    'size' => '1024x1024',
                ])->throw()->json('data.0.b64_json');
        } catch (HttpClientException|DecryptException) {
            throw ValidationException::withMessages(['image' => 'تصویرسازی ناموفق بود. اتصال و اعتبار سرویس استودیو را بررسی کنید.']);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $bytes = is_string($response) && strlen($response) < 24000000 ? base64_decode($response, true) : false;
        $mime = $bytes ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : '';
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            throw ValidationException::withMessages(['image' => 'خروجی تصویری قابل ذخیره دریافت نشد.']);
        }

        $directory = $forCatalog ? 'catalog' : 'studio';
        $disk = $forCatalog ? 'public' : 'local';
        $path = $directory.'/'.Str::uuid().'.'.$extensions[$mime];
        abort_unless(Storage::disk($disk)->put($path, $bytes), 500);

        return $path;
    }
}
