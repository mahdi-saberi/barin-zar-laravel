<?php

return [
    'admin_email' => env('BARIN_ADMIN_EMAIL'),
    'integrations' => array_values(array_filter(array_map('trim', explode(',', env('BARIN_INTEGRATION_HOSTS', 'api.openai.com'))))),
    'plugins' => [],
    'categories' => ['necklace' => 'گردنبند', 'bracelet' => 'دستبند', 'earrings' => 'گوشواره', 'ring' => 'انگشتر', 'set' => 'نیم‌ست و سرویس', 'anklet' => 'پابند'],
];
