<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class Media
{
    public const LIMIT = 16;

    public const VIDEO_RULE = 'required|file|mimetypes:video/mp4,video/webm|max:32768';

    public static function localPath(string $path): bool
    {
        return str_starts_with($path, '/') && !str_starts_with($path, '//')
            && !preg_match('~[\x00-\x20\x7f\\\\<>]~', $path);
    }

    public static function type(string $path): ?string
    {
        if (!self::localPath($path)) {
            return null;
        }

        return match (strtolower(pathinfo((string) parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION))) {
            'jpg', 'jpeg', 'png', 'webp' => 'image',
            'mp4', 'webm' => 'video',
            default => null,
        };
    }

    public static function homeHero(array $page): ?string
    {
        if (!array_key_exists('hero', $page) || ($page['hero'] === '/assets/campaign.webp' && !array_key_exists('hero_alt', $page))) {
            return '/assets/barinzar-hero.webp';
        }

        return $page['hero'];
    }

    public static function productGallery(array $product): array
    {
        if (isset($product['media'])) {
            return $product['media'];
        }

        $media = [];
        foreach ($product['images'] ?? [] as $index => $path) {
            if ($path && self::localPath($path)) {
                $media[] = ['path' => $path, 'alt' => $product['image_alts'][$index] ?? ''];
            }
        }

        return $media;
    }

    public static function productFields(array $validated): array
    {
        if (isset($validated['media'])) {
            $items = json_decode($validated['media'], true, 8);
            Validator::make(['media' => $items], [
                'media' => ['required', 'array', 'list', 'min:1', 'max:'.self::LIMIT],
                'media.*' => 'required|array:path,alt',
                'media.*.path' => 'required|string|max:500|distinct:strict',
                'media.*.alt' => 'nullable|string|max:180',
            ], [
                'media.*.path.distinct' => 'هر فایل را یک‌بار به گالری اضافه کنید.',
                'media.*.alt.max' => 'متن جایگزین تصویر حداکثر ۱۸۰ نویسه است.',
            ], ['media' => 'گالری محصول', 'media.*.path' => 'فایل گالری', 'media.*.alt' => 'متن جایگزین'])->validate();

            $photos = [];
            $gallery = [];
            foreach ($items as $item) {
                if (!self::localPath($item['path'])) {
                    throw ValidationException::withMessages(['media' => 'یک عکس یا ویدئوی معتبر از کتابخانه انتخاب کنید.']);
                }
                $type = self::type($item['path']) ?? 'image';
                $media = ['path' => $item['path'], 'alt' => $type === 'image' ? trim($item['alt'] ?? '') : ''];
                $gallery[] = $media;
                if ($type === 'image') {
                    $photos[] = $media;
                }
            }
        } else {
            // Older forms submit separate lines for images and their alternative text.
            $lines = preg_split('/\R/u', $validated['images'] ?? '');
            $alts = preg_split('/\R/u', $validated['image_alts'] ?? '');
            $photos = [];
            foreach ($lines as $index => $line) {
                $path = trim($line);
                if ($path === '') {
                    continue;
                }
                if (!self::localPath($path) || self::type($path) === 'video') {
                    throw ValidationException::withMessages(['images' => 'یک مسیر داخلی تصویر وارد کنید.']);
                }
                $photos[] = ['path' => $path, 'alt' => trim($alts[$index] ?? '')];
            }
            $gallery = $photos;
        }

        if ($photos === [] || count($gallery) > self::LIMIT) {
            throw ValidationException::withMessages([isset($validated['media']) ? 'media' : 'images' =>
                'حداقل یک عکس برای کاور و حداکثر ۱۶ فایل برای گالری محصول انتخاب کنید.']);
        }

        return ['media' => $gallery, 'images' => array_column($photos, 'path'), 'image_alts' => array_column($photos, 'alt')];
    }
}
