@props(['name' => null, 'title', 'value' => '', 'alt' => '', 'multiple' => false])

<section class="admin-field-wide media-field" data-media-field data-title="{{ $title }}"
    data-limit="{{ $multiple ? \App\Support\Media::LIMIT : 1 }}" @if(!$name) data-media-library @endif>
    <input type="hidden" @if($name) name="{{ $name }}" @endif value="{{ $value }}" data-media-value>
    @if($name && !$multiple)
        <input type="hidden" name="{{ $name }}_alt" value="{{ $alt }}" data-media-alt>
    @endif
    <div class="media-field-ui" data-media-field-ui hidden>
        <div class="media-field-heading">
            <div>
                <h2>{{ $title }}</h2>
                @if($multiple)<p>اولین عکس، کاور محصول در ویترین است.</p>@endif
            </div>
            <div class="admin-actions">
                <small data-media-count hidden></small>
                <button type="button" class="admin-button admin-primary" data-media-add aria-haspopup="dialog">
                    {{ $name ? '＋ افزودن رسانه' : 'باز کردن کتابخانه' }}
                </button>
            </div>
        </div>
        <div class="media-field-empty" data-media-empty>
            <span class="media-picture-icon" aria-hidden="true"></span>
            <p>{{ $name ? 'هنوز رسانه‌ای انتخاب نشده است' : 'فایل‌های فروشگاه و طرح‌های استودیوی تصویر' }}</p>
            <small>{{ $name ? 'بارگذاری فایل، انتخاب از کتابخانه یا تصویرسازی در استودیو' : 'عکس و ویدئو را بارگذاری کنید یا یک عکس را در استودیو بازطراحی کنید.' }}</small>
        </div>
        <div class="media-field-grid" data-media-items></div>
        <p class="media-field-status" role="status" data-media-field-status hidden></p>
    </div>
    <noscript><p>برای انتخاب رسانه، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
</section>
