@php($roles = \App\Support\StaffAccess::ROLES)
<section class="admin-card">
    <form class="admin-filters admin-directory-filters" method="get" action="{{ route('admin.index', [], false) }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label>نام، شماره همراه یا ایمیل<input name="q" type="search" maxlength="120" value="{{ $term }}"></label>
        <button class="admin-button admin-primary" type="submit">جست‌وجو</button>
    </form>
</section>
<section class="admin-card">
    <div class="admin-card-heading"><h2>{{ $customers ? 'حساب‌های مشتریان' : 'حساب‌های سایت' }}</h2><p>{{ number_format($members->total()) }} حساب</p></div>
    <div class="admin-table-scroll" role="region" aria-label="فهرست کاربران" tabindex="0">
        <table class="admin-table">
            <thead><tr><th scope="col">نام</th><th scope="col">شماره همراه</th><th scope="col">ایمیل</th><th scope="col">کد ملی</th><th scope="col">سفارش‌ها</th><th scope="col">عضویت</th>@unless($customers)<th scope="col">نقش</th>@endunless</tr></thead>
            <tbody>
            @forelse($members as $member)
                <tr>
                    <th scope="row">{{ $member->name }}</th>
                    <td><bdi>{{ $member->mobile ?? '—' }}</bdi></td>
                    <td><bdi>{{ $member->email ?? '—' }}</bdi></td>
                    <td>{{ $member->national_id_hash ? 'ثبت شده' : '—' }}</td>
                    <td>{{ number_format($member->orders_count) }}</td>
                    <td>{{ \App\Support\PersianFormat::dateTime($member->created_at?->toIso8601String()) }}</td>
                    @unless($customers)
                        <td>
                            @if(\App\Support\StaffAccess::owner($member))
                                <span class="admin-badge badge-success">مدیر ارشد</span>
                            @else
                                <form method="post" class="admin-status-form" action="{{ route('admin.users.role', $member, false) }}">
                                    @csrf
                                    <select name="role" aria-label="نقش {{ $member->name }}">
                                        @foreach($roles as $role => $label)
                                            <option value="{{ $role }}" @selected($role === $member->role)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="admin-button admin-button-small" type="submit">ثبت نقش</button>
                                </form>
                            @endif
                        </td>
                    @endunless
                </tr>
            @empty
                <tr><td class="admin-empty" colspan="{{ $customers ? 6 : 7 }}">کاربری پیدا نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $members->onEachSide(1)->links('partials.pagination') }}
</section>
