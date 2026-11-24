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
        foreach ($products as [$slug, $name, $category, $milligrams, $id]) {
            Entry::firstOrCreate(['kind' => 'product', 'slug' => $slug], ['data' => compact('name', 'category', 'milligrams') + [
                'labor' => 1600000, 'profit_bps' => 500, 'tax_bps' => 0, 'discount_bps' => 0,
                'stock' => 3, 'cost' => 0, 'description' => 'طلای زرد ۱۸ عیار. نمونه نمایشی گالری برین‌زر.',
                'images' => $id ? ["/assets/p{$id}.webp", "/assets/m{$id}.webp"] : ["/assets/{$slug}.webp", "/assets/{$slug}-detail.webp"], 'image_alts' => $id ? ['نمای محصول', 'روی مدل'] : ['نمای کامل', 'برش جزئیات'],
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
