<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RateUpdater
{
    public function __construct(private readonly Integrations $integrations) {}

    public function run(string $trigger): array
    {
        $status = ['attempted_at' => now()->toIso8601String(), 'state' => 'updated', 'message' => 'نرخ دریافت شد.'];
        if (empty(Entry::getData('settings', 'store')['rate_url'])) {
            $status['state'] = 'unconfigured';
            $status['message'] = 'نشانی سرویس نرخ در تنظیمات وارد نشده است.';
        } else {
            try {
                $this->integrations->refresh();
            } catch (ConnectionException|RequestException|ValidationException|DecryptException $error) {
                $status['state'] = 'error';
                $status['message'] = match (true) {
                    $error instanceof ConnectionException => 'ارتباط با سرویس نرخ برقرار نشد.',
                    $error instanceof RequestException => 'سرویس نرخ پاسخ HTTP '.$error->response->status().' داد.',
                    $error instanceof DecryptException => 'توکن با کلید فعلی برنامه باز نمی‌شود. توکن را دوباره ثبت کنید.',
                    default => collect($error->errors())->flatten()->first(),
                };
                Log::warning('Gold rate refresh failed', ['trigger' => $trigger, 'message' => $status['message']]);
            }
        }
        Entry::putData('rate', $trigger, $status);

        return $status;
    }
}
