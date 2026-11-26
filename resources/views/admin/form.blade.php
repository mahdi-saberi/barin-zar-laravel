@php
    $groups = match ($kind) {
        'product' => [
            'مشخصات محصول' => ['name', 'category', 'description', 'published'],
            'موجودی و قیمت‌گذاری' => ['stock', 'milligrams', 'labor', 'cost', 'profit_bps', 'discount_bps', 'tax_bps'],
            'گالری تصاویر' => ['images', 'image_alts'],
        ],
        'page' => [
            'محتوای صفحه' => ['name', 'description', 'hero'],
            'چیدمان صفحه اصلی' => ['badge', 'button', 'products_heading', 'budget_heading', 'budget_low', 'budget_high', 'guide_heading', 'guide_text', 'guide_image', 'sections'],
        ],
        'campaign' => [
            'محتوا و جایگاه' => ['name', 'description', 'image', 'link', 'placement', 'product', 'theme'],
            'زمان‌بندی و تخفیف' => ['start', 'end', 'discount_bps'],
        ],
        'settings' => [
            'مشخصات فروشگاه' => ['brand', 'footer'],
            'اتصال نرخ طلا' => ['rate_url', 'rate_path', 'rate_unit', 'rate_token'],
            'اتصال استودیوی تصویر' => ['ai_url', 'ai_model', 'ai_token'],
        ],
        default => ['اطلاعات سئو' => array_keys($fields)],
    };
    $entry = $entry ?? null;
    if ($kind === 'page' && $entry?->slug !== 'home') {
        unset($groups['چیدمان صفحه اصلی']);
    }
@endphp

@if($kind === 'seo')
    <section class="admin-card admin-seo-preview" aria-label="پیش‌نمایش نتیجه جست‌وجو">
        <h2>پیش‌نمایش نتیجه جست‌وجو</h2>
        <strong id="snippet-title"></strong>
        <p id="snippet-description"></p>
        <small id="seo-checks"></small>
    </section>
@endif

<form class="admin-edit-form" method="post" action="{{ route('admin.save', $kind, false) }}" @if($kind === 'seo') data-seo-form @endif>
    @csrf
    @if($kind === 'settings')
        <input type="hidden" name="slug" value="store">
    @elseif($entry)
        <input type="hidden" name="edit" value="{{ $entry->slug }}">
        @if($kind === 'product')
            <input type="hidden" name="original_stock" value="{{ is_scalar(old('original_stock')) ? old('original_stock') : $record['stock'] }}">
        @endif
    @endif

    @foreach($groups as $legend => $names)
        <fieldset class="admin-card admin-fieldset">
            <legend>{{ $legend }}</legend>
            @if($kind === 'product' && in_array('images', $names, true))
                @include('admin.product-images')
            @endif
            <div class="admin-fields">
                @if($loop->first && $kind !== 'settings')
                    @php($slugValue = $entry?->slug ?? old('slug', $newSlug))
                    <label>شناسه یکتا
                        <input dir="ltr" name="slug" value="{{ is_string($slugValue) ? $slugValue : '' }}" @readonly($entry !== null) required maxlength="{{ $kind === 'seo' ? 98 : 90 }}" pattern="[A-Za-z0-9_\-]+">
                        @if($kind === 'seo')
                            <small>صفحه اصلی <bdi>home</bdi>، ویترین <bdi>catalog</bdi>، دسته <bdi>category-ring</bdi>، محصول <bdi>product-leaf</bdi></small>
                        @elseif(!$entry)
                            <small>حروف لاتین، عدد، خط تیره و زیرخط. این شناسه در نشانی صفحه استفاده می‌شود.</small>
                        @endif
                    </label>
                @endif
                @foreach($names as $name)
                    @include('admin.field', ['field' => $fields[$name]])
                @endforeach
            </div>
        </fieldset>
    @endforeach
    <div class="admin-actions admin-form-actions">
        <button class="admin-button admin-primary" type="submit" data-save-entry>ذخیره تغییرات</button>
        @if($entry && in_array($kind, ['product', 'page'], true))
            @php($seoSlug = $kind === 'product' ? 'product-'.$entry->slug : $entry->slug)
            <a class="admin-button" href="{{ route('admin.index', ['tab' => 'seo', 'create' => 1, 'slug' => $seoSlug]) }}">تنظیم سئو</a>
        @endif
        @if($kind !== 'settings')
            <a class="admin-text-link" href="{{ route('admin.index', ['tab' => $kind]) }}">بازگشت به فهرست</a>
        @endif
    </div>
</form>

@if($entry && ($kind === 'seo' || $kind === 'campaign' || ($kind === 'page' && !in_array($entry->slug, $corePages, true))))
    <details class="admin-card admin-delete">
        <summary>حذف این مورد</summary>
        <form method="post" action="{{ route('admin.entries.destroy', ['kind' => $kind, 'slug' => $entry->slug], false) }}">
            @csrf
            @method('DELETE')
            <p>«{{ $record['name'] ?? $record['title'] ?? $entry->slug }}» حذف شود؟</p>
            <button class="admin-button admin-danger" type="submit">تأیید حذف</button>
        </form>
    </details>
@endif
