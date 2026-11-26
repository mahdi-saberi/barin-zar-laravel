<?php

return [
    'admin_email' => env('BARIN_ADMIN_EMAIL'),
    'integrations' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) env('BARIN_INTEGRATION_HOSTS', 'api.openai.com')))))),
    'plugins' => [],
    'categories' => ['necklace' => 'گردنبند', 'bracelet' => 'دستبند', 'earrings' => 'گوشواره', 'ring' => 'انگشتر', 'set' => 'نیم‌ست و سرویس', 'anklet' => 'پابند'],
    'contact' => [
        'address' => 'تهران، بازار 15 خرداد، سبزه میدان، مرکز طلا و جواهر ایران، پلاک 78',
        'phone' => '02155616452',
        'dial' => '+982155616452',
    ],
    'catalog' => [
        'cover' => '/assets/campaign.webp',
        'intro' => 'برای خودتان یا یک هدیه، قطعه‌ها را بر اساس نوع، وزن و بودجه مقایسه کنید.',
        'collections' => [
            'necklace' => ['cover' => '/assets/m1.webp', 'thumb' => '/assets/p1.webp', 'intro' => 'از آویزهای ظریف تا طرح‌های چشمگیر. نمای کامل و وزن هر گردنبند را پیش از انتخاب ببینید.'],
            'bracelet' => ['cover' => '/assets/m2.webp', 'thumb' => '/assets/p2.webp', 'intro' => 'مدل‌های دستبند را از نظر وزن و فرم مقایسه کنید. برای انتخاب اندازهٔ مناسب مچ می‌توانید با گالری تماس بگیرید.'],
            'earrings' => ['cover' => '/assets/m3.webp', 'thumb' => '/assets/p3.webp', 'intro' => 'گوشواره‌های سبک و طرح‌های درشت‌تر را کنار هم ببینید و با فیلتر وزن، انتخابتان را محدود کنید.'],
            'ring' => ['cover' => '/assets/ring-detail.webp', 'thumb' => '/assets/ring.webp', 'intro' => 'طرح‌های انگشتر را از نمای نزدیک ببینید و برای انتخاب سایز، از گالری راهنمایی بگیرید.'],
            'set' => ['cover' => '/assets/petal-set-detail.webp', 'thumb' => '/assets/petal-set.webp', 'intro' => 'قطعه‌های هماهنگ برای هدیه یا مناسبت‌های خاص. عکس‌ها و ریز قیمت هر نیم‌ست را کنار هم بررسی کنید.'],
            'anklet' => ['cover' => '/assets/anklet-detail.webp', 'thumb' => '/assets/anklet.webp', 'intro' => 'پابندهای ظریف و آویزدار را از نمای نزدیک ببینید و وزن مدل‌ها را با هم مقایسه کنید.'],
        ],
    ],
];
