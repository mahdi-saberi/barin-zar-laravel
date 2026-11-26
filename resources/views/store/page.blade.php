<article class="wrap inner-section content-page">
    <nav class="breadcrumb" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">خانه</a>
        <span aria-current="page">{{ $heading }}</span>
    </nav>
    <h1>{{ $heading }}</h1>
    @if(!empty($page['hero']))
        <x-store-media :path="$page['hero']" :alt="$page['hero_alt'] ?? ''" :label="$heading" width="950" height="520" fetchpriority="high" />
    @endif
    @if(!empty($page['description']))
        <div class="prose rich-content">{{ \App\Support\RichText::render($page, 'description') }}</div>
    @endif
</article>
