<section class="admin-card admin-rate" aria-labelledby="settings-rate">
    <div>
        <h2 id="settings-rate">نرخ هر گرم طلای ۱۸ عیار</h2>
        @include('admin.rate-value')
    </div>
    @if(!empty($record['rate_url']))
        <form method="post" action="{{ route('admin.rate') }}">
            @csrf
            <button class="admin-button" type="submit">دریافت نرخ از API</button>
        </form>
    @endif
</section>
