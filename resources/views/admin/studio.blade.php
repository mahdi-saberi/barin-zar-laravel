@php
    $file = session('generated') ? basename(session('generated')) : null;
@endphp
<div class="admin-studio-grid">
    <form class="admin-card admin-panel-form" action="{{ route('admin.images.generate', [], false) }}" method="post" enctype="multipart/form-data">
        @csrf
        <h2>تولید عکس محصول</h2>
        <label>عکس مرجع<input type="file" name="image" accept="image/webp,image/jpeg,image/png" required></label>
        <label>طرح عکاسی<select name="preset" required>
            @foreach($presets as $preset)<option value="{{ $preset['id'] }}">{{ $preset['title'] }}</option>@endforeach
        </select></label>
        <button class="admin-button admin-primary" type="submit">تولید تصویر</button>
    </form>
    <form class="admin-card admin-panel-form" action="{{ route('admin.upload', [], false) }}" method="post" enctype="multipart/form-data">
        @csrf
        <h2>بارگذاری در گالری</h2>
        <label>تصویر محصول یا بنر<input type="file" name="image" accept="image/webp,image/jpeg,image/png" required></label>
        <small>JPG، PNG یا WebP تا ۸ مگابایت</small>
        <button class="admin-button admin-primary" type="submit">بارگذاری</button>
        @if(session('upload'))
            <div class="admin-copy-path">
                <label>مسیر تصویر برای فرم محصول یا بنر<input readonly dir="ltr" value="{{ session('upload') }}" data-copy-source></label>
                <button class="admin-button" type="button" data-copy-path hidden>کپی مسیر</button>
            </div>
        @endif
    </form>
</div>
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
