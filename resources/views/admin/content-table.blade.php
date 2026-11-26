<div class="admin-table-scroll" tabindex="0" role="region" aria-label="جدول {{ $tabs[$kind] }}">
    <table class="admin-table admin-content-table">
        <thead><tr>
            <th scope="col">عنوان</th><th scope="col">شناسه</th>
            <th scope="col">{{ $kind === 'campaign' ? 'زمان‌بندی و وضعیت' : 'آخرین تغییر' }}</th>
            <th scope="col">عملیات</th>
        </tr></thead>
        <tbody>
        @forelse($items as $item)
            @php
                $data = $item->data;
                $editUrl = route('admin.index', ['tab' => $kind, 'edit' => $item->slug]);
            @endphp
            <tr>
                <th scope="row"><a class="admin-text-link" href="{{ $editUrl }}">{{ $data['name'] ?? $data['title'] ?? $item->slug }}</a></th>
                <td><bdi class="admin-code">{{ $item->slug }}</bdi></td>
                <td>
                    @if($kind === 'campaign')
                        @php
                            $today = today()->toDateString();
                            $status = $today < $data['start'] ? 'زمان‌بندی‌شده' : ($today > $data['end'] ? 'پایان‌یافته' : 'فعال');
                        @endphp
                        <span class="admin-badge {{ $status === 'فعال' ? 'badge-success' : 'badge-neutral' }}">{{ $status }}</span>
                        <small class="admin-block"><bdi>{{ $data['start'] }}</bdi> تا <bdi>{{ $data['end'] }}</bdi></small>
                    @else
                        <time dir="ltr">{{ $item->updated_at?->format('Y/m/d H:i') ?? '—' }}</time>
                        @if($kind === 'seo' && !empty($data['noindex']))
                            <small class="admin-block">ایندکس غیرفعال</small>
                        @endif
                    @endif
                </td>
                <td><div class="admin-actions admin-row-actions">
                    <a class="admin-button admin-button-small" href="{{ $editUrl }}">ویرایش</a>
                    @if($kind === 'page')
                        <a class="admin-button admin-button-small admin-row-link" href="{{ match($item->slug) { 'home' => route('home'), 'catalog' => route('shop.index'), default => route('page.show', $item->slug) } }}">مشاهده</a>
                    @endif
                </div></td>
            </tr>
        @empty
            <tr><td class="admin-empty" colspan="4">موردی پیدا نشد.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
