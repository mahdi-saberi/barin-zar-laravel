@php
    $file = session('generated') ? basename(session('generated')) : null;
@endphp
<section class="admin-card">
    <x-admin.media-field title="کتابخانهٔ رسانه" :value="session('upload', '')" />
</section>
@if($file)
    <section class="admin-card admin-generated" aria-label="خروجی استودیو">
        <img src="{{ route('admin.images.show', $file) }}" alt="پیش‌نمایش تصویر تولیدشده" width="480" height="480">
        <div>
            <h2>تصویر تولیدشده</h2>
            <div class="admin-actions">
                <form method="post" action="{{ route('admin.images.publish', $file, false) }}">
                    @csrf
                    <button class="admin-button admin-primary" type="submit">ذخیره در گالری</button>
                </form>
                <a class="admin-button" download href="{{ route('admin.images.show', $file) }}">دریافت تصویر</a>
            </div>
        </div>
    </section>
@endif
