@php
    $creating = [
        'product' => 'افزودن محصول',
        'page' => 'افزودن صفحه',
        'campaign' => 'افزودن کمپین',
        'seo' => 'افزودن تنظیمات سئو',
    ];
    $editing = [
        'product' => 'ویرایش محصول',
        'page' => 'ویرایش صفحه',
        'campaign' => 'ویرایش کمپین',
        'seo' => 'ویرایش سئو',
    ];
    $editingEntry = !empty($entry);
    $entryForm = !empty($formMode) && isset($creating[$tab]);
    $heading = $entryForm ? ($editingEntry ? $editing[$tab] : $creating[$tab]) : $tabs[$tab];
@endphp
@extends('admin.layout', ['heading' => $heading])

@section('content')
<header class="admin-page-heading">
    <div>
        @if($entryForm)
            <a class="admin-back-link" href="{{ route('admin.index', ['tab' => $tab]) }}">بازگشت به {{ $tabs[$tab] }}</a>
        @endif
        <h1>{{ $heading }}</h1>
        @if($editingEntry)
            <p>{{ $entry->data['name'] ?? $entry->data['title'] ?? $entry->slug }}</p>
        @endif
    </div>
    <div class="admin-actions">
        @if(isset($creating[$tab]) && !$entryForm)
            <a class="admin-button admin-primary" href="{{ route('admin.index', ['tab' => $tab, 'create' => 1]) }}">{{ $creating[$tab] }}</a>
        @elseif($tab === 'finance')
            <a class="admin-button" href="{{ route('admin.export', $filters) }}">خروجی CSV</a>
        @elseif($tab === 'product' && $editingEntry && ($record['published'] ?? true))
            <a class="admin-button" href="{{ route('product.show', $entry->slug) }}" target="_blank" rel="noopener">مشاهده در فروشگاه</a>
        @endif
    </div>
</header>

@switch($tab)
    @case('dashboard')
        @include('admin.dashboard')
        @break
    @case('finance')
        @include('admin.finance')
        @break
    @case('studio')
        @include('admin.studio')
        @break
    @case('settings')
        @include('admin.settings')
        @include('admin.form')
        @break
    @default
        @if($formMode)
            @include('admin.form')
        @else
            @include('admin.entries')
        @endif
@endswitch
@endsection
