@if(!empty($rate['value']))
    <p class="admin-rate-value"><strong><bdi>{{ number_format($rate['value']) }}</bdi> تومان</strong>
        <span class="admin-badge {{ ($rate['source'] ?? '') === 'api' ? 'badge-success' : 'badge-warning' }}">{{ match($rate['source'] ?? '') { 'api' => 'دریافت‌شده از API', 'manual' => 'ثبت دستی', default => 'نرخ نمونه' } }}</span>
    </p>
    @if(!empty($rate['timestamp']))
        <small>آخرین به‌روزرسانی <time dir="ltr" datetime="{{ $rate['timestamp'] }}">{{ \Illuminate\Support\Carbon::parse($rate['timestamp'])->timezone(config('app.timezone'))->format('Y/m/d H:i') }}</time></small>
    @endif
@else
    <p class="admin-muted">هنوز نرخی ثبت نشده است.</p>
@endif
