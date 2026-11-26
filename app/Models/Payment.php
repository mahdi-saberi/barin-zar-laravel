<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const LABELS = [
        'created' => 'درخواست اولیه', 'redirected' => 'ارسال به بانک', 'failed' => 'خطای دریافت توکن',
        'verify_pending' => 'در انتظار استعلام', 'confirmed' => 'تأیید بانکی', 'review' => 'نیازمند تطبیق',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['credentials', 'token', 'reference', 'return_data'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array', 'token' => 'encrypted', 'return_data' => 'encrypted:array',
            'amount_rial' => 'integer', 'fee_rial' => 'integer', 'confirmed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
