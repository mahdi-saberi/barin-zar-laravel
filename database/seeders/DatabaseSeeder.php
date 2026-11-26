<?php

namespace Database\Seeders;

use App\Models\Entry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['leaf', 'آویز برگ', 'necklace', 4000, 1],
            ['wave', 'دستبند موج', 'bracelet', 5500, 2],
            ['hoop', 'گوشواره بیضی', 'earrings', 2500, 3],
            ['ring', 'انگشتر گنبد', 'ring', 3000, null],
            ['petal-set', 'نیم‌ست گلبرگ', 'set', 7000, null],
            ['anklet', 'پابند سه‌آویز', 'anklet', 2000, null],
        ];
        // Keep the original six products and reuse their bundled photos for demo variants.
        $styles = [
            'مهتاب', 'باران', 'آفتاب', 'رویا', 'ترمه', 'نسیم', 'آوا', 'یلدا',
            'نگار', 'دریا', 'بهار', 'مانا', 'رها', 'مهر', 'نوا', 'پرنیان',
        ];
        for ($index = 0; $index < 100; $index++) {
            [$slug, $name, $category, $milligrams, $id] = $products[$index % count($products)];
            $imageSlug = $slug;
            $isVariant = $index >= count($products);
            $variant = intdiv($index, count($products));
            if ($isVariant) {
                $slug = sprintf('demo-%s-%02d', $category, $variant);
                $name .= ' طرح '.$styles[$variant - 1];
                $milligrams += $variant * 125;
            }
            Entry::firstOrCreate(['kind' => 'product', 'slug' => $slug], ['data' => compact('name', 'category', 'milligrams') + [
                'labor' => $isVariant ? 800000 + ($index % 12) * 150000 : 1600000,
                'profit_bps' => 500, 'tax_bps' => 0, 'discount_bps' => $isVariant && $index % 5 === 0 ? 500 : 0,
                'stock' => $isVariant ? 1 + ($index % 8) : 3, 'cost' => 0, 'description' => 'طلای زرد ۱۸ عیار. نمونه نمایشی گالری برین‌زر.',
                'images' => $id ? ["/assets/p{$id}.webp", "/assets/m{$id}.webp"] : ["/assets/{$imageSlug}.webp", "/assets/{$imageSlug}-detail.webp"], 'image_alts' => $id ? ['نمای محصول', 'روی مدل'] : ['نمای کامل', 'برش جزئیات'],
            ]]);
        }
        Entry::firstOrCreate(['kind' => 'page', 'slug' => 'home'], ['data' => [
            'name' => 'طلایی که با شما می‌ماند', 'description' => 'قطعه‌ی دلخواهتان را با دیدن جزئیات، عکس روی مدل و ریز قیمت انتخاب کنید.',
            'hero' => '/assets/campaign.webp', 'badge' => 'گالری طلای ۱۸ عیار · بازار تهران',
            'sections' => 'categories, products, campaigns, budget, guide',
        ]]);
        Entry::firstOrCreate(['kind' => 'page', 'slug' => 'guide'], ['data' => [
            'name' => 'راهنمای خرید', 'description' => 'وزن، اجرت و ریز قیمت را در صفحه هر قطعه ببینید. برای انتخاب سایز، مشخصات محصول و عکس روی مدل را کنار هم بررسی کنید.',
        ]]);
        Entry::firstOrCreate(['kind' => 'rate', 'slug' => 'latest'], ['data' => [
            'value' => 10500000, 'source' => 'sample', 'timestamp' => now()->toIso8601String(),
        ]]);
    }
}
