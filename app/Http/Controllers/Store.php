<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Services\Pricing;
use App\Support\GoldRateDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Store extends Controller
{
    private const CATALOG_PER_PAGE = 12;

    private const WEIGHT_RANGES = [
        'under-3' => ['label' => 'کمتر از ۳ گرم', 'min' => 1, 'max' => 2999],
        '3-to-6' => ['label' => '۳ تا کمتر از ۶ گرم', 'min' => 3000, 'max' => 5999],
        '6-and-up' => ['label' => '۶ گرم و بالاتر', 'min' => 6000, 'max' => null],
    ];

    public function __construct(private readonly Pricing $pricing) {}

    public function goldRate(): JsonResponse
    {
        return response()->json(GoldRateDisplay::from(Entry::getData('rate', 'latest')), headers: [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function catalog(Request $request, ?string $category = null)
    {
        $categories = config('barin.categories');
        $legacyCategory = $request->query('category');
        if ($category === null && is_string($legacyCategory) && $legacyCategory !== '') {
            abort_unless(isset($categories[$legacyCategory]), 404);

            $query = $request->only(['q', 'max', 'sort', 'weight', 'in_stock', 'page']);

            return redirect()->route('shop.category', ['category' => $legacyCategory] + $query, 301);
        }
        abort_if($category !== null && ! isset($categories[$category]), 404);

        return $this->show($request, 'catalog', $category);
    }

    public function show(Request $request, string $mode = 'home', ?string $slug = null)
    {
        $categories = config('barin.categories');
        $category = $mode === 'catalog' ? $slug : null;
        $rate = Entry::getData('rate', 'latest');
        $campaigns = $this->pricing->activeCampaigns();
        $published = Entry::products()->published()->orderBy('id');
        $cart = collect($request->session()->get('cart', []));
        $cartProducts = (clone $published)->whereIn('slug', $cart->all());
        $products = collect();
        $collections = collect();
        $product = $pagination = null;
        $filters = [];

        switch ($mode) {
            case 'home':
                foreach ($categories as $key => $label) {
                    $collections->put($key, (clone $published)->where('data->category', $key)->limit(3)->get());
                }
                break;
            case 'catalog':
                $search = $request->query('q');
                $sort = $request->query('sort');
                $weight = $request->query('weight');
                $filters = array_filter([
                    'q' => is_string($search) ? trim($search) : '',
                    'max' => max(0, $request->integer('max')),
                    'sort' => in_array($sort, ['asc', 'desc'], true) ? $sort : '',
                    'weight' => is_string($weight) && isset(self::WEIGHT_RANGES[$weight]) ? $weight : '',
                    'in_stock' => (int) $request->boolean('in_stock'),
                ], fn ($value) => $value !== '' && $value !== 0);
                $pagination = $this->catalogProducts($published, $category, $filters, $rate, $campaigns);
                $products = $pagination->getCollection();
                break;
            case 'product':
                $product = (clone $published)->where('slug', $slug)->firstOrFail();
                $products = (clone $published)->where('slug', '!=', $slug)->limit(3)->get();
                break;
            case 'cart':
                $products = $cartProducts->get()->keyBy('slug');
                $available = $cart->intersect($products->keys())->values();
                if ($available->count() !== $cart->count()) {
                    $request->session()->put('cart', $available->all());
                }
                $cart = $available;
                break;
        }
        if ($mode !== 'cart' && $cart->isNotEmpty()) {
            $cart = $cart->intersect($cartProducts->pluck('slug'))->values();
        }

        $displayed = $mode === 'home' ? $collections->collapse() : $products;
        if ($product) {
            $displayed = $displayed->concat([$product]);
        }
        $quotes = $displayed->keyBy('slug')->map(fn (Entry $item) => $this->pricing->forProduct($item, $rate, $campaigns));
        $page = Entry::getData('page', $mode === 'page' ? $slug : $mode);
        abort_if($mode === 'page' && ! $page, 404);
        $seoKey = match (true) {
            $mode === 'product' => 'product-'.$slug,
            $mode === 'page' => $slug,
            $category !== null => 'category-'.$category,
            default => $mode,
        };
        $seo = Entry::getData('seo', $seoKey);

        return view('store', compact(
            'mode', 'slug', 'rate', 'products', 'quotes', 'product', 'page', 'seo', 'cart', 'campaigns',
            'categories', 'category', 'collections', 'pagination', 'filters'
        ) + [
            'checkoutKey' => $mode === 'cart' ? hash('sha256', json_encode([$cart->all(), $quotes->sortKeys()])) : null,
            'weightRanges' => self::WEIGHT_RANGES,
        ]);
    }

    private function catalogProducts(Builder $query, ?string $category, array $filters, array $rate, Collection $campaigns): LengthAwarePaginator
    {
        if ($category !== null) {
            $query->where('data->category', $category);
        }
        if (isset($filters['q'])) {
            $query->matching($filters['q'], ['data->name']);
        }
        $grammar = $query->getQuery()->getGrammar();
        if (isset($filters['weight'])) {
            $range = self::WEIGHT_RANGES[$filters['weight']];
            $column = $grammar->wrap('data->milligrams');
            // Existing products can store weight and stock as JSON strings.
            $weight = "cast($column as decimal(12, 0))";
            $query->whereRaw("$weight >= ?", [$range['min']]);
            if ($range['max'] !== null) {
                $query->whereRaw("$weight <= ?", [$range['max']]);
            }
        }
        if (isset($filters['in_stock'])) {
            $column = $grammar->wrap('data->stock');
            $query->whereRaw("cast($column as decimal(12, 0)) > 0");
        }
        if (isset($filters['max']) || isset($filters['sort'])) {
            // The final price depends on the current rate and active discounts.
            $items = $query->get();
            $totals = $items->mapWithKeys(fn (Entry $item) => [
                $item->id => $this->pricing->forProduct($item, $rate, $campaigns)['total'],
            ]);
            if (isset($filters['max'])) {
                $items = $items->filter(fn (Entry $item) => $totals[$item->id] <= $filters['max']);
            }
            if (isset($filters['sort'])) {
                $items = $items->sortBy(fn (Entry $item) => $totals[$item->id], descending: $filters['sort'] === 'desc');
            }
            $page = LengthAwarePaginator::resolveCurrentPage();
            $pagination = new LengthAwarePaginator(
                $items->forPage($page, self::CATALOG_PER_PAGE)->values(), $items->count(), self::CATALOG_PER_PAGE, $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );
        } else {
            $pagination = $query->paginate(self::CATALOG_PER_PAGE);
        }
        abort_if($pagination->currentPage() > $pagination->lastPage(), 404);

        return $pagination->appends($filters);
    }

    public function changeCart(Request $request, string $slug)
    {
        abort_unless($request->boolean('remove') || Entry::products()->published()->where('slug', $slug)->exists(), 404);
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
            $campaigns = $this->pricing->activeCampaigns();
            $products = Entry::products()->published()->whereIn('slug', $slugs)->orderBy('id')->lockForUpdate()->get();
            $quotes = $products->keyBy('slug')->map(fn (Entry $item) => $this->pricing->forProduct($item, $rate, $campaigns));
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
