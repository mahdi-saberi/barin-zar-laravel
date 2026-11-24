<?php

namespace App\Services;

class Pricing
{
    public function breakdown(array $p, int $rate, int $promo = 0): array
    {
        $gold = intdiv($rate * $p['milligrams'] + 500, 1000);
        $labor = (int) $p['labor'];
        $profit = intdiv(($gold + $labor) * $p['profit_bps'] + 5000, 10000);
        $discount = intdiv(($labor + $profit) * max($p['discount_bps'], $promo), 10000);
        $tax = intdiv(($labor + $profit - $discount) * $p['tax_bps'] + 5000, 10000);
        return compact('gold', 'labor', 'profit', 'discount', 'tax') + [
            'total' => $gold + $labor + $profit - $discount + $tax,
            'rate' => $rate,
        ];
    }
}
