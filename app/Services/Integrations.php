<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Integrations
{
    public function endpoint(array $settings, string $name)
    {
        $url = $settings[$name.'_url'] ?? '';
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), config('barin.integrations'), true)
            || isset($parts['user']) || isset($parts['port']) || isset($parts['pass'])) {
            throw ValidationException::withMessages([$name.'_url' => 'نشانی HTTPS و میزبان مجاز لازم است.']);
        }
        $request = Http::acceptJson()->timeout($name === 'ai' ? 120 : 8)->connectTimeout(5)->withoutRedirecting();
        return empty($settings[$name.'_token']) ? $request : $request->withToken(Crypt::decryptString($settings[$name.'_token']));
    }

    public function refresh(): void
    {
        $settings = Entry::getData('settings', 'store');
        $value = $this->endpoint($settings, 'rate')->get($settings['rate_url'])->throw()->json($settings['rate_path']);
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
