@props(['path', 'alt' => '', 'label' => 'پخش ویدئو'])

@if(\App\Support\Media::type($path) === 'video')
    <video src="{{ $path }}" aria-label="{{ $label }}" controls preload="metadata" playsinline {{ $attributes->only(['class', 'width', 'height']) }}></video>
@else
    <img src="{{ $path }}" alt="{{ $alt }}" {{ $attributes }}>
@endif
