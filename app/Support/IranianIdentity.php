<?php

namespace App\Support;

final class IranianIdentity
{
    public static function digits(mixed $value): ?string
    {
        return is_string($value) ? strtr(trim($value), [
            '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9',
            '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9',
        ]) : null;
    }

    public static function mobile(mixed $value): ?string
    {
        $mobile = self::digits($value);

        return $mobile === null ? null : preg_replace('/\A(?:\+98|0098|98)(?=9[0-9]{9}\z)/', '0', str_replace([' ', '-'], '', $mobile));
    }

    public static function validNationalId(string $id): bool
    {
        if (!preg_match('/\A[0-9]{10}\z/', $id) || count(array_unique(str_split($id))) === 1) {
            return false;
        }

        $total = 0;
        for ($i = 0; $i < 9; $i++) {
            $total += (int) $id[$i] * (10 - $i);
        }
        $remainder = $total % 11;

        return (int) $id[9] === ($remainder < 2 ? $remainder : 11 - $remainder);
    }

    public static function fingerprint(string $id): string
    {
        return hash_hmac('sha256', 'barin-national-id:'.$id, (string) config('app.key'));
    }
}
