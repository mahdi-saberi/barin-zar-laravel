<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Store;
use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [Store::class, 'show'])->name('home');
Route::get('/shop', [Store::class, 'catalog'])->name('shop.index');
Route::get('/shop/category/{category}', [Store::class, 'catalog'])->name('shop.category');
Route::get('/gold-rate', [Store::class, 'goldRate'])->name('shop.gold-rate');
Route::get('/cart', fn (Request $request, Store $store) => $store->show($request, 'cart'));
Route::get('/product/{slug}', fn (Request $request, Store $store, string $slug) => $store->show($request, 'product', $slug))->name('product.show');
Route::get('/page/{slug}', fn (Request $request, Store $store, string $slug) => $store->show($request, 'page', $slug))->name('page.show');
Route::post('/cart/{slug}', [Store::class, 'changeCart'])->block()->name('cart.change');
Route::post('/order', [Store::class, 'order'])->middleware('throttle:8,1')->block()->name('order.store');
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /admin\nDisallow: /cart\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain']));
Route::get('/sitemap.xml', function () {
    $settings = Entry::where('kind', 'seo')->get()->keyBy('slug');
    $links = [];
    foreach (['home' => '/', 'catalog' => '/shop'] as $key => $path) {
        if (empty($settings->get($key)?->data['noindex'])) {
            $links[] = url($path);
        }
    }
    $products = Entry::products()->published();
    $activeCategories = (clone $products)->select('data->category as category')->distinct()->pluck('category');
    foreach (config('barin.categories') as $key => $label) {
        if ($activeCategories->contains($key) && empty($settings->get('category-'.$key)?->data['noindex'])) {
            $links[] = route('shop.category', $key);
        }
    }
    $pages = Entry::where('kind', 'page')->whereNotIn('slug', ['home', 'catalog']);
    foreach ($products->unionAll($pages)->cursor() as $entry) {
        $key = $entry->kind === 'product' ? 'product-'.$entry->slug : $entry->slug;
        if (empty($settings->get($key)?->data['noindex'])) {
            $links[] = url('/'.$entry->kind.'/'.$entry->slug);
        }
    }
    return response(view('sitemap', compact('links')), 200, ['Content-Type' => 'application/xml']);
});
Route::get('/login', [Admin::class, 'loginForm'])->name('login');
Route::post('/login', [Admin::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
Route::prefix('admin')->middleware('auth')->group(function () {
    Route::get('/', [Admin::class, 'index'])->name('admin.index');
    Route::get('/export', [Admin::class, 'export'])->name('admin.export');
    Route::post('/save/{kind}', [Admin::class, 'save'])->name('admin.save');
    Route::patch('/products/{slug}/visibility', [Admin::class, 'visibility'])->name('admin.products.visibility');
    Route::delete('/entries/{kind}/{slug}', [Admin::class, 'destroy'])->name('admin.entries.destroy');
    Route::post('/rate', [Admin::class, 'refresh'])->name('admin.rate');
    Route::post('/image', [Admin::class, 'generate'])->name('admin.images.generate');
    Route::get('/image/{file}', [Admin::class, 'studioImage'])->name('admin.images.show');
    Route::post('/image/{file}/publish', [Admin::class, 'publishImage'])->name('admin.images.publish');
    Route::post('/upload', [Admin::class, 'upload'])->name('admin.upload');
    Route::post('/orders/{id}', [Admin::class, 'status'])->name('admin.orders.status');
    Route::post('/logout', [Admin::class, 'logout'])->name('admin.logout');
});
