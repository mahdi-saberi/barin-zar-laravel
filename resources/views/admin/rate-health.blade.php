@if(!empty($rateStatus['attempted_at']))
    <p class="admin-muted">آخرین تلاش زمان‌بند {{ \App\Support\PersianFormat::dateTime($rateStatus['attempted_at']) }} · {{ $rateStatus['message'] }}</p>
@else
    <p class="admin-muted">هنوز تلاشی از زمان‌بند ثبت نشده است.</p>
@endif
@if(!empty($manualRateStatus['attempted_at']))
    <p class="admin-muted">آخرین دریافت دستی {{ \App\Support\PersianFormat::dateTime($manualRateStatus['attempted_at']) }} · {{ $manualRateStatus['message'] }}</p>
@endif
