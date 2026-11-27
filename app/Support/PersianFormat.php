<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

final class PersianFormat
{
    public static function number(int $value): string
    {
        return CalendarUtils::convertNumbers(number_format($value, 0, '.', '٬'));
    }

    public static function dateTime(?string $timestamp, string $format = 'Y/m/d H:i'): ?string
    {
        if (! $timestamp) {
            return null;
        }

        $timezone = 'Asia/Tehran';
        try {
            $local = CarbonImmutable::parse($timestamp, $timezone)->setTimezone($timezone);
        } catch (InvalidFormatException) {
            return null;
        }

        return CalendarUtils::convertNumbers(Jalalian::fromDateTime($local)->format($format));
    }
}
