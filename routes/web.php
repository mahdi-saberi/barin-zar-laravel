<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Store;
use App\Models\Entry;
use Illuminate\Support\Facades\Route;

Route::get('/', [Store::class, 'show']);
Route::get('/shop', fn (\Illuminate\Http\Request $request, Store $store) => $store->show($request, 'catalog'));
Route::get('/cart', fn (\Illuminate\Http\Request $request, Store $store) => $store->show($request, 'cart'));
Route::get('/product/{slug}', fn (\Illuminate\Http\Request $request, Store $store, string $slug) => $store->show($request, 'product', $slug));
Route::get('/page/{slug}', fn (\Illuminate\Http\Request $request, Store $store, string $slug) => $store->show($request, 'page', $slug));
Route::post('/cart/{slug}', [Store::class, 'changeCart'])->block();
Route::post('/order', [Store::class, 'order'])->middleware('throttle:8,1')->block();
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /admin\nDisallow: /cart\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain']));
Route::get('/sitemap.xml', function () {
    $settings = Entry::where('kind', 'seo')->get()->keyBy('slug');
    $links = [];
    foreach (['home' => '/', 'catalog' => '/shop'] as $key => $path) {
        if (empty($settings->get($key)?->data['noindex'])) {
            $links[] = url($path);
        }
    }
    foreach (Entry::whereIn('kind', ['page', 'product'])->where(fn ($query) => $query->where('kind', 'product')->orWhere('slug', '!=', 'home'))->get() as $entry) {
        $key = $entry->kind === 'product' ? 'product-'.$entry->slug : $entry->slug;
        if (empty($settings->get($key)?->data['noindex'])) {
            $links[] = url('/'.$entry->kind.'/'.$entry->slug);
        }
    }
    return response(view('sitemap', compact('links')), 200, ['Content-Type' => 'application/xml']);
});
Route::get('/login', [Admin::class, 'loginForm'])->name('login');
Route::post('/login', [Admin::class, 'login'])->middleware('throttle:5,1');
Route::prefix('admin')->middleware('auth')->group(function () {
    Route::get('/', [Admin::class, 'index']);
    Route::get('/export', [Admin::class, 'export']);
    Route::post('/save/{kind}', [Admin::class, 'save']);
    Route::post('/rate', [Admin::class, 'refresh']);
    Route::post('/image', [Admin::class, 'generate']);
    Route::get('/image/{file}', [Admin::class, 'studioImage']);
    Route::post('/upload', [Admin::class, 'upload']);
    Route::post('/orders/{id}', [Admin::class, 'status']);
    Route::post('/logout', [Admin::class, 'logout']);
});
