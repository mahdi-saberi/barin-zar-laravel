<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Services\Integrations;
use App\Services\Pricing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Admin extends Controller
{
    private const TABS = [
        'dashboard' => 'داشبورد',
        'product' => 'محصولات و موجودی',
        'finance' => 'سفارش‌ها و مالی',
        'page' => 'صفحه‌ها',
        'campaign' => 'بنرها و کمپین‌ها',
        'seo' => 'سئو',
        'studio' => 'استودیوی تصویر',
        'settings' => 'تنظیمات فروشگاه',
    ];

    private const STATUSES = [
        'pending' => 'منتظر تأیید',
        'paid' => 'پرداخت تأییدشده',
        'fulfilled' => 'تحویل‌شده',
        'cancelled' => 'لغوشده',
    ];

    private const TRANSITIONS = ['pending' => ['paid', 'cancelled'], 'paid' => ['fulfilled']];

    private const CORE_PAGES = ['home', 'catalog', 'guide'];

    public function __construct(private readonly Pricing $pricing) {}

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::check() && Auth::user()->email === config('barin.admin_email'), 403);
    }

    public function loginForm()
    {
        if (Auth::check() && Auth::user()->email === config('barin.admin_email')) {
            return redirect()->route('admin.index');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if ($data['email'] !== config('barin.admin_email') || !Auth::attempt($data)) {
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
                'name' => ['عنوان', 'required|string|max:140'], 'description' => ['متن', 'nullable|string|max:12000', 'textarea'],
                'hero' => ['آدرس تصویر', 'nullable|string|max:500'], 'badge' => ['بالانویس', 'nullable|string|max:120'],
                'button' => ['متن دکمه اصلی', 'nullable|string|max:80'],
                'products_heading' => ['عنوان مجموعه‌های طلا', 'nullable|string|max:100'],
                'budget_heading' => ['عنوان بخش بودجه', 'nullable|string|max:100'],
                'budget_low' => ['بودجه اول (تومان)', 'nullable|integer|between:1,100000000000', 'number'],
                'budget_high' => ['بودجه دوم (تومان)', 'nullable|integer|between:1,100000000000', 'number'],
                'guide_heading' => ['عنوان بخش راهنما', 'nullable|string|max:100'],
                'guide_text' => ['متن بخش راهنما', 'nullable|string|max:500'],
                'guide_image' => ['تصویر بخش راهنما', 'nullable|string|max:500'],
                'sections' => ['ترتیب بخش‌ها (categories, products, campaigns, budget, guide)', 'nullable|string|max:160'],
            ],
            'product' => [
                'name' => ['نام', 'required|string|max:140'], 'description' => ['توضیحات', 'nullable|string|max:12000', 'textarea'],
                'category' => ['دسته', 'required|in:'.implode(',', array_keys(config('barin.categories'))), 'category'],
                'milligrams' => ['وزن به میلی‌گرم', 'required|integer|between:1,250000', 'number'],
                'labor' => ['اجرت (تومان)', 'required|integer|between:0,100000000000', 'number'],
                'cost' => ['بهای تمام‌شده یک قطعه (تومان)', 'required|integer|between:0,100000000000', 'number'],
                'profit_bps' => ['سود (۷۰۰ معادل ۷٪)', 'required|integer|between:0,10000', 'number'],
                'discount_bps' => ['تخفیف اجرت و سود (۷۰۰ معادل ۷٪)', 'required|integer|between:0,10000', 'number'],
                'tax_bps' => ['مالیات تنظیم‌شده بر اجرت و سود (۷۰۰ معادل ۷٪)', 'required|integer|between:0,10000', 'number'],
                'stock' => ['موجودی', 'required|integer|between:0,100000', 'number'],
                'images' => ['آدرس تصاویر، هر سطر یک عکس و عکس اول بندانگشتی', 'required|string|max:6000', 'textarea'],
                'image_alts' => ['متن جایگزین هر عکس، سطر به سطر', 'nullable|string|max:1800', 'textarea'],
                'published' => ['نمایش در ویترین فروشگاه', 'sometimes|required|boolean', 'checkbox'],
            ],
            'seo' => [
                'title' => ['عنوان سئو', 'required|string|max:180'], 'description' => ['توضیح متا', 'nullable|string|max:330', 'textarea'],
                'keyword' => ['کلیدواژه هدف', 'nullable|string|max:100'], 'canonical' => ['Canonical اختیاری', 'nullable|url:https|max:500'],
                'noindex' => ['جلوگیری از نمایش در نتایج جست‌وجو', 'nullable|boolean', 'checkbox'],
            ],
            'campaign' => [
                'name' => ['عنوان', 'required|string|max:140'], 'description' => ['پیام', 'nullable|string|max:500'],
                'image' => ['آدرس بنر', 'nullable|string|max:500'], 'link' => ['مسیر داخلی دکمه', 'nullable|string|max:500'],
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
                'brand' => ['نام فروشگاه', 'nullable|string|max:100'], 'footer' => ['متن انتهای سایت', 'nullable|string|max:350'],
            ],
        ];
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $tab = $request->query('tab', 'dashboard');
        abort_unless(is_string($tab) && isset(self::TABS[$tab]), 404);

        $data = match ($tab) {
            'dashboard' => $this->dashboard(),
            'finance' => $this->finance($request),
            'studio' => ['presets' => $this->presets()],
            'settings' => [
                'kind' => 'settings',
                'fields' => $this->fields()['settings'],
                'record' => Entry::getData('settings', 'store') + ['rate_unit' => 'toman', 'ai_model' => 'gpt-image-1'],
                'formMode' => true,
                'rate' => Entry::getData('rate', 'latest'),
            ],
            default => $this->entryPanel($request, $tab),
        };

        return view('admin.index', $data + [
            'tab' => $tab,
            'tabs' => self::TABS,
            'statuses' => self::STATUSES,
            'transitions' => self::TRANSITIONS,
            'corePages' => self::CORE_PAGES,
            'categories' => config('barin.categories'),
        ]);
    }

    private function dashboard(): array
    {
        $products = Entry::products()->published();
        $orders = DB::table('orders');

        return [
            'metrics' => [
                'products' => (clone $products)->count(),
                'lowStock' => $this->filterStock(clone $products, 'low')->count(),
                'pending' => (clone $orders)->where('status', 'pending')->count(),
                'sales' => (clone $orders)->whereIn('status', ['paid', 'fulfilled'])->sum('total'),
            ],
            'rate' => Entry::getData('rate', 'latest'),
            'orders' => $orders->latest('id')->limit(5)->get(),
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
            ];
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
            'status' => 'nullable|in:'.implode(',', array_keys(self::STATUSES)),
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''),
        ]), fn ($value) => $value !== null && $value !== '');
        $orders = DB::table('orders');
        if (isset($filters['q'])) {
            $pattern = '%'.strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $orders->where(function (QueryBuilder $query) use ($pattern) {
                foreach (['reference', 'customer->name', 'customer->mobile'] as $field) {
                    $column = $query->getGrammar()->wrap($field);
                    $query->orWhereRaw("$column like ? escape '!'", [$pattern]);
                }
            });
        }
        if (isset($filters['status'])) {
            $orders->where('status', $filters['status']);
        }
        if (isset($filters['from'])) {
            $orders->whereDate('created_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $orders->whereDate('created_at', '<=', $filters['to']);
        }

        return [$orders, $filters];
    }

    private function finance(Request $request): array
    {
        [$query, $filters] = $this->orderQuery($request);
        $paid = (clone $query)->whereIn('status', ['paid', 'fulfilled']);
        $metrics = ['total' => $paid->sum('total'), 'count' => $paid->count(), 'margin' => 0, 'discount' => 0];
        foreach ($paid->select('lines')->cursor() as $order) {
            foreach (json_decode($order->lines, true) as $line) {
                $metrics['margin'] += $line['total'] - $line['tax'] - $line['cost'];
                $metrics['discount'] += $line['discount'];
            }
        }
        $orders = $query->latest('id')->paginate(20)->appends(['tab' => 'finance'] + $filters);

        return compact('orders', 'metrics', 'filters');
    }

    public function save(Request $request, string $kind)
    {
        $this->authorizeAdmin();
        $fields = $this->fields()[$kind] ?? abort(404);
        $slugLimit = $kind === 'seo' ? 98 : 90;
        $rules = ['slug' => 'required|alpha_dash:ascii|max:'.$slugLimit, 'edit' => 'nullable|alpha_dash:ascii|max:'.$slugLimit];
        if ($kind === 'product') {
            $rules['original_stock'] = 'required_with:edit|nullable|integer|min:0';
        }
        $validated = $request->validate($rules + array_map(fn ($field) => $field[1], $fields), [],
            ['slug' => 'شناسه', ...array_map(fn ($field) => $field[0], $fields)]);
        $slug = $kind === 'settings' ? 'store' : $validated['slug'];
        $edit = $validated['edit'] ?? null;
        $originalStock = (int) ($validated['original_stock'] ?? 0);
        unset($validated['slug'], $validated['edit'], $validated['original_stock']);

        if ($kind === 'product') {
            foreach (['milligrams', 'labor', 'cost', 'profit_bps', 'discount_bps', 'tax_bps', 'stock'] as $field) {
                $validated[$field] = (int) $validated[$field];
            }
            // Keep each description aligned with its image when a blank line is skipped.
            $images = array_map('trim', preg_split('/\r\n|\r|\n/', $validated['images']));
            $alts = preg_split('/\r\n|\r|\n/', $validated['image_alts'] ?? '');
            $images = array_filter($images, fn (string $path) => $path !== '');
            if (count($images) < 1 || count($images) > 16) {
                throw ValidationException::withMessages(['images' => 'یک تا ۱۶ آدرس تصویر وارد کنید.']);
            }
            $validated['images'] = array_values($images);
            $validated['image_alts'] = array_map(fn (int $i) => trim($alts[$i] ?? ''), array_keys($images));
            if (array_key_exists('published', $validated)) {
                $validated['published'] = (bool) $validated['published'];
            }
        }
        foreach (['images', 'hero', 'guide_image', 'image', 'link'] as $field) {
            foreach ((array) ($validated[$field] ?? []) as $path) {
                if ($path && (! str_starts_with($path, '/') || preg_match('~^//|[\\\\\s<>]~', $path))) {
                    throw ValidationException::withMessages([$field => 'یک مسیر داخلی مانند /assets/example.webp وارد کنید.']);
                }
            }
        }

        try {
            DB::transaction(function () use ($kind, $slug, $edit, $originalStock, $validated) {
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
        $this->authorizeAdmin();
        $published = (bool) $request->validate(['published' => 'required|boolean'])['published'];
        DB::transaction(function () use ($slug, $published) {
            $product = Entry::products()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $product->update(['data' => array_replace($product->data, compact('published'))]);
        }, 3);

        return back()->with('notice', $published ? 'محصول در ویترین منتشر شد.' : 'نمایش محصول در ویترین متوقف شد.');
    }

    public function destroy(string $kind, string $slug)
    {
        $this->authorizeAdmin();
        abort_unless(in_array($kind, ['page', 'campaign', 'seo'], true), 404);
        abort_if($kind === 'page' && in_array($slug, self::CORE_PAGES, true), 422);
        Entry::where(compact('kind', 'slug'))->firstOrFail()->delete();

        return redirect()->route('admin.index', ['tab' => $kind])->with('notice', 'رکورد حذف شد.');
    }

    private function presets(): array
    {
        return json_decode(file_get_contents(public_path('assets/presets.json')), true);
    }

    public function refresh(Integrations $service)
    {
        $this->authorizeAdmin();
        try {
            $service->refresh();
        } catch (HttpClientException) {
            return back()->withErrors(['rate' => 'دریافت نرخ ناموفق بود. آخرین نرخ معتبر حفظ شد.']);
        }
        return back()->with('notice', 'نرخ از API به‌روز شد.');
    }

    public function generate(Request $request, Integrations $service)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['preset' => 'required|integer', 'image' => 'required|image|mimes:png,jpg,jpeg,webp|max:8192']);
        $preset = collect($this->presets())->firstWhere('id', $data['preset']);
        abort_unless($preset, 422);
        $settings = Entry::getData('settings', 'store');
        $image = $data['image'];
        try {
            $response = $service->endpoint($settings, 'ai')->attach('image', fopen($image->path(), 'rb'), 'product.'.$image->extension())
                ->post($settings['ai_url'], ['model' => ($settings['ai_model'] ?? '') ?: 'gpt-image-1', 'prompt' => $preset['prompt'], 'size' => '1024x1024'])
                ->throw()->json('data.0.b64_json');
        } catch (HttpClientException) {
            return back()->withErrors(['image' => 'سرویس تصویر پاسخ موفق نداد. تنظیمات و اعتبار سرویس را بررسی کنید.']);
        }
        $bytes = is_string($response) && strlen($response) < 24000000 ? base64_decode($response, true) : false;
        $mime = $bytes ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : '';
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            return back()->withErrors(['image' => 'خروجی تصویری قابل ذخیره دریافت نشد.']);
        }
        $path = 'studio/'.Str::uuid().'.'.$extensions[$mime];
        abort_unless(Storage::disk('local')->put($path, $bytes), 500);
        return back()->with('generated', $path);
    }

    public function studioImage(string $file)
    {
        $this->authorizeAdmin();
        return Storage::disk('local')->response($this->studioPath($file));
    }

    public function publishImage(string $file)
    {
        $this->authorizeAdmin();
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

        return back()->with('generated', 'studio/'.$file)->with('upload', '/storage/'.$path)->with('notice', 'تصویر در گالری ذخیره شد.');
    }

    private function studioPath(string $file): string
    {
        $path = 'studio/'.$file;
        abort_unless(preg_match('/^[0-9a-f-]{36}\.(png|jpg|webp)$/D', $file) && Storage::disk('local')->exists($path), 404);

        return $path;
    }

    public function upload(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['image' => 'required|image|mimes:png,jpg,jpeg,webp|max:8192']);
        $path = $data['image']->store('catalog', 'public');
        abort_unless($path, 500);
        $url = '/storage/'.$path;

        return $request->expectsJson()
            ? response()->json(['path' => $url])
            : back()->with('upload', $url)->with('notice', 'تصویر بارگذاری شد.');
    }

    public function status(Request $request, int $id)
    {
        $this->authorizeAdmin();
        $status = $request->validate(['status' => 'required|in:paid,fulfilled,cancelled'])['status'];
        DB::transaction(function () use ($id, $status) {
            $order = DB::table('orders')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            if (! in_array($status, self::TRANSITIONS[$order->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'این تغییر وضعیت برای سفارش مجاز نیست. فهرست سفارش‌ها را دوباره باز کنید.']);
            }
            if ($status === 'cancelled') {
                foreach (json_decode($order->lines, true) as $line) {
                    $product = Entry::products()->where('slug', $line['slug'])->lockForUpdate()->first();
                    if ($product) {
                        $data = $product->data;
                        $data['stock']++;
                        $product->update(['data' => $data]);
                    }
                }
            }
            DB::table('orders')->where('id', $id)->update(compact('status'));
        }, 3);
        return back()->with('notice', 'وضعیت سفارش ذخیره شد.');
    }

    public function export(Request $request)
    {
        $this->authorizeAdmin();
        [$orders] = $this->orderQuery($request);

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['reference', 'status', 'total_toman', 'discount', 'tax', 'cost', 'created_at'], ',', '"', '');
            foreach ($orders->orderBy('id')->cursor() as $order) {
                $lines = json_decode($order->lines, true);
                fputcsv($out, [$order->reference, $order->status, $order->total, array_sum(array_column($lines, 'discount')), array_sum(array_column($lines, 'tax')), array_sum(array_column($lines, 'cost')), $order->created_at], ',', '"', '');
            }
            fclose($out);
        }, 'barin-orders.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
