<?php

namespace App\Services;

use App\Models\Entry;
use App\Support\PersianFormat;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageStudio
{
    public const IMAGE_RULE = 'required|image|mimes:png,jpg,jpeg,webp|max:8192';

    public const IMAGE_MESSAGES = [
        'image.uploaded' => 'بارگذاری فایل مرجع به سرور ناموفق بود (احتمالاً محدودیت upload_max_filesize یا post_max_size در PHP).',
        'image.image' => 'فایل ارسالی یک تصویر معتبر نیست.',
        'image.mimes' => 'عکس مرجع باید PNG، JPG یا WebP باشد.',
        'image.max' => 'حجم عکس باید حداکثر ۸ مگابایت باشد.',
    ];

    public const STAGES = [
        'preset' => 'انتخاب طرح',
        'config' => 'تنظیمات سرویس',
        'decrypt' => 'رمزگشایی توکن',
        'connection' => 'اتصال شبکه',
        'http_error' => 'پاسخ خطای سرویس',
        'response_format' => 'قالب پاسخ',
        'download' => 'دریافت تصویر از نشانی',
        'storage' => 'ذخیره‌سازی فایل',
        'unexpected' => 'خطای غیرمنتظره',
        'success' => 'موفق',
    ];

    private const LOG_LIMIT = 15;

    private const MAX_DOWNLOAD_BYTES = 18000000;

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

    public function logs(): array
    {
        $attempts = Entry::getData('studio', 'logs')['attempts'] ?? [];
        if (!is_array($attempts)) {
            return [];
        }

        return array_values(array_map(function (array $item) {
            $stage = $item['stage'] ?? 'unexpected';

            return $item + [
                'stage_label' => self::STAGES[$stage] ?? $stage,
                'attempted_at_label' => PersianFormat::dateTime($item['attempted_at'] ?? null, 'Y/m/d H:i:s'),
            ];
        }, array_filter($attempts, 'is_array')));
    }

    public function clearLogs(): void
    {
        Entry::where(['kind' => 'studio', 'slug' => 'logs'])->delete();
    }

    public function readiness(): array
    {
        $settings = $this->settings();
        $url = trim((string) ($settings['ai_url'] ?? ''));
        $parts = $url !== '' ? parse_url($url) : null;
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $allowedHosts = config('barin.integrations');
        $hasValidSyntax = is_array($parts) && $scheme === 'https' && $host !== ''
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port']);
        $hostAllowed = $hasValidSyntax && in_array($host, $allowedHosts, true);
        $logs = $this->logs();

        return [
            'configured' => $url !== '',
            'url' => $url !== '' ? $this->safeUrl($url) : null,
            'scheme' => $scheme ?: null,
            'host' => $host ?: null,
            'host_allowed' => $hostAllowed,
            'allowed_hosts' => $allowedHosts,
            'model' => $settings['ai_model'],
            'has_token' => !empty($settings['ai_token']),
            'mode' => $this->isGenerationsUrl($url) ? 'json-generations' : 'multipart-edits',
            'last_attempt' => $logs[0] ?? null,
        ];
    }

    public function generate(UploadedFile $image, int $presetId, bool $forCatalog = false): string
    {
        $startedAt = hrtime(true);
        $settings = $this->settings();
        $preset = collect($this->presets())->firstWhere('id', $presetId);
        $requestMode = $this->isGenerationsUrl($settings['ai_url'] ?? '') ? 'json-generations' : 'multipart-edits';
        $context = $this->baseContext($image, $presetId, $preset, $settings, $requestMode);

        if (!$preset) {
            return $this->fail(
                $context,
                $startedAt,
                'preset',
                'preset',
                'یک طرح عکاسی از فهرست استودیو انتخاب کنید.',
                "preset_id={$presetId} در presets.json پیدا نشد."
            );
        }

        if (empty($settings['ai_url'])) {
            return $this->fail(
                $context,
                $startedAt,
                'config',
                'image',
                'نشانی سرویس استودیو را در تنظیمات فروشگاه وارد کنید.',
                'فیلد ai_url در تنظیمات فروشگاه خالی است.'
            );
        }

        try {
            $endpoint = $this->integrations->endpoint($settings, 'ai');
        } catch (ValidationException $error) {
            $message = (string) (collect($error->errors())->flatten()->first() ?: 'تنظیمات نشانی سرویس استودیو معتبر نیست.');
            $this->recordLog($context + [
                'state' => 'error',
                'stage' => 'config',
                'http_status' => null,
                'duration_ms' => $this->elapsedMs($startedAt),
                'message' => $message,
                'detail' => 'url='.$context['url'].' | allowed_hosts='.(implode(', ', config('barin.integrations')) ?: '(empty)'),
            ]);
            throw $error;
        } catch (DecryptException $error) {
            return $this->fail(
                $context,
                $startedAt,
                'decrypt',
                'image',
                'توکن استودیو با کلید فعلی برنامه باز نمی‌شود. توکن را دوباره در تنظیمات ذخیره کنید.',
                'DecryptException: '.$this->sanitizeText($error->getMessage(), 250)
            );
        }

        $stream = null;
        if ($requestMode === 'multipart-edits') {
            $stream = fopen($image->path(), 'rb');
            if (!is_resource($stream)) {
                $this->recordLog($context + [
                    'state' => 'error',
                    'stage' => 'storage',
                    'http_status' => null,
                    'duration_ms' => $this->elapsedMs($startedAt),
                    'message' => 'خواندن فایل تصویر مرجع از حافظه موقت سرور ناموفق بود.',
                    'detail' => 'fopen failed for uploaded temp path.',
                ]);
                abort(500);
            }
        }

        try {
            $payloadData = [
                'model' => $settings['ai_model'],
                'prompt' => $preset['prompt'],
                'size' => '1024x1024',
            ];
            $httpResponse = ($requestMode === 'json-generations'
                ? $endpoint->asJson()->post($settings['ai_url'], $payloadData)
                : $endpoint->attach('image', $stream, 'product.'.$image->extension())->post($settings['ai_url'], $payloadData)
            )->throw();
        } catch (ConnectionException $error) {
            $reason = $this->sanitizeText($error->getMessage(), 350);

            return $this->fail(
                $context,
                $startedAt,
                'connection',
                'image',
                'ارتباط با سرویس استودیو برقرار نشد: '.$reason,
                "ConnectionException ({$this->elapsedMs($startedAt)}ms) | mode={$requestMode} | url={$context['url']} | model={$context['model']} | {$reason}"
            );
        } catch (RequestException $error) {
            $response = $error->response;
            $status = $response->status();
            $contentType = (string) ($response->header('Content-Type') ?? '');
            $json = $response->json();
            $providerCode = is_array($json) ? ($json['error']['code'] ?? $json['error']['type'] ?? $json['code'] ?? null) : null;
            $providerMessage = $this->extractProviderError($json);
            $excerpt = $this->sanitizeExcerpt($response->body(), 400);
            $hint = $providerMessage ?: ($excerpt ?: 'بدنهٔ پاسخ خالی است');

            $detailParts = array_filter([
                "HTTP {$status}",
                "mode={$requestMode}",
                $contentType !== '' ? "Content-Type: {$contentType}" : null,
                is_scalar($providerCode) && $providerCode !== '' ? "code: {$providerCode}" : null,
                $providerMessage ? "provider_message: {$providerMessage}" : null,
                $excerpt !== '' && $excerpt !== $providerMessage ? "body: {$excerpt}" : null,
            ]);

            return $this->fail(
                $context,
                $startedAt,
                'http_error',
                'image',
                "سرویس استودیو خطای HTTP {$status} برگرداند: {$hint}",
                implode(' | ', $detailParts),
                $status
            );
        } catch (HttpClientException $error) {
            $reason = $this->sanitizeText($error->getMessage(), 350);

            return $this->fail(
                $context,
                $startedAt,
                'http_error',
                'image',
                'خطای درخواست HTTP در استودیو: '.$reason,
                get_class($error).": {$reason}"
            );
        } catch (\Throwable $error) {
            $reason = $this->sanitizeText($error->getMessage(), 350);
            $this->recordLog($context + [
                'state' => 'error',
                'stage' => 'unexpected',
                'http_status' => null,
                'duration_ms' => $this->elapsedMs($startedAt),
                'message' => 'خطای غیرمنتظره در ارسال درخواست استودیو: '.$reason,
                'detail' => get_class($error).': '.$reason,
            ]);
            throw $error;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $status = $httpResponse->status();
        $contentType = (string) ($httpResponse->header('Content-Type') ?? '');
        $body = $httpResponse->body();
        $payload = $httpResponse->json();

        if (!is_array($payload)) {
            return $this->fail(
                $context,
                $startedAt,
                'response_format',
                'image',
                'پاسخ سرویس استودیو قالب JSON معتبر نداشت.',
                "HTTP {$status} | mode={$requestMode} | Content-Type: ".($contentType ?: '(none)').' | body_bytes='.strlen($body).' | body='.$this->sanitizeExcerpt($body, 350),
                $status
            );
        }

        $b64 = data_get($payload, 'data.0.b64_json');
        $imageUrl = data_get($payload, 'data.0.url');
        $sourceKind = 'b64_json';
        $downloadedFrom = null;

        if (is_string($b64) && trim($b64) !== '') {
            if (strlen($b64) >= 24000000) {
                return $this->fail(
                    $context,
                    $startedAt,
                    'response_format',
                    'image',
                    'حجم دادهٔ تصویر بازگشتی از سرویس استودیو بیش از حد مجاز (۲۴ مگابایت base64) است.',
                    "HTTP {$status} | b64_length=".strlen($b64),
                    $status
                );
            }

            $bytes = base64_decode($b64, true);
            if ($bytes === false) {
                $hasDataUri = str_starts_with($b64, 'data:image/');
                $message = $hasDataUri
                    ? 'خروجی b64_json دارای پیشوند Data URI (data:image/...) است و base64 خام نیست.'
                    : 'مقدار data.0.b64_json کدگذاری base64 معتبر ندارد.';

                return $this->fail(
                    $context,
                    $startedAt,
                    'response_format',
                    'image',
                    $message,
                    "HTTP {$status} | b64_length=".strlen($b64).' | prefix='.$this->sanitizeText(substr($b64, 0, 80), 80),
                    $status
                );
            }
        } elseif (is_string($imageUrl) && trim($imageUrl) !== '') {
            $sourceKind = 'url';
            [$bytes, $downloadedFrom] = $this->downloadImageUrl(trim($imageUrl), $context, $startedAt, $status);
        } else {
            $keys = implode(', ', array_keys($payload)) ?: '(empty)';
            $providerError = $this->extractProviderError($payload);
            $message = $providerError !== null
                ? "سرویس استودیو در پاسخ HTTP {$status} خطا برگرداند: {$providerError}"
                : "فیلد data.0.b64_json یا data.0.url در پاسخ سرویس استودیو یافت نشد (کلیدهای JSON: {$keys}).";

            return $this->fail(
                $context,
                $startedAt,
                'response_format',
                'image',
                $message,
                "HTTP {$status} | mode={$requestMode} | Content-Type: ".($contentType ?: '(none)')." | keys={$keys} | excerpt=".$this->sanitizeExcerpt($body, 350),
                $status
            );
        }

        $mime = $bytes !== '' ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : '';
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            return $this->fail(
                $context,
                $startedAt,
                'response_format',
                'image',
                'خروجی تصویری قابل ذخیره دریافت نشد'.($mime ? " (نوع فایل تشخیص‌داده‌شده: {$mime})." : '.'),
                "HTTP {$status} | source={$sourceKind} | decoded_bytes=".strlen($bytes).' | detected_mime='.($mime ?: 'empty').' | excerpt='.$this->sanitizeExcerpt($bytes, 200),
                $status
            );
        }

        $directory = $forCatalog ? 'catalog' : 'studio';
        $disk = $forCatalog ? 'public' : 'local';
        $path = $directory.'/'.Str::uuid().'.'.$extensions[$mime];
        if (!Storage::disk($disk)->put($path, $bytes)) {
            $this->recordLog($context + [
                'state' => 'error',
                'stage' => 'storage',
                'http_status' => $status,
                'duration_ms' => $this->elapsedMs($startedAt),
                'message' => 'ذخیرهٔ فایل تصویر خروجی در فضای ذخیره‌سازی ناموفق بود.',
                'detail' => "disk={$disk} | path={$path} | bytes=".strlen($bytes),
            ]);
            abort(500);
        }

        $sourceSummary = $downloadedFrom ? "url({$downloadedFrom})" : 'b64_json';
        $this->recordLog($context + [
            'state' => 'success',
            'stage' => 'success',
            'http_status' => $status,
            'duration_ms' => $this->elapsedMs($startedAt),
            'message' => 'تصویر با موفقیت تولید شد.',
            'detail' => "HTTP {$status} | mode={$requestMode} | source={$sourceSummary} | output={$path} ({$mime}, ".strlen($bytes).' bytes)',
        ]);

        return $path;
    }

    private function downloadImageUrl(string $url, array $context, int $startedAt, int $apiStatus): array
    {
        $currentUrl = $url;
        for ($hop = 0; $hop <= 3; $hop++) {
            $rejection = $this->unsafeDownloadReason($currentUrl);
            if ($rejection !== null) {
                $this->fail(
                    $context,
                    $startedAt,
                    'download',
                    'image',
                    "نشانی تصویر خروجی (data.0.url) مجاز نیست: {$rejection}",
                    "API HTTP {$apiStatus} | blocked_download_url={$this->safeUrl($currentUrl)} | reason={$rejection}",
                    $apiStatus
                );
            }

            try {
                $response = Http::timeout(30)->connectTimeout(5)->withoutRedirecting()->get($currentUrl);
            } catch (ConnectionException $error) {
                $reason = $this->sanitizeText($error->getMessage(), 300);
                $this->fail(
                    $context,
                    $startedAt,
                    'download',
                    'image',
                    "دریافت تصویر از نشانی خروجی ناموفق بود: {$reason}",
                    "API HTTP {$apiStatus} | download_url={$this->safeUrl($currentUrl)} | ConnectionException: {$reason}",
                    $apiStatus
                );
            }

            $downloadStatus = $response->status();
            if (in_array($downloadStatus, [301, 302, 303, 307, 308], true)) {
                $location = trim((string) $response->header('Location'));
                if ($location === '') {
                    $this->fail(
                        $context,
                        $startedAt,
                        'download',
                        'image',
                        "نشانی تصویر خروجی پاسخ تغییر مسیر (HTTP {$downloadStatus}) بدون مقصد داد.",
                        "API HTTP {$apiStatus} | download_url={$this->safeUrl($currentUrl)} | HTTP {$downloadStatus} empty Location",
                        $downloadStatus
                    );
                }
                if (!preg_match('/^https?:\/\//i', $location)) {
                    $parts = parse_url($currentUrl);
                    $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
                    $location = str_starts_with($location, '/') ? $origin.$location : $origin.'/'.$location;
                }
                $currentUrl = $location;
                continue;
            }

            if ($response->failed()) {
                $this->fail(
                    $context,
                    $startedAt,
                    'download',
                    'image',
                    "دریافت فایل تصویر از نشانی خروجی ناموفق بود (HTTP {$downloadStatus}).",
                    "API HTTP {$apiStatus} | download_url={$this->safeUrl($currentUrl)} | download HTTP {$downloadStatus} | body=".$this->sanitizeExcerpt($response->body(), 250),
                    $downloadStatus
                );
            }

            $bytes = $response->body();
            if (strlen($bytes) > self::MAX_DOWNLOAD_BYTES) {
                $this->fail(
                    $context,
                    $startedAt,
                    'download',
                    'image',
                    'حجم فایل تصویر دریافت‌شده از نشانی خروجی بیش از حد مجاز است.',
                    "download_url={$this->safeUrl($currentUrl)} | bytes=".strlen($bytes),
                    $downloadStatus
                );
            }

            return [$bytes, $this->safeUrl($currentUrl)];
        }

        $this->fail(
            $context,
            $startedAt,
            'download',
            'image',
            'تعداد تغییر مسیرهای (redirect) نشانی تصویر خروجی بیش از حد مجاز بود.',
            "API HTTP {$apiStatus} | last_download_url={$this->safeUrl($currentUrl)}",
            $apiStatus
        );
    }

    private function unsafeDownloadReason(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return 'نشانی باید با https:// آغاز شود.';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'نشانی نباید شامل اطلاعات کاربری باشد.';
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return 'فقط پورت استاندارد HTTPS مجاز است.';
        }
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return 'میزبان محلی یا داخلی مجاز نیست.';
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                ? null
                : 'آی‌پی خصوصی یا رزروشده مجاز نیست.';
        }
        if (app()->runningUnitTests()) {
            return null;
        }
        $ips = $this->resolveHostIps($host);
        if ($ips === []) {
            return 'نام دامنه به آی‌پی معتبر ترجمه نشد.';
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'آی‌پی مقصد در محدودهٔ خصوصی یا رزروشده است.';
            }
        }

        return null;
    }

    private function resolveHostIps(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($ip) && $ip !== '') {
                    $ips[] = $ip;
                }
            }
        }
        if ($ips === []) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = $v4;
            }
        }

        return $ips;
    }

    private function isGenerationsUrl(string $url): bool
    {
        $path = strtolower(rtrim((string) parse_url(trim($url), PHP_URL_PATH), '/'));

        return str_ends_with($path, '/images/generations');
    }

    private function baseContext(UploadedFile $image, int $presetId, ?array $preset, array $settings, string $requestMode): array
    {
        $size = $image->getSize();

        return [
            'preset_id' => $presetId,
            'preset_title' => $preset['title'] ?? null,
            'model' => $settings['ai_model'] ?? null,
            'url' => $this->safeUrl($settings['ai_url'] ?? ''),
            'mode' => $requestMode,
            'has_token' => !empty($settings['ai_token']),
            'source_mime' => $image->getMimeType(),
            'source_ext' => $image->extension(),
            'source_bytes' => is_int($size) ? $size : null,
        ];
    }

    private function fail(
        array $context,
        int $startedAt,
        string $stage,
        string $errorKey,
        string $message,
        string $detail,
        ?int $httpStatus = null
    ): never {
        $this->recordLog($context + [
            'state' => 'error',
            'stage' => $stage,
            'http_status' => $httpStatus,
            'duration_ms' => $this->elapsedMs($startedAt),
            'message' => $message,
            'detail' => $detail,
        ]);

        throw ValidationException::withMessages([$errorKey => $message]);
    }

    private function recordLog(array $entry): void
    {
        $record = ['attempted_at' => now()->toIso8601String()] + $entry;

        $logContext = $record;
        unset($logContext['attempted_at']);
        if ($record['state'] === 'success') {
            Log::info('Image studio generation succeeded', $logContext);
        } else {
            Log::warning('Image studio generation failed', $logContext);
        }

        try {
            $attempts = Entry::getData('studio', 'logs')['attempts'] ?? [];
            if (!is_array($attempts)) {
                $attempts = [];
            }
            array_unshift($attempts, $record);
            Entry::putData('studio', 'logs', ['attempts' => array_slice($attempts, 0, self::LOG_LIMIT)]);
        } catch (\Throwable $error) {
            Log::error('Failed to persist image studio diagnostic log', [
                'error' => $this->sanitizeText($error->getMessage(), 200),
            ]);
        }
    }

    private function elapsedMs(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }

    private function safeUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '(خالی)';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $this->sanitizeText($url, 140);
        }
        $scheme = $parts['scheme'] ?? 'https';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';

        return "{$scheme}://{$parts['host']}{$port}{$path}";
    }

    private function extractProviderError(mixed $json): ?string
    {
        if (!is_array($json)) {
            return null;
        }
        $candidates = [
            data_get($json, 'error.message'),
            data_get($json, 'error.detail'),
            is_string($json['error'] ?? null) ? $json['error'] : null,
            $json['message'] ?? null,
            $json['detail'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $this->sanitizeText($candidate, 300);
            }
        }
        if (isset($json['errors']) && is_array($json['errors'])) {
            $first = collect($json['errors'])->flatten()->first(fn ($value) => is_string($value) && trim($value) !== '');
            if (is_string($first)) {
                return $this->sanitizeText($first, 300);
            }
        }

        return null;
    }

    private function sanitizeExcerpt(string $body, int $limit = 350): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }
        if (str_contains($body, '<')) {
            $body = strip_tags($body);
        }

        return $this->sanitizeText($body, $limit);
    }

    private function sanitizeText(string $text, int $limit = 350): string
    {
        $clean = preg_replace('/(Bearer\s+)[A-Za-z0-9._\-]+/i', '$1***', $text) ?? $text;
        $clean = preg_replace('/\b(sk-[A-Za-z0-9_-]{6,})\b/', 'sk-***', $clean) ?? $clean;
        $clean = preg_replace('/[^\P{C}\n\t]+/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

        return Str::limit(trim($clean), $limit);
    }
}
