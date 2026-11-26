<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\ImageStudio;
use App\Services\OrderPayments;
use App\Services\Payments\Gateways;
use App\Services\Pricing;
use App\Services\RateUpdater;
use App\Support\IranianIdentity;
use App\Support\Media;
use App\Support\RichText;
use App\Support\StaffAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Admin extends Controller
{
    private const TABS = [
        'dashboard' => 'داشبورد',
        'product' => 'محصولات و موجودی',
        'finance' => 'سفارش‌ها و مالی',
        'customers' => 'مشتریان',
        'users' => 'کاربران و دسترسی‌ها',
        'gateways' => 'درگاه‌های پرداخت',
        'page' => 'صفحه‌ها',
        'campaign' => 'بنرها و کمپین‌ها',
        'seo' => 'سئو',
        'studio' => 'استودیوی تصویر',
        'settings' => 'تنظیمات فروشگاه',
    ];

    private const ACCESS = [
        'dashboard' => 'dashboard', 'product' => 'content', 'page' => 'content', 'campaign' => 'content',
        'seo' => 'content', 'studio' => 'content', 'finance' => 'orders', 'settings' => 'settings',
        'customers' => 'customers', 'users' => 'users', 'gateways' => 'gateways',
    ];

    private const CORE_PAGES = ['home', 'catalog', 'guide'];

    public function __construct(private readonly Pricing $pricing, private readonly ImageStudio $studio) {}

    private function authorizeAdmin(string $permission = 'dashboard'): void
    {
        abort_unless(StaffAccess::allows(Auth::user(), $permission), 403);
    }

    public function loginForm()
    {
        if (StaffAccess::allows(Auth::user(), 'dashboard')) {
            return redirect()->route('admin.index');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|string|max:255', 'password' => 'required|string']);
        $identity = filter_var($data['email'], FILTER_VALIDATE_EMAIL) ? ['email' => $data['email']]
            : ['mobile' => IranianIdentity::mobile($data['email'])];
        $user = User::where($identity)->first();
        if (!StaffAccess::allows($user, 'dashboard') || !Auth::attempt($identity + ['password' => $data['password']])) {
            return back()->withErrors(['email' => 'ورود ناموفق بود.']);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('admin.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    private function fields(): array
    {
        return [
            'page' => [
                'name' => ['عنوان', 'required|string|max:140'], 'description' => ['متن', 'nullable|string|max:12000', 'richtext'],
                'hero' => ['رسانهٔ سربرگ', 'nullable|string|max:500', 'media'], 'badge' => ['بالانویس', 'nullable|string|max:120'],
                'button' => ['متن دکمه اصلی', 'nullable|string|max:80'],
                'products_heading' => ['عنوان مجموعه‌های طلا', 'nullable|string|max:100'],
                'budget_heading' => ['عنوان بخش بودجه', 'nullable|string|max:100'],
                'budget_low' => ['بودجه اول (تومان)', 'nullable|integer|between:1,100000000000', 'number'],
                'budget_high' => ['بودجه دوم (تومان)', 'nullable|integer|between:1,100000000000', 'number'],
                'guide_heading' => ['عنوان بخش راهنما', 'nullable|string|max:100'],
                'guide_text' => ['متن بخش راهنما', 'nullable|string|max:500', 'richtext'],
                'guide_image' => ['رسانهٔ راهنما', 'nullable|string|max:500', 'media'],
                'sections' => ['ترتیب بخش‌ها (categories, products, campaigns, budget, guide)', 'nullable|string|max:160'],
            ],
            'product' => [
                'name' => ['نام', 'required|string|max:140'], 'description' => ['توضیحات', 'nullable|string|max:12000', 'richtext'],
                'category' => ['دسته', 'required|in:'.implode(',', array_keys(config('barin.categories'))), 'category'],
                'milligrams' => ['وزن به میلی‌گرم', 'required|integer|between:1,250000', 'number'],
                'labor' => ['اجرت (تومان)', 'required|integer|between:0,100000000000', 'number'],
                'cost' => ['بهای تمام‌شده یک قطعه (تومان)', 'required|integer|between:0,100000000000', 'number'],
                'profit_bps' => ['سود (۷۰۰ معادل ۷٪)', 'required|integer|between:0,10000', 'number'],
                'discount_bps' => ['تخفیف اجرت و سود (۷۰۰ معادل ۷٪)', 'required|integer|between:0,10000', 'number'],
                'tax_bps' => ['مالیات تنظیم‌شده بر اجرت و سود (۷۰۰ معادل ۷٪)', 'required|integer|between:0,10000', 'number'],
                'stock' => ['موجودی', 'required|integer|between:0,100000', 'number'],
                'media' => ['گالری محصول', 'sometimes|required|string|max:16000', 'gallery'],
                'published' => ['نمایش در ویترین فروشگاه', 'sometimes|required|boolean', 'checkbox'],
            ],
            'seo' => [
                'title' => ['عنوان سئو', 'required|string|max:180'], 'description' => ['توضیح متا', 'nullable|string|max:330', 'textarea'],
                'keyword' => ['کلیدواژه هدف', 'nullable|string|max:100'], 'canonical' => ['Canonical اختیاری', 'nullable|url:https|max:500'],
                'noindex' => ['جلوگیری از نمایش در نتایج جست‌وجو', 'nullable|boolean', 'checkbox'],
            ],
            'campaign' => [
                'name' => ['عنوان', 'required|string|max:140'], 'description' => ['پیام', 'nullable|string|max:500', 'richtext'],
                'image' => ['رسانهٔ بنر', 'nullable|string|max:500', 'media'], 'link' => ['مسیر داخلی دکمه', 'nullable|string|max:500'],
                'start' => ['شروع', 'required|date_format:Y-m-d', 'date'], 'end' => ['پایان', 'required|date_format:Y-m-d|after_or_equal:start', 'date'],
                'placement' => ['جایگاه', 'required|in:home,product,both', 'placement'],
                'product' => ['شناسه محصول هدف، خالی برای همه', 'nullable|alpha_dash:ascii|max:90'],
                'theme' => ['قالب', 'required|in:rose,black,default', 'theme'],
                'discount_bps' => ['تخفیف اجرت و سود (۷۰۰ معادل ۷٪)', 'required|integer|between:0,10000', 'number'],
            ],
            'settings' => [
                'rate_url' => ['آدرس API نرخ طلای ۱۸ عیار', 'nullable|url:https|max:800'], 'rate_path' => ['مسیر JSON مثل data.price', 'required_with:rate_url|nullable|string|max:160'],
                'rate_unit' => ['واحد پاسخ', 'required|in:rial,toman', 'unit'],
                'rate_token' => ['توکن نرخ (خالی = حفظ کلید)', 'nullable|string|max:1000', 'password'],
                'ai_url' => ['آدرس API سازگار با OpenAI Images Edits', 'nullable|url:https|max:800'],
                'ai_model' => ['مدل تولید تصویر', 'nullable|string|max:100'],
                'ai_token' => ['توکن استودیو (خالی = حفظ کلید)', 'nullable|string|max:1000', 'password'],
                'brand' => ['نام فروشگاه', 'nullable|string|max:100'], 'footer' => ['متن انتهای سایت', 'nullable|string|max:350', 'richtext'],
            ],
        ];
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $tab = $request->query('tab', 'dashboard');
        abort_unless(is_string($tab) && isset(self::TABS[$tab]), 404);
        $this->authorizeAdmin(self::ACCESS[$tab]);

        $data = match ($tab) {
            'dashboard' => $this->dashboard(),
            'finance' => $this->finance($request),
            'customers', 'users' => $this->directory($request, $tab === 'customers'),
            'gateways' => ['gatewayDefinitions' => Gateways::DEFINITIONS, 'gatewayRecords' => PaymentGateway::all()->keyBy('provider')],
            'studio' => [],
            'settings' => [
                'kind' => 'settings',
                'fields' => $this->fields()['settings'],
                'record' => $this->studio->settings() + ['rate_unit' => 'toman'],
                'formMode' => true,
                'rate' => Entry::getData('rate', 'latest'),
                'rateStatus' => Entry::getData('rate', 'scheduled'),
                'manualRateStatus' => Entry::getData('rate', 'manual'),
            ],
            default => $this->entryPanel($request, $tab),
        };

        $hasMedia = $tab === 'studio' || (!empty($data['formMode']) && in_array($tab, ['product', 'page', 'campaign'], true));

        return view('admin.index', $data + [
            'tab' => $tab,
            'tabs' => array_filter(self::TABS, fn ($key) => StaffAccess::allows(Auth::user(), self::ACCESS[$key]), ARRAY_FILTER_USE_KEY),
            'statuses' => Order::STATUSES,
            'transitions' => Order::TRANSITIONS,
            'corePages' => self::CORE_PAGES,
            'categories' => config('barin.categories'),
            'hasMedia' => $hasMedia,
            'presets' => $hasMedia ? $this->studio->presets() : [],
            'can' => collect(['content', 'orders', 'finance', 'fulfill', 'settings'])->mapWithKeys(
                fn ($permission) => [$permission => StaffAccess::allows(Auth::user(), $permission)]
            )->all(),
        ]);
    }

    private function dashboard(): array
    {
        $products = Entry::products()->published();
        $orders = Order::query();
        $user = Auth::user();
        $canContent = StaffAccess::allows($user, 'content');
        $canOrders = StaffAccess::allows($user, 'orders');
        $canFinance = StaffAccess::allows($user, 'finance');

        return [
            'metrics' => [
                'products' => $canContent ? (clone $products)->count() : null,
                'lowStock' => $canContent ? $this->filterStock(clone $products, 'low')->count() : null,
                'pending' => $canOrders ? (clone $orders)->where('status', 'pending')->count() : null,
                'sales' => $canFinance ? (clone $orders)->whereIn('status', ['paid', 'fulfilled'])->sum('total') : null,
            ],
            'rate' => Entry::getData('rate', 'latest'),
            'rateStatus' => Entry::getData('rate', 'scheduled'),
            'orders' => $canOrders ? $orders->latest('id')->limit(5)->get() : collect(),
        ];
    }

    private function entryPanel(Request $request, string $kind): array
    {
        $edit = $request->query('edit');
        $edit = is_string($edit) && $edit !== '' ? $edit : null;
        if ($edit !== null || $request->boolean('create')) {
            $newSlug = $request->query('slug');
            $newSlug = is_string($newSlug) ? $newSlug : '';
            $entries = Entry::where('kind', $kind);
            $entry = match (true) {
                $edit !== null => $entries->where('slug', $edit)->firstOrFail(),
                $kind === 'seo' && $newSlug !== '' => $entries->where('slug', $newSlug)->first(),
                default => null,
            };
            $defaults = match ($kind) {
                'product' => ['published' => true, 'stock' => 0, 'labor' => 0, 'cost' => 0, 'profit_bps' => 0, 'discount_bps' => 0, 'tax_bps' => 0],
                'campaign' => ['start' => today()->toDateString(), 'end' => today()->toDateString(), 'discount_bps' => 0],
                default => [],
            };
            return [
                'kind' => $kind,
                'entry' => $entry,
                'fields' => $this->fields()[$kind],
                'record' => ($entry?->data ?? []) + $defaults,
                'newSlug' => $newSlug,
                'formMode' => true,
            ] + ($kind === 'product' ? ['presets' => $this->studio->presets()] : []);
        }

        $search = $request->query('q');
        $filters = [];
        $query = Entry::where('kind', $kind);
        if (is_string($search) && trim($search) !== '') {
            $filters['q'] = trim($search);
            $query->matching($filters['q'], ['slug', $kind === 'seo' ? 'data->title' : 'data->name']);
        }
        if ($kind === 'product') {
            $category = $request->query('category');
            if (is_string($category) && array_key_exists($category, config('barin.categories'))) {
                $filters['category'] = $category;
                $query->where('data->category', $category);
            }
            $stock = $request->query('stock');
            if (in_array($stock, ['available', 'low', 'out'], true)) {
                $filters['stock'] = $stock;
                $this->filterStock($query, $stock);
            }
            $visibility = $request->query('visibility');
            if (in_array($visibility, ['published', 'hidden'], true)) {
                $filters['visibility'] = $visibility;
                $visibility === 'published' ? $query->published() : $query->where('data->published', false);
            }
        }
        $items = $query->latest('id')->paginate(20)->appends(['tab' => $kind] + $filters);
        $rate = $kind === 'product' ? Entry::getData('rate', 'latest') : [];
        $quotes = collect();
        if (! empty($rate['value'])) {
            $campaigns = $this->pricing->activeCampaigns();
            $quotes = $items->getCollection()->mapWithKeys(fn (Entry $item) => [
                $item->slug => $this->pricing->forProduct($item, $rate, $campaigns),
            ]);
        }

        return compact('kind', 'items', 'filters', 'rate', 'quotes') + ['formMode' => false];
    }

    private function filterStock(Builder $query, string $stock): Builder
    {
        // Older forms stored inventory as a JSON string.
        $column = $query->getQuery()->getGrammar()->wrap('data->stock');
        $quantity = "cast($column as decimal(12, 0))";

        return match ($stock) {
            'low' => $query->whereRaw("$quantity between 1 and 3"),
            'out' => $query->whereRaw("$quantity = 0"),
            default => $query->whereRaw("$quantity > 0"),
        };
    }

    private function orderQuery(Request $request): array
    {
        $filters = array_filter($request->validate([
            'q' => 'nullable|string|max:120',
            'status' => ['nullable', Rule::in(array_keys(Order::STATUSES))],
            'gateway' => ['nullable', Rule::in(['manual', ...array_keys(Gateways::DEFINITIONS)])],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''),
        ]), fn ($value) => $value !== null && $value !== '');
        $orders = Order::query();
        if (isset($filters['q'])) {
            $term = IranianIdentity::digits($filters['q']);
            $pattern = '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $orders->where(function (Builder $query) use ($pattern) {
                $grammar = $query->getQuery()->getGrammar();
                foreach (['reference', 'customer->name', 'customer->mobile', 'manual_reference'] as $field) {
                    $query->orWhereRaw($grammar->wrap($field)." like ? escape '!'", [$pattern]);
                }
                $query->orWhereHas('payments', fn (Builder $payments) => $payments->whereRaw("bank_reference like ? escape '!'", [$pattern]));
            });
        }
        foreach (['status', 'gateway'] as $field) {
            if (isset($filters[$field])) {
                $orders->where($field, $filters[$field]);
            }
        }
        if (isset($filters['from'])) {
            $orders->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (isset($filters['to'])) {
            $orders->where('created_at', '<=', $filters['to'].' 23:59:59');
        }

        return [$orders, $filters];
    }

    private function finance(Request $request): array
    {
        [$query, $filters] = $this->orderQuery($request);
        $metrics = [];
        $payments = null;
        if (StaffAccess::allows($request->user(), 'finance')) {
            $transactions = Payment::whereIn('order_id', (clone $query)->select('id'));
            $verified = (clone $transactions)->whereNotNull('confirmed_at');
            $metrics = [
                'total' => 0, 'count' => 0, 'margin' => 0, 'cost_count' => 0, 'cost' => 0,
                'discount' => 0, 'tax' => 0, 'labor' => 0, 'gold' => 0,
                'pending' => (clone $query)->where('status', 'pending')->sum('total'),
                'captured_rial' => (clone $verified)->sum('amount_rial'),
                'fee_rial' => (clone $verified)->sum('fee_rial'),
                'review_rial' => (clone $verified)->where('status', 'review')->sum('amount_rial'),
            ];
            $metrics['net_rial'] = $metrics['captured_rial'] - $metrics['fee_rial'];
            foreach ((clone $query)->whereIn('status', ['paid', 'fulfilled'])->select('id', 'total', 'lines')->lazyById() as $order) {
                $parts = $order->financials();
                $metrics['total'] += $order->total;
                $metrics['count']++;
                foreach (['tax', 'discount', 'gold', 'labor'] as $key) {
                    $metrics[$key] += $parts[$key];
                }
                if ($parts['margin'] !== null) {
                    $metrics['cost_count']++;
                    $metrics['margin'] += $parts['margin'];
                    $metrics['cost'] += $parts['cost'];
                }
            }
            $payments = $transactions->with('order:id,reference')->latest('id')->paginate(20, pageName: 'transactions')->appends(['tab' => 'finance'] + $filters);
        }
        $orders = $query->with('payments')->latest('id')->paginate(20)->appends(['tab' => 'finance'] + $filters);
        $events = $payments ? DB::table('order_events')->whereIn('order_id', $orders->pluck('id'))
            ->leftJoin('users', 'users.id', '=', 'order_events.user_id')
            ->select('order_events.*', 'users.name as actor')->orderByDesc('order_events.id')->get()->groupBy('order_id') : collect();

        return compact('orders', 'metrics', 'filters', 'payments', 'events') + ['gatewayDefinitions' => Gateways::DEFINITIONS];
    }

    private function directory(Request $request, bool $customers): array
    {
        $term = $request->validate(['q' => 'nullable|string|max:120'])['q'] ?? '';
        $users = User::query()->withCount('orders');
        if ($customers) {
            $users->whereNotNull('mobile');
        }
        if ($term !== '') {
            $pattern = '%'.strtr(IranianIdentity::digits($term), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $users->where(fn (Builder $query) => $query->whereRaw("name like ? escape '!'", [$pattern])
                ->orWhereRaw("mobile like ? escape '!'", [$pattern])->orWhereRaw("email like ? escape '!'", [$pattern]));
        }
        $members = $users->latest('id')->paginate(25)->appends(['tab' => $customers ? 'customers' : 'users', 'q' => $term]);

        return compact('members', 'term', 'customers');
    }

    public function save(Request $request, string $kind)
    {
        $this->authorizeAdmin($kind === 'settings' ? 'settings' : 'content');
        $fields = $this->fields()[$kind] ?? abort(404);
        $slugLimit = $kind === 'seo' ? 98 : 90;
        $rules = [
            'slug' => ($kind === 'product' ? 'nullable' : 'required').'|alpha_dash:ascii|max:'.$slugLimit,
            'edit' => 'nullable|alpha_dash:ascii|max:'.$slugLimit,
        ];
        if ($kind === 'product') {
            $rules['original_stock'] = 'required_with:edit|nullable|integer|min:0';
            $rules['images'] = 'required_without:media|nullable|string|max:6000';
            $rules['image_alts'] = 'nullable|string|max:1800';
        }
        $mediaFields = array_keys(array_filter($fields, fn ($field) => ($field[2] ?? '') === 'media'));
        foreach ($mediaFields as $name) {
            $rules[$name.'_alt'] = 'nullable|string|max:180';
        }
        $richRules = array_map(fn ($field) => $field[1], array_filter($fields, fn ($field) => ($field[2] ?? '') === 'richtext'));
        foreach (array_keys($richRules) as $name) {
            $rules[$name] = 'nullable|string|max:60000';
            $rules[$name.'_html'] = 'sometimes|boolean';
        }
        $labels = ['slug' => 'شناسه', ...array_map(fn ($field) => $field[0], $fields)];
        $validated = $request->validate($rules + array_map(fn ($field) => $field[1], $fields), [], $labels);
        $text = [];
        foreach (array_keys($richRules) as $name) {
            $flag = $name.'_html';
            if (!array_key_exists($name, $validated)) {
                unset($validated[$flag]);
                continue;
            }

            $validated[$flag] = (bool) ($validated[$flag] ?? false);
            if ($validated[$flag]) {
                $validated[$name] = RichText::sanitize($validated[$name] ?? '');
            }
            $text[$name] = RichText::text($validated, $name);
            if ($text[$name] === '' && !preg_match('/<(?:img|video)\s/', $validated[$name] ?? '')) {
                $validated[$name] = null;
            }
        }
        Validator::make($text, $richRules, [], $labels)->validate();
        $edit = $validated['edit'] ?? null;
        $slug = $kind === 'settings' ? 'store' : ($validated['slug'] ?? $edit);
        if ($kind === 'product' && $slug === null) {
            $stem = Str::slug($validated['name']);
            $slug = rtrim(substr($stem, 0, 63), '-') ?: $validated['category'];
            $slug .= '-'.strtolower((string) Str::ulid());
        }
        $originalStock = (int) ($validated['original_stock'] ?? 0);
        unset($validated['slug'], $validated['edit'], $validated['original_stock']);

        $legacyGallery = $kind === 'product' && !isset($validated['media']);
        if ($kind === 'product') {
            foreach (['milligrams', 'labor', 'cost', 'profit_bps', 'discount_bps', 'tax_bps', 'stock'] as $field) {
                $validated[$field] = (int) $validated[$field];
            }
            $validated = array_replace($validated, Media::productFields($validated));
            if (array_key_exists('published', $validated)) {
                $validated['published'] = (bool) $validated['published'];
            }
        }
        foreach ($mediaFields as $field) {
            $path = $validated[$field] ?? null;
            if ($path && !Media::localPath($path)) {
                throw ValidationException::withMessages([$field => 'یک فایل از کتابخانه رسانه انتخاب کنید.']);
            }
        }
        if (!empty($validated['link']) && !Media::localPath($validated['link'])) {
            throw ValidationException::withMessages(['link' => 'مسیر داخلی دکمه را وارد کنید.']);
        }

        try {
            DB::transaction(function () use ($kind, $slug, $edit, $originalStock, $validated, $legacyGallery) {
                if ($kind === 'settings') {
                    $settings = Entry::where(compact('kind', 'slug'))->lockForUpdate()->first();
                    $data = $settings?->data ?? [];
                    foreach (['ai_token', 'rate_token'] as $field) {
                        $token = $validated[$field] ?? '';
                        $validated[$field] = $token !== '' ? Crypt::encryptString($token) : ($data[$field] ?? null);
                    }
                    Entry::putData($kind, $slug, array_replace($data, $validated));

                    return;
                }
                if ($edit === null) {
                    Entry::create(compact('kind', 'slug') + ['data' => $validated]);

                    return;
                }

                abort_unless($slug === $edit, 422);
                $entry = Entry::where(compact('kind', 'slug'))->lockForUpdate()->firstOrFail();
                $data = $entry->data;
                if ($legacyGallery && isset($data['media'])) {
                    foreach ($data['media'] as $item) {
                        if (Media::type($item['path']) === 'video') {
                            $validated['media'][] = $item;
                        }
                    }
                    if (count($validated['media']) > Media::LIMIT) {
                        throw ValidationException::withMessages(['images' => 'گالری محصول حداکثر ۱۶ عکس و ویدئو دارد.']);
                    }
                }
                if ($kind === 'product' && (int) $data['stock'] !== $originalStock) {
                    if ($validated['stock'] !== $originalStock) {
                        throw ValidationException::withMessages([
                            'stock' => 'موجودی هم‌زمان تغییر کرده است. صفحه را دوباره باز کنید و موجودی تازه را بررسی کنید.',
                        ]);
                    }
                    $validated['stock'] = $data['stock'];
                }
                $entry->update(['data' => array_replace($data, $validated)]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'این شناسه قبلاً ثبت شده است. برای تغییر آن، باز کردن رکورد از فهرست لازم است.']);
        }

        return redirect()->route('admin.index', ['tab' => $kind, 'edit' => $kind === 'settings' ? null : $slug])
            ->with('notice', 'تغییرات ذخیره شد.');
    }

    public function visibility(Request $request, string $slug)
    {
        $this->authorizeAdmin('content');
        $published = (bool) $request->validate(['published' => 'required|boolean'])['published'];
        DB::transaction(function () use ($slug, $published) {
            $product = Entry::products()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $product->update(['data' => array_replace($product->data, compact('published'))]);
        }, 3);

        return back()->with('notice', $published ? 'محصول در ویترین منتشر شد.' : 'نمایش محصول در ویترین متوقف شد.');
    }

    public function destroy(string $kind, string $slug)
    {
        $this->authorizeAdmin('content');
        abort_unless(in_array($kind, ['page', 'campaign', 'seo'], true), 404);
        abort_if($kind === 'page' && in_array($slug, self::CORE_PAGES, true), 422);
        Entry::where(compact('kind', 'slug'))->firstOrFail()->delete();

        return redirect()->route('admin.index', ['tab' => $kind])->with('notice', 'رکورد حذف شد.');
    }

    public function refresh(RateUpdater $updater)
    {
        $this->authorizeAdmin('settings');
        $status = $updater->run('manual');

        return $status['state'] === 'updated' ? back()->with('notice', $status['message'])
            : back()->withErrors(['rate' => $status['message']]);
    }

    public function generate(Request $request)
    {
        $this->authorizeAdmin('content');
        $data = $request->validate(['preset' => 'required|integer', 'image' => ImageStudio::IMAGE_RULE]);
        $path = $this->studio->generate($data['image'], (int) $data['preset']);
        if ($request->expectsJson()) {
            $file = basename($path);

            return response()->json([
                'path' => route('admin.images.show', $file, false),
                'publish' => route('admin.images.publish', $file, false),
            ]);
        }

        return back()->with('generated', $path);
    }

    public function studioImage(string $file)
    {
        $this->authorizeAdmin('content');
        return Storage::disk('local')->response($this->studioPath($file));
    }

    public function publishImage(Request $request, string $file)
    {
        $this->authorizeAdmin('content');
        $stream = Storage::disk('local')->readStream($this->studioPath($file));
        abort_unless(is_resource($stream), 404);
        $path = 'catalog/'.$file;
        try {
            abort_unless(Storage::disk('public')->put($path, $stream), 500);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $url = '/storage/'.$path;

        return $request->expectsJson()
            ? response()->json(['path' => $url])
            : back()->with('generated', 'studio/'.$file)->with('upload', $url)->with('notice', 'تصویر در گالری ذخیره شد.');
    }

    private function studioPath(string $file): string
    {
        $path = 'studio/'.$file;
        abort_unless(preg_match('/^[0-9a-f-]{36}\.(png|jpg|webp)$/D', $file) && Storage::disk('local')->exists($path), 404);

        return $path;
    }

    public function upload(Request $request)
    {
        $this->authorizeAdmin('content');
        $video = $request->input('kind') === 'video';
        $field = $video ? 'file' : 'image';
        $data = $request->validate([
            'kind' => 'sometimes|required|in:image,video',
            $field => $video ? Media::VIDEO_RULE : ImageStudio::IMAGE_RULE,
            'preset' => $video ? 'prohibited' : 'sometimes|required|integer',
        ], [
            'file.mimetypes' => 'ویدئو باید MP4 یا WebM باشد.',
            'file.max' => 'حجم ویدئو باید حداکثر ۳۲ مگابایت باشد.',
            'image.max' => 'حجم عکس باید حداکثر ۸ مگابایت باشد.',
        ]);
        $generated = isset($data['preset']);
        $file = $data[$field];
        if ($generated) {
            $path = $this->studio->generate($file, (int) $data['preset'], forCatalog: true);
        } else {
            $label = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $label = rtrim(substr($label, 0, 60), '-') ?: ($video ? 'video' : 'photo');
            $path = $file->storeAs('catalog', $label.'-'.strtolower((string) Str::ulid()).'.'.$file->extension(), 'public');
        }
        abort_unless($path, 500);
        $url = '/storage/'.$path;

        return $request->expectsJson()
            ? response()->json(['path' => $url])
            : back()->with('upload', $url)->with('notice', $generated ? 'تصویر استودیو در کتابخانه ذخیره شد.' : 'فایل بارگذاری شد.');
    }

    public function media(Request $request)
    {
        $this->authorizeAdmin('content');
        $filters = $request->validate(['q' => 'nullable|string|max:120', 'type' => 'nullable|in:image,video', 'page' => 'nullable|integer|min:1|max:10000']);
        $disk = Storage::disk('public');
        $files = [];
        foreach ($disk->files('catalog') as $file) {
            if (Media::type('/storage/'.$file)) {
                $files[$file] = $disk->lastModified($file);
            }
        }
        arsort($files);
        $paths = array_merge(
            array_map(fn ($file) => '/storage/'.$file, array_keys($files)),
            array_map(fn ($file) => '/assets/'.basename($file), glob(public_path('assets/*'))),
        );
        $items = [];
        foreach ($paths as $path) {
            $type = Media::type($path);
            $label = preg_replace('/-[0-9a-hjkmnp-tv-z]{26}$/', '', pathinfo($path, PATHINFO_FILENAME));
            if ($type && (!isset($filters['type']) || $filters['type'] === $type)
                && mb_stripos(basename($path), $filters['q'] ?? '') !== false) {
                $items[] = compact('path', 'type', 'label');
            }
        }
        $page = (int) ($filters['page'] ?? 1);
        $size = 20;

        return response()->json([
            'items' => array_slice($items, ($page - 1) * $size, $size),
            'next' => count($items) > $page * $size ? $page + 1 : null,
        ]);
    }

    public function status(Request $request, int $id, OrderPayments $payments)
    {
        $data = $request->validate(['status' => 'required|in:paid,fulfilled,cancelled', 'manual_reference' => 'nullable|string|max:120']);
        $this->authorizeAdmin($data['status'] === 'fulfilled' ? 'fulfill' : 'finance');
        $payments->changeStatus(Order::findOrFail($id), $data['status'], $request->user()->id, $data['manual_reference'] ?? null);

        return back()->with('notice', 'وضعیت سفارش ذخیره شد.');
    }

    public function verifyPayment(Payment $payment, OrderPayments $payments)
    {
        $this->authorizeAdmin('finance');
        abort_unless($payment->status === 'verify_pending', 422);
        $payments->verify($payment);

        return back()->with('notice', $payment->fresh()->confirmed_at ? 'نتیجه بانک ثبت شد.' : 'پرداخت همچنان نیازمند بررسی است.');
    }

    public function saveGateway(Request $request, string $provider)
    {
        $this->authorizeAdmin('gateways');
        $definition = Gateways::DEFINITIONS[$provider] ?? abort(404);
        $fields = array_keys($definition['fields']);
        $rules = [];
        foreach ($fields as $field) {
            $rules['credentials.'.$field] = ['nullable', 'string', 'max:255', 'not_regex:/[[:cntrl:]]/'];
        }
        $data = $request->validate(['enabled' => 'required|boolean', 'credentials' => ['sometimes', 'array:'.implode(',', $fields)]] + $rules);

        DB::transaction(function () use ($provider, $definition, $data, $fields) {
            $gateway = PaymentGateway::lockForUpdate()->find($provider) ?? new PaymentGateway(['provider' => $provider]);
            $credentials = array_replace($gateway->credentials ?? [], array_filter($data['credentials'] ?? [], fn ($value) => filled($value)));
            $checks = [];
            foreach ($fields as $field) {
                $checks[$field] = [$data['enabled'] ? 'required' : 'nullable', $definition['rules'][$field] ?? 'string'];
            }
            Validator::make($credentials, $checks, [], $definition['fields'])->validate();
            if ($provider === 'melli' && isset($credentials['key']) && strlen((string) base64_decode($credentials['key'], true)) !== 24) {
                throw ValidationException::withMessages(['credentials.key' => 'کلید ترمینال سداد باید Base64 متناظر با ۲۴ بایت باشد.']);
            }
            $gateway->fill(['enabled' => $data['enabled'], 'credentials' => $credentials])->save();
        }, 3);
        Log::notice('Payment gateway settings updated', ['provider' => $provider, 'user_id' => $request->user()->id, 'enabled' => (bool) $data['enabled']]);

        return redirect()->route('admin.index', ['tab' => 'gateways'])->with('notice', 'تنظیمات درگاه ذخیره شد.');
    }

    public function role(Request $request, User $user)
    {
        $this->authorizeAdmin('users');
        abort_if(StaffAccess::owner($user), 422);
        $role = $request->validate(['role' => ['required', Rule::in(array_keys(StaffAccess::ROLES))]])['role'];
        $previous = $user->role;
        $user->forceFill(['role' => $role])->save();
        Log::notice('User role updated', ['actor_id' => $request->user()->id, 'user_id' => $user->id, 'from' => $previous, 'to' => $role]);

        return back()->with('notice', 'نقش کاربر ذخیره شد.');
    }

    public function export(Request $request)
    {
        $this->authorizeAdmin('finance');
        [$orders] = $this->orderQuery($request);

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['reference', 'status', 'gateway', 'total_toman', 'gold', 'labor', 'profit', 'discount', 'tax', 'cost', 'margin', 'merchant_fee_rial', 'created_at', 'paid_at'], ',', '"', '');
            foreach ($orders->withSum('payments', 'fee_rial')->lazyById(500) as $order) {
                fputcsv($out, [
                    $order->reference, $order->status, $order->gateway, $order->total,
                    ...array_values($order->financials()), $order->payments_sum_fee_rial ?? 0,
                    $order->created_at, $order->paid_at,
                ], ',', '"', '');
            }
            fclose($out);
        }, 'barin-orders.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
