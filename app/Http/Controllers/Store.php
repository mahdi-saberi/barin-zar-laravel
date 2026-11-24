<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Services\Pricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Store extends Controller
{
    public function show(Request $request, string $mode = 'home', ?string $slug = null)
    {
        $rate = Entry::getData('rate', 'latest');
        $products = Entry::where('kind', 'product')->orderBy('id')->get()->keyBy('slug');
        $campaigns = Entry::where('kind', 'campaign')->orderBy('id')->get()->filter(function ($campaign) {
            $c = $campaign->data;
            return now()->toDateString() >= $c['start'] && now()->toDateString() <= $c['end'];
        });
        $quotes = $products->map(fn ($p) => $this->price($p, $rate, $campaigns));
        $product = $mode === 'product' ? $products->get($slug) : null;
        abort_if($mode === 'product' && !$product, 404);
        $page = Entry::getData('page', $mode === 'page' ? $slug : $mode);
        abort_if($mode === 'page' && !$page, 404);
        $seo = Entry::getData('seo', $mode === 'product' ? 'product-'.$slug : ($mode === 'page' ? $slug : $mode));
        $cart = collect($request->session()->get('cart', []))->intersect($products->keys());
        if ($mode === 'catalog') {
            $products = $products->filter(fn ($p) =>
                (!$request->filled('category') || $p->data['category'] === $request->query('category'))
                && (!$request->filled('q') || str_contains($p->data['name'], $request->string('q')->toString()))
                && (!$request->filled('max') || $quotes[$p->slug]['total'] <= $request->integer('max')));
            if (in_array($request->query('sort'), ['asc', 'desc'], true)) {
                $products = $products->sortBy(fn ($p) => $quotes[$p->slug]['total'], descending: $request->query('sort') === 'desc');
            }
        }
        return view('store', compact('mode', 'slug', 'rate', 'products', 'quotes', 'product', 'page', 'seo', 'cart', 'campaigns') + [
            'categories' => config('barin.categories'),
            'checkoutKey' => hash('sha256', json_encode([$cart->values(), $quotes->only($cart->all())->sortKeys()])),
        ]);
    }

    public function price($product, array $rate, $campaigns): array
    {
        $promo = $campaigns->filter(fn ($c) => empty($c->data['product']) || $c->data['product'] === $product->slug)
            ->max(fn ($c) => $c->data['discount_bps']) ?? 0;
        return app(Pricing::class)->breakdown($product->data, $rate['value'] ?? 0, $promo);
    }

    public function changeCart(Request $request, string $slug)
    {
        abort_unless(Entry::getData('product', $slug), 404);
        $cart = collect($request->session()->get('cart', []))->reject(fn ($id) => $id === $slug);
        if (!$request->boolean('remove')) {
            $cart->push($slug);
        }
        $request->session()->put('cart', $cart->values()->all());
        return redirect('/cart');
    }

    public function order(Request $request)
    {
        $customer = $request->validate([
            'name' => 'required|string|max:120', 'mobile' => 'required|string|max:24',
            'delivery' => 'required|in:pickup,shipping', 'address' => 'required_if:delivery,shipping|nullable|string|max:500',
        ]);
        $slugs = $request->session()->get('cart', []);
        if (!$slugs) {
            return back()->withErrors(['cart' => 'سبد خرید خالی است.']);
        }
        $reference = (string) Str::uuid();
        DB::transaction(function () use ($slugs, $customer, $reference, $request) {
            $rate = Entry::getData('rate', 'latest');
            if (!$rate || ($rate['source'] === 'api' && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($rate['timestamp']), true) > 20)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'نرخ به‌روز در دسترس نیست.']);
            }
            if ($rate['source'] === 'sample' && app()->isProduction()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'برای ثبت سفارش، نرخ واقعی باید فعال شود.']);
            }
            $campaigns = Entry::where('kind', 'campaign')->get()->filter(fn ($c) =>
                now()->toDateString() >= $c->data['start'] && now()->toDateString() <= $c->data['end']);
            $products = Entry::where('kind', 'product')->whereIn('slug', $slugs)->orderBy('id')->lockForUpdate()->get();
            $quotes = $products->keyBy('slug')->map(fn ($p) => $this->price($p, $rate, $campaigns));
            if (count($slugs) !== $products->count() || !hash_equals(hash('sha256', json_encode([$slugs, $quotes->sortKeys()])), (string) $request->input('checkout_key'))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'سبد یا قیمت تغییر کرد. قیمت جدید را بررسی کنید.']);
            }
            $lines = [];
            foreach ($products as $p) {
                $data = $p->data;
                if ($data['stock'] < 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['cart' => $data['name'].' موجود نیست.']);
                }
                $lines[] = $quotes[$p->slug] + ['slug' => $p->slug, 'name' => $data['name'], 'cost' => $data['cost']];
                $data['stock']--;
                $p->update(['data' => $data]);
            }
            DB::table('orders')->insert(['reference' => $reference, 'customer' => json_encode($customer),
                'lines' => json_encode($lines), 'total' => array_sum(array_column($lines, 'total')), 'created_at' => now()]);
        }, 3);
        $request->session()->forget('cart');
        return redirect('/cart')->with('notice', 'سفارش ثبت شد. کد پیگیری '.$reference);
    }
}
