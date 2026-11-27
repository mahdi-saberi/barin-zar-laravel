<section class="admin-card admin-rate" aria-labelledby="settings-rate">
    <div>
        <h2 id="settings-rate">نرخ هر گرم طلای ۱۸ عیار</h2>
        @include('admin.rate-value')
        @include('admin.rate-health')
    </div>
    @if(!empty($record['rate_url']))
        <form method="post" action="{{ route('admin.rate', [], false) }}">
            @csrf
            <button class="admin-button" type="submit">دریافت نرخ از API</button>
        </form>
    @endif
</section>

<section class="admin-card admin-rate" aria-labelledby="settings-studio-status">
    <div>
        <h2 id="settings-studio-status">وضعیت اتصال استودیوی تصویر</h2>
        @if(!($studioStatus['configured'] ?? false))
            <p class="admin-muted">نشانی سرویس استودیو در تنظیمات زیر وارد نشده است.</p>
        @elseif(!($studioStatus['host_allowed'] ?? false))
            <p class="admin-muted">میزبان <bdi dir="ltr">{{ $studioStatus['host'] ?? 'نامشخص' }}</bdi> در <bdi dir="ltr">BARIN_INTEGRATION_HOSTS</bdi> مجاز نیست یا نشانی HTTPS معتبر ندارد.</p>
        @else
            <p class="admin-muted">نشانی و میزبان مجاز است · مدل: <bdi dir="ltr">{{ $studioStatus['model'] }}</bdi> · توکن: {{ ($studioStatus['has_token'] ?? false) ? 'ثبت‌شده' : 'ثبت‌نشده' }}</p>
        @endif
        @if(!empty($studioStatus['last_attempt']))
            @php($lastStudio = $studioStatus['last_attempt'])
            <p class="admin-muted">
                آخرین آزمایش {{ $lastStudio['attempted_at_label'] ?? $lastStudio['attempted_at'] }}
                · {{ $lastStudio['stage_label'] ?? $lastStudio['stage'] }}
                @if(!empty($lastStudio['http_status']))(HTTP {{ $lastStudio['http_status'] }})@endif
                · {{ $lastStudio['message'] }}
            </p>
        @endif
    </div>
    @if($can['content'])
        <a class="admin-button" href="{{ route('admin.index', ['tab' => 'studio']) }}">باز کردن استودیو و لاگ</a>
    @endif
</section>
