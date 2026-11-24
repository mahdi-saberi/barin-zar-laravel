<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Services\Integrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Admin extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless(Auth::check() && Auth::user()->email === config('barin.admin_email'), 403);
    }

    public function loginForm()
    {
        return view('admin', ['login' => true]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if ($data['email'] !== config('barin.admin_email') || !Auth::attempt($data)) {
            return back()->withErrors(['email' => 'ورود ناموفق بود.']);
        }
        $request->session()->regenerate();
        return redirect('/admin');
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
                'products_heading' => ['عنوان محصولات منتخب', 'nullable|string|max:100'],
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
            ],
            'seo' => [
                'title' => ['عنوان سئو', 'required|string|max:180'], 'description' => ['توضیح متا', 'nullable|string|max:330', 'textarea'],
                'keyword' => ['کلیدواژه هدف', 'nullable|string|max:100'], 'canonical' => ['Canonical اختیاری', 'nullable|url:https|max:500'],
                'noindex' => ['جلوگیری از ایندکس (۱ یا ۰)', 'nullable|in:0,1', 'number'],
            ],
            'campaign' => [
                'name' => ['عنوان', 'required|string|max:140'], 'description' => ['پیام', 'nullable|string|max:500'],
                'image' => ['آدرس بنر', 'nullable|string|max:500'], 'link' => ['مسیر داخلی دکمه', 'nullable|string|max:500'],
                'start' => ['شروع', 'required|date_format:Y-m-d', 'date'], 'end' => ['پایان', 'required|date_format:Y-m-d|after_or_equal:start', 'date'],
                'placement' => ['جایگاه', 'required|in:home,product,both', 'placement'],
                'product' => ['شناسه محصول هدف، خالی برای همه', 'nullable|alpha_dash:ascii|max:90'],
                'theme' => ['قالب (rose, black, default)', 'required|in:rose,black,default'],
                'discount_bps' => ['تخفیف اجرت و سود (۷۰۰ معادل ۷٪)', 'required|integer|between:0,10000', 'number'],
            ],
            'settings' => [
                'rate_url' => ['آدرس API نرخ طلای ۱۸ عیار', 'nullable|url:https|max:800'], 'rate_path' => ['مسیر JSON مثل data.price', 'required_with:rate_url|nullable|string|max:160'],
                'rate_unit' => ['واحد پاسخ (rial / toman)', 'required|in:rial,toman'],
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
        $forms = $this->fields();
        $tab = $request->string('tab', 'finance')->toString();
        $kind = array_key_exists($tab, $forms) ? $tab : null;
        $items = $kind ? Entry::where('kind', $kind)->orderBy('slug')->get() : collect();
        $slug = $kind === 'settings' ? 'store' : $request->string('edit')->toString();
        $record = $kind && $slug ? Entry::getData($kind, $slug) : [];
        if ($kind === 'settings') {
            $record += ['rate_unit' => 'toman', 'ai_model' => 'gpt-image-1'];
        }
        $orders = $tab === 'finance' ? DB::table('orders')->latest('id')->paginate(20) : null;
        $paid = $orders ? DB::table('orders')->whereIn('status', ['paid', 'fulfilled']) : null;
        $metrics = $paid ? ['total' => $paid->sum('total'), 'count' => $paid->count(), 'margin' => 0, 'discount' => 0] : [];
        if ($paid) {
            foreach ($paid->select('lines')->cursor() as $order) {
                foreach (json_decode($order->lines, true) as $line) {
                    $metrics['margin'] += $line['total'] - $line['tax'] - $line['cost'];
                    $metrics['discount'] += $line['discount'];
                }
            }
        }
        return view('admin', compact('forms', 'tab', 'kind', 'items', 'slug', 'record', 'orders', 'metrics') + [
            'presets' => $this->presets(), 'rate' => Entry::getData('rate', 'latest'),
        ]);
    }

    public function save(Request $request, string $kind)
    {
        $this->authorizeAdmin();
        $fields = $this->fields()[$kind] ?? abort(404);
        $validated = $request->validate(['slug' => 'required|alpha_dash:ascii|max:90'] + array_map(fn ($field) => $field[1], $fields));
        $slug = $kind === 'settings' ? 'store' : $validated['slug'];
        unset($validated['slug']);
        foreach (['images', 'image_alts'] as $key) {
            if (array_key_exists($key, $validated)) {
                $validated[$key] = array_map('trim', preg_split('/\R/', $validated[$key] ?? ''));
                if ($key === 'images' && (!$validated[$key][0] || count($validated[$key]) > 16)) {
                    return back()->withInput()->withErrors(['images' => 'یک تا ۱۶ آدرس تصویر وارد کنید. سطر اول خالی نباشد.']);
                }
            }
        }
        foreach ([...($validated['images'] ?? []), $validated['hero'] ?? null, $validated['guide_image'] ?? null, $validated['image'] ?? null, $validated['link'] ?? null] as $path) {
            if ($path && (!str_starts_with($path, '/') || preg_match('~^//|[\\\\\s<>]~', $path))) {
                return back()->withInput()->withErrors(['path' => 'برای بنر، تصاویر و لینک، مسیر داخلی مانند /assets/example.webp وارد کنید.']);
            }
        }
        if ($kind === 'settings') {
            $prior = Entry::getData($kind, $slug);
            foreach (['ai_token', 'rate_token'] as $field) {
                $validated[$field] = empty($validated[$field]) ? ($prior[$field] ?? null) : Crypt::encryptString($validated[$field]);
            }
        }
        DB::transaction(function () use ($kind, $slug, $validated) {
            Entry::where(compact('kind', 'slug'))->lockForUpdate()->first();
            Entry::putData($kind, $slug, $validated);
        }, 3);
        return redirect('/admin?'.http_build_query(['tab' => $kind, 'edit' => $slug]))->with('notice', 'ذخیره شد.');
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
        } catch (\Illuminate\Http\Client\HttpClientException $e) {
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
                ->post($settings['ai_url'], ['model' => $settings['ai_model'] ?: 'gpt-image-1', 'prompt' => $preset['prompt'], 'size' => '1024x1024'])
                ->throw()->json('data.0.b64_json');
        } catch (\Illuminate\Http\Client\HttpClientException $e) {
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
        abort_unless(preg_match('/^[0-9a-f-]{36}\.(png|jpg|webp)$/', $file) && Storage::disk('local')->exists('studio/'.$file), 404);
        return Storage::disk('local')->response('studio/'.$file);
    }

    public function upload(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['image' => 'required|image|mimes:png,jpg,jpeg,webp|max:8192']);
        $path = $data['image']->store('catalog', 'public');
        abort_unless($path, 500);
        return back()->with('upload', '/storage/'.$path);
    }

    public function status(Request $request, int $id)
    {
        $this->authorizeAdmin();
        $status = $request->validate(['status' => 'required|in:paid,fulfilled,cancelled'])['status'];
        DB::transaction(function () use ($id, $status) {
            $order = DB::table('orders')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            $allowed = ['pending' => ['paid', 'cancelled'], 'paid' => ['fulfilled']];
            abort_unless(in_array($status, $allowed[$order->status] ?? [], true), 422);
            if ($status === 'cancelled') {
                foreach (json_decode($order->lines, true) as $line) {
                    $product = Entry::where('kind', 'product')->where('slug', $line['slug'])->lockForUpdate()->first();
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

    public function export()
    {
        $this->authorizeAdmin();
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['reference', 'status', 'total_toman', 'discount', 'tax', 'cost', 'created_at'], ',', '"', '');
            foreach (DB::table('orders')->orderBy('id')->cursor() as $order) {
                $lines = json_decode($order->lines, true);
                fputcsv($out, [$order->reference, $order->status, $order->total, array_sum(array_column($lines, 'discount')), array_sum(array_column($lines, 'tax')), array_sum(array_column($lines, 'cost')), $order->created_at], ',', '"', '');
            }
            fclose($out);
        }, 'barin-orders.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
