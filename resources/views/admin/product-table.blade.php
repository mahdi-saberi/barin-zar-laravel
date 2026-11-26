<div class="admin-table-scroll" tabindex="0" role="region" aria-label="جدول محصولات">
    <table class="admin-table admin-product-table">
        <thead><tr>
            <th scope="col">محصول</th>
            <th scope="col">نوع طلا</th>
            <th scope="col">وزن (گرم)</th>
            <th scope="col">موجودی</th>
            <th scope="col">قیمت (تومان)</th>
            <th scope="col">انتشار</th>
            <th scope="col">عملیات</th>
        </tr></thead>
        <tbody>
        @forelse($items as $item)
            @php
                $p = $item->data;
                $published = $p['published'] ?? true;
                $stock = (int) $p['stock'];
                $editUrl = route('admin.index', ['tab' => 'product', 'edit' => $item->slug]);
            @endphp
            <tr>
                <th scope="row">
                    <a class="admin-item" href="{{ $editUrl }}">
                        @if(!empty($p['images'][0]))
                            <img src="{{ $p['images'][0] }}" width="54" height="54" alt="" loading="lazy">
                        @else
                            <span class="admin-thumbnail-placeholder" aria-hidden="true"></span>
                        @endif
                        <span>{{ $p['name'] }}<bdi class="admin-code admin-block">{{ $item->slug }}</bdi></span>
                    </a>
                </th>
                <td>{{ $categories[$p['category']] ?? '—' }}</td>
                <td class="admin-numeric"><bdi>{{ number_format($p['milligrams'] / 1000, 3) }}</bdi></td>
                <td><span class="admin-badge {{ $stock === 0 ? 'badge-danger' : ($stock <= 3 ? 'badge-warning' : 'badge-success') }}">
                    {{ $stock === 0 ? 'ناموجود' : number_format($stock).' عدد' }}
                </span></td>
                <td class="admin-numeric"><bdi>{{ $quotes->has($item->slug) ? number_format($quotes[$item->slug]['total']) : '—' }}</bdi></td>
                <td><span class="admin-badge {{ $published ? 'badge-success' : 'badge-neutral' }}">{{ $published ? 'منتشرشده' : 'پنهان' }}</span></td>
                <td><div class="admin-actions admin-row-actions">
                    <a class="admin-button admin-button-small" href="{{ $editUrl }}">ویرایش</a>
                    <form method="post" action="{{ route('admin.products.visibility', $item->slug, false) }}">
                        @csrf
                        @method('PATCH')
                        <button class="admin-button admin-button-small admin-row-link" type="submit" name="published" value="{{ $published ? 0 : 1 }}">{{ $published ? 'پنهان کردن' : 'انتشار' }}</button>
                    </form>
                </div></td>
            </tr>
        @empty
            <tr><td class="admin-empty" colspan="7">محصولی پیدا نشد.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
