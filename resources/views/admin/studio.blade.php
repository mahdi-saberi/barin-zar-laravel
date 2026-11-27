@php
    $file = session('generated') ? basename(session('generated')) : null;
    $studioStatus = $studioStatus ?? [];
    $studioLogs = $studioLogs ?? [];
@endphp
<section class="admin-card" aria-labelledby="studio-readiness">
    <div class="admin-card-heading">
        <div>
            <h2 id="studio-readiness">وضعیت اتصال استودیوی تصویر</h2>
            @if(!($studioStatus['configured'] ?? false))
                <p class="admin-muted">نشانی سرویس استودیو در تنظیمات فروشگاه ثبت نشده است.</p>
            @elseif(!($studioStatus['host_allowed'] ?? false))
                <p class="admin-muted">میزبان <bdi dir="ltr">{{ $studioStatus['host'] ?? 'نامشخص' }}</bdi> در فهرست میزبان‌های مجاز (<bdi dir="ltr">BARIN_INTEGRATION_HOSTS</bdi>) نیست یا نشانی HTTPS معتبر ندارد.</p>
            @else
                <p class="admin-muted">تنظیمات اولیهٔ اتصال آماده است.</p>
            @endif
        </div>
        @if($can['settings'])
            <a class="admin-button admin-button-small" href="{{ route('admin.index', ['tab' => 'settings']) }}">تنظیمات استودیو</a>
        @endif
    </div>
    <dl class="admin-studio-meta">
        <div><dt>نشانی سرویس</dt><dd dir="ltr">{{ $studioStatus['url'] ?? 'ثبت‌نشده' }}</dd></div>
        <div><dt>نوع درخواست</dt><dd dir="ltr">{{ $studioStatus['mode'] ?? 'multipart-edits' }}</dd></div>
        <div><dt>مدل فعال</dt><dd dir="ltr">{{ $studioStatus['model'] ?? '-' }}</dd></div>
        <div><dt>توکن</dt><dd>{{ ($studioStatus['has_token'] ?? false) ? 'ثبت‌شده (رمزگذاری‌شده)' : 'ثبت‌نشده' }}</dd></div>
        <div><dt>میزبان‌های مجاز</dt><dd dir="ltr">{{ !empty($studioStatus['allowed_hosts']) ? implode(', ', $studioStatus['allowed_hosts']) : 'تعریف‌نشده' }}</dd></div>
    </dl>
</section>

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

<section class="admin-card" aria-labelledby="studio-logs-heading" data-studio-logs data-studio-logs-url="{{ route('admin.studio.logs', [], false) }}">
    <div class="admin-card-heading">
        <div>
            <h2 id="studio-logs-heading">لاگ آزمایش و خطاهای استودیو</h2>
            <p class="admin-muted">جزئیات فنی آخرین تلاش‌های تصویرسازی (توکن‌ها در لاگ ثبت نمی‌شوند).</p>
        </div>
        <form method="post" action="{{ route('admin.studio.logs.clear', [], false) }}" data-studio-logs-clear @if(empty($studioLogs)) hidden @endif>
            @csrf
            <button class="admin-button admin-button-small" type="submit">پاک‌کردن لاگ</button>
        </form>
    </div>
    <div class="admin-studio-log-list" data-studio-log-items>
        @forelse($studioLogs as $item)
            <article class="admin-studio-log-item {{ ($item['state'] ?? '') === 'success' ? 'is-success' : 'is-error' }}">
                <div class="admin-studio-log-head">
                    <span class="admin-badge {{ ($item['state'] ?? '') === 'success' ? 'badge-success' : 'badge-danger' }}">
                        {{ ($item['state'] ?? '') === 'success' ? 'موفق' : 'خطا' }}
                    </span>
                    <span class="admin-badge badge-neutral">{{ $item['stage_label'] ?? ($item['stage'] ?? '-') }}</span>
                    @if(!empty($item['http_status']))
                        <span class="admin-badge badge-warning" dir="ltr">HTTP {{ $item['http_status'] }}</span>
                    @endif
                    @if(isset($item['duration_ms']))
                        <span class="admin-code" dir="ltr">{{ $item['duration_ms'] }} ms</span>
                    @endif
                    <time class="admin-muted admin-numeric">{{ $item['attempted_at_label'] ?? ($item['attempted_at'] ?? '') }}</time>
                </div>
                <p class="admin-studio-log-message">{{ $item['message'] ?? '' }}</p>
                <p class="admin-code admin-studio-log-meta">
                    مدل: <bdi dir="ltr">{{ $item['model'] ?? '-' }}</bdi>
                    · نشانی: <bdi dir="ltr">{{ $item['url'] ?? '-' }}</bdi>
                    @if(!empty($item['preset_id']))
                        · طرح: {{ $item['preset_title'] ?? ('#'.$item['preset_id']) }}
                    @endif
                    @if(!empty($item['source_mime']) || !empty($item['source_bytes']))
                        · فایل مرجع: <bdi dir="ltr">{{ $item['source_mime'] ?? '?' }} ({{ isset($item['source_bytes']) ? round($item['source_bytes'] / 1024).' KB' : '?' }})</bdi>
                    @endif
                </p>
                @if(!empty($item['detail']))
                    <pre class="admin-studio-log-detail" dir="ltr">{{ $item['detail'] }}</pre>
                @endif
            </article>
        @empty
            <p class="admin-muted">هنوز تلاشی در استودیو ثبت نشده است. برای آزمایش، روی «باز کردن کتابخانه» بزنید و در تب «✦ استودیوی هوش مصنوعی» تصویر تولید کنید.</p>
        @endforelse
    </div>
</section>
