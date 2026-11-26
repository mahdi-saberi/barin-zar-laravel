<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Support\Collection;

class Pricing
{
    public function activeCampaigns(): Collection
    {
        $today = today()->toDateString();

        return Entry::where('kind', 'campaign')->orderBy('id')->get()->filter(fn (Entry $campaign) =>
            $today >= $campaign->data['start'] && $today <= $campaign->data['end']
        );
    }

    public function forProduct(Entry $product, array $rate, Collection $campaigns): array
    {
        $discount = $campaigns->filter(fn (Entry $campaign) =>
            empty($campaign->data['product']) || $campaign->data['product'] === $product->slug
        )->max(fn (Entry $campaign) => $campaign->data['discount_bps']);

        return $this->breakdown($product->data, $rate['value'] ?? 0, $discount ?? 0);
    }

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
