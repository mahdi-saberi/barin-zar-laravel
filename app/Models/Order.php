<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    public const UPDATED_AT = null;

    public const STATUSES = [
        'pending' => 'در انتظار پرداخت',
        'paid' => 'پرداخت‌شده',
        'fulfilled' => 'تحویل‌شده',
        'cancelled' => 'لغوشده',
        'expired' => 'منقضی‌شده',
        'review' => 'نیازمند تطبیق مالی',
    ];

    public const TRANSITIONS = ['pending' => ['paid', 'cancelled'], 'paid' => ['fulfilled']];

    protected $guarded = ['id'];

    protected $hidden = ['shipping_address'];

    protected function casts(): array
    {
        return [
            'customer' => 'array', 'lines' => 'array', 'total' => 'integer',
            'expires_at' => 'datetime', 'paid_at' => 'datetime', 'shipping_address' => 'encrypted',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function record(string $event, ?int $userId = null): void
    {
        DB::table('order_events')->insert([
            'order_id' => $this->id, 'user_id' => $userId, 'event' => $event, 'created_at' => now(),
        ]);
    }

    public function releaseStock(): void
    {
        $products = Entry::products()->whereIn('slug', array_column($this->lines, 'slug'))
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($products as $product) {
            $data = $product->data;
            $data['stock']++;
            $product->update(['data' => $data]);
        }
    }

    public function financials(): array
    {
        $parts = [];
        foreach (['gold', 'labor', 'profit', 'discount', 'tax', 'cost'] as $key) {
            $parts[$key] = array_sum(array_column($this->lines, $key));
        }
        $costs = array_column($this->lines, 'cost');
        $parts['margin'] = $costs && count($costs) === count($this->lines) && min($costs) > 0
            ? $this->total - $parts['tax'] - $parts['cost'] : null;

        return $parts;
    }
}
