@extends('layout', ['title'=>'مدیریت برین‌زر','noindex'=>true])
@section('content')
<section class="section">
@if(!empty($login))
<h1 class="title">ورود به مدیریت</h1>
<form class="store-form panel admin-form" method="post" action="/login">@csrf
<label>ایمیل<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required></label>
<label>رمز عبور<input name="password" type="password" autocomplete="current-password" required></label>
<button class="btn">ورود</button></form>
@else
<?php $tabs = ['finance'=>'مالی و سفارش‌ها','product'=>'محصول و موجودی','page'=>'صفحه‌ساز','campaign'=>'بنرها و کمپین‌ها','seo'=>'تحلیل سئو','studio'=>'استودیوی تصویر','settings'=>'نرخ و تنظیمات']; ?>
<div class="split"><h1 class="title">{{ $tabs[$tab] ?? 'پنل مدیریت' }}</h1><form method="post" action="/admin/logout">@csrf<button class="secondary">خروج</button></form></div>
<nav class="admin-links" aria-label="منوی مدیریت">@foreach($tabs as $key=>$label)
<a href="/admin?tab={{ $key }}" class="secondary" @if($key === $tab) aria-current="page" @endif>{{ $label }}</a>
@endforeach</nav>
@if($tab === 'finance')
<div class="tiles section">
@foreach(['total'=>'مبلغ سفارش‌های پرداخت‌شده','count'=>'تعداد سفارش‌های پرداخت‌شده','margin'=>'حاشیه ناخالص بر اساس بهای ثبت‌شده','discount'=>'مجموع تخفیف پرداخت‌شده'] as $key=>$label)
<div class="panel"><h2>{{ $label }}</h2><p class="price">{{ number_format($metrics[$key]) }} {{ $key === 'count' ? 'سفارش' : 'تومان' }}</p></div>
@endforeach
</div>
<a class="secondary" href="/admin/export">دریافت CSV سفارش‌ها</a>
<div class="panel admin-scroll section">
<table class="store-table"><thead><tr><th>سفارش</th><th>خریدار و تحویل</th><th>ریز سفارش</th><th>مبلغ (تومان)</th><th>وضعیت</th></tr></thead>
<tbody>@forelse($orders as $order)
<?php $customer = json_decode($order->customer,true); ?>
<tr><td><small>{{ $order->reference }}<br>{{ $order->created_at }}</small></td>
<td>{{ $customer['name'] }}<br>{{ $customer['mobile'] }}<br>{{ $customer['delivery'] === 'pickup' ? 'حضوری' : ($customer['address'] ?? 'ارسال') }}</td>
<td class="order-lines"><details><summary>محصول و ریز قیمت</summary>@foreach(json_decode($order->lines,true) as $line)
<p>{{ $line['name'] }} · ارزش طلا {{ number_format($line['gold']) }} · اجرت {{ number_format($line['labor']) }} · سود {{ number_format($line['profit']) }} · تخفیف {{ number_format($line['discount']) }} · مالیات {{ number_format($line['tax']) }} · بهای تمام‌شده {{ number_format($line['cost']) }}</p>@endforeach</details></td>
<td>{{ number_format($order->total) }}</td>
<td>{{ ['pending'=>'منتظر تأیید','paid'=>'پرداخت تأییدشده','fulfilled'=>'تحویل‌شده','cancelled'=>'لغوشده'][$order->status] }}
@if(in_array($order->status,['pending','paid']))
<form method="post" action="/admin/orders/{{ $order->id }}">@csrf
<select name="status" aria-label="وضعیت جدید">@if($order->status==='pending')<option value="paid">تأیید پرداخت</option><option value="cancelled">لغو سفارش</option>@else<option value="fulfilled">تحویل‌شده</option>@endif</select>
<button class="secondary">ثبت وضعیت</button></form>
@endif</td></tr>
@empty<tr><td colspan="5">هنوز سفارشی ثبت نشده است.</td></tr>@endforelse</tbody></table>
<div class="row">@if($orders->previousPageUrl())<a class="link" href="{{ $orders->previousPageUrl() }}">صفحه قبل</a>@endif @if($orders->nextPageUrl())<a class="link" href="{{ $orders->nextPageUrl() }}">صفحه بعد</a>@endif</div>
</div>
@elseif($tab === 'studio')
<div class="twocol">
<form class="store-form panel" action="/admin/image" method="post" enctype="multipart/form-data">@csrf
<h2>تولید عکس محصول</h2>
<label>عکس مرجع<input type="file" name="image" accept="image/webp,image/jpeg,image/png" required></label>
<label>پرامپت آماده<select name="preset">@foreach($presets as $preset)<option value="{{ $preset['id'] }}">{{ $preset['title'] }}</option>@endforeach</select></label>
<button class="btn">تولید تصویر</button><small>پس از بررسی طرح و مقیاس قطعه، تصویر تأییدشده را در بخش محصول بارگذاری کنید.</small>
</form>
<form class="store-form panel" action="/admin/upload" method="post" enctype="multipart/form-data">@csrf
<h2>بارگذاری تصاویر گالری و بنر</h2>
<label>فایل JPG، PNG یا WebP<input type="file" name="image" accept="image/webp,image/jpeg,image/png" required></label>
<button class="btn">بارگذاری</button>
@if(session('upload'))<input readonly aria-label="مسیر تصویر بارگذاری‌شده" dir="ltr" value="{{ session('upload') }}">@endif
</form></div>
@if(session('generated'))
<div class="panel section"><a class="link" download href="/admin/image/{{ basename(session('generated')) }}">دریافت تصویر برای بازبینی</a><img style="max-width:480px" src="/admin/image/{{ basename(session('generated')) }}" alt="پیش‌نمایش خروجی استودیو"></div>
@endif
@elseif($kind)
@if($kind==='settings')
<form action="/admin/rate" method="post" class="panel split">@csrf
<p>آخرین نرخ {{ number_format($rate['value'] ?? 0) }} تومان · {{ $rate['source'] ?? 'بدون نرخ' }} <span dir="ltr">{{ $rate['timestamp'] ?? '' }}</span></p><button class="secondary">دریافت نرخ از API</button></form>
@else
<nav class="admin-links" aria-label="سوابق"><a class="secondary" href="/admin?tab={{ $kind }}">ایجاد</a>@foreach($items as $item)
<a class="secondary" href="/admin?tab={{ $kind }}&amp;edit={{ $item->slug }}">{{ $item->data['name'] ?? $item->data['title'] ?? $item->slug }}</a>
@endforeach</nav>
@endif
@if($kind==='seo')
<div class="snippet"><strong id="snippet-title"></strong><p id="snippet-description"></p><small id="seo-checks"></small></div>
<p>شناسه‌های سئو مانند home، catalog، guide یا product-leaf به صفحه مربوط متصل می‌شوند.</p>
@endif
<form class="store-form panel admin-form" method="post" action="/admin/save/{{ $kind }}" @if($kind==='seo') data-seo-form @endif>
@csrf
@if($kind==='settings')<input type="hidden" name="slug" value="store">
@else<label>شناسه یکتا<input dir="ltr" name="slug" value="{{ old('slug',$slug) }}" required pattern="[A-Za-z0-9_-]+"></label>@endif
@foreach($forms[$kind] as $name=>$field)
@php
    $type = $field[2] ?? 'text';
    $value = $type === 'password' ? '' : old($name,$record[$name] ?? '');
    $options = match ($type) {
        'category' => config('barin.categories'),
        'placement' => ['home'=>'صفحه اصلی','product'=>'صفحه محصول','both'=>'هر دو'],
        default => [],
    };
@endphp
<label>{{ $field[0] }}
@if($type==='textarea')<textarea name="{{ $name }}">{{ is_array($value) ? implode("\n",$value) : $value }}</textarea>
@elseif($options)<select name="{{ $name }}">@foreach($options as $key=>$label)<option value="{{ $key }}" @selected($value === $key)>{{ $label }}</option>@endforeach</select>
@else<input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" @if(str_starts_with($field[1],'required|')) required @endif @if($type==='password') autocomplete="new-password" @endif>@endif
</label>
@endforeach
<button class="btn">ذخیره</button></form>
@endif
@endif
</section>
@endsection
