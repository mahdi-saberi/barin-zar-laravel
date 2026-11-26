@if(!empty($rate['value']))
    @php($updated = \App\Support\PersianFormat::dateTime($rate['timestamp'] ?? null))
    <p class="admin-rate-value"><strong><bdi>{{ \App\Support\PersianFormat::number((int) $rate['value']) }}</bdi> تومان</strong>
        <span class="admin-badge {{ ($rate['source'] ?? '') === 'api' ? 'badge-success' : 'badge-warning' }}">{{ match($rate['source'] ?? '') { 'api' => 'دریافت‌شده از API', 'manual' => 'ثبت دستی', default => 'نرخ نمونه' } }}</span>
    </p>
    @if($updated)
        <small>آخرین به‌روزرسانی <time dir="ltr" datetime="{{ $rate['timestamp'] }}" title="به وقت تهران">{{ $updated }}</time></small>
    @endif
@else
    <p class="admin-muted">هنوز نرخی ثبت نشده است.</p>
@endif
