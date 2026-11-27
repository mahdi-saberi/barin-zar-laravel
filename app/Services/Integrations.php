<?php

namespace App\Services;

use App\Models\Entry;
use App\Support\IranianIdentity;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Integrations
{
    public function validateUrl(?string $url, string $field): string
    {
        $url = trim((string) $url);
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') {
            throw ValidationException::withMessages([$field => 'نشانی سرویس باید با https:// آغاز شود.']);
        }
        if (isset($parts['user']) || isset($parts['port']) || isset($parts['pass'])) {
            throw ValidationException::withMessages([$field => 'نشانی سرویس نباید شامل پورت یا نام کاربری در آدرس باشد.']);
        }
        $host = strtolower($parts['host'] ?? '');
        $allowed = config('barin.integrations');
        if ($host === '' || !in_array($host, $allowed, true)) {
            $allowedText = $allowed ? implode(', ', $allowed) : 'تعریف‌نشده';
            throw ValidationException::withMessages([
                $field => "میزبان «{$host}» در فهرست میزبان‌های مجاز (BARIN_INTEGRATION_HOSTS: {$allowedText}) نیست.",
            ]);
        }

        return $url;
    }

    public function endpoint(array $settings, string $name)
    {
        $this->validateUrl($settings[$name.'_url'] ?? '', $name.'_url');
        $request = Http::acceptJson()->timeout($name === 'ai' ? 120 : 8)->connectTimeout(5)->withoutRedirecting();
        return empty($settings[$name.'_token']) ? $request : $request->withToken(Crypt::decryptString($settings[$name.'_token']));
    }

    public function refresh(): void
    {
        $settings = Entry::getData('settings', 'store');
        $value = $this->endpoint($settings, 'rate')->get($settings['rate_url'])->throw()->json($settings['rate_path']);
        if (is_string($value)) {
            $value = IranianIdentity::digits($value);
            if (preg_match('/\A[0-9]{1,3}(?:[,٬][0-9]{3})+(?:[.٫][0-9]+)?\z/u', $value)) {
                $value = str_replace([',', '٬', '٫'], ['', '', '.'], $value);
            }
        }
        if (!is_numeric($value) || $value <= 0 || $value > 10000000000) {
            throw ValidationException::withMessages(['rate_url' => 'پاسخ سرویس نرخ معتبر ندارد.']);
        }
        $rate = (int) round($value / (($settings['rate_unit'] ?? '') === 'rial' ? 10 : 1));
        if ($rate < 1) {
            throw ValidationException::withMessages(['rate_url' => 'نرخ تبدیل شده معتبر نیست.']);
        }
        Entry::putData('rate', 'latest', ['value' => $rate, 'source' => 'api', 'timestamp' => now()->toIso8601String()]);
    }
}
