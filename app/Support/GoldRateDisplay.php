<?php

namespace App\Support;

final class GoldRateDisplay
{
    public static function from(array $rate): array
    {
        $value = (int) ($rate['value'] ?? 0);
        if ($value < 1) {
            return ['value' => null, 'price' => null, 'updated' => null, 'datetime' => null, 'source' => null];
        }

        $updated = PersianFormat::dateTime($rate['timestamp'] ?? null);

        return [
            'value' => $value,
            'price' => PersianFormat::number($value),
            'updated' => $updated,
            'datetime' => $updated ? $rate['timestamp'] : null,
            'source' => match ($rate['source'] ?? '') {
                'api' => null,
                'manual' => 'دستی',
                default => 'نمونه',
            },
        ];
    }
}
