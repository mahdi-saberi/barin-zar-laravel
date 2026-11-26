@php
    $type = $field[2] ?? 'text';
    $value = $type === 'password' ? '' : old($name, $record[$name] ?? '');
    if ($type === 'textarea' && is_array($value)) {
        $value = implode("\n", array_filter($value, 'is_scalar'));
    }
    $value = is_scalar($value) ? $value : '';
    $required = str_starts_with($field[1], 'required|');
    $options = match ($type) {
        'category' => $categories,
        'placement' => ['home' => 'صفحه اصلی', 'product' => 'صفحه محصول', 'both' => 'هر دو'],
        'theme' => ['default' => 'پیش‌فرض', 'rose' => 'رز', 'black' => 'مشکی'],
        'unit' => ['toman' => 'تومان', 'rial' => 'ریال'],
        default => [],
    };
@endphp
@if($type === 'richtext')
    @include('admin.rich-text')
@elseif($type === 'gallery')
    @php
        $gallery = old($name, json_encode(\App\Support\Media::productGallery($record), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    @endphp
    <x-admin.media-field :name="$name" :title="$field[0]" :value="is_string($gallery) ? $gallery : '[]'" multiple />
@elseif($type === 'media')
    @if(($entry->slug ?? null) === 'home' && $kind === 'page')
        @php($value = old($name, $name === 'hero' ? \App\Support\Media::homeHero($record) : ($record + ['guide_image' => '/assets/m2.webp'])[$name]))
        @php($value = is_string($value) ? $value : '')
    @endif
    @php($alt = old($name.'_alt', $record[$name.'_alt'] ?? ''))
    <x-admin.media-field :name="$name" :title="$field[0]" :value="$value" :alt="is_string($alt) ? $alt : ''" />
@elseif($type === 'checkbox')
    <label class="admin-checkbox admin-field-wide">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" value="1" @checked($value)>
        {{ $field[0] }}
    </label>
@else
    <label @class(['admin-field-wide' => in_array($name, ['description', 'footer', 'sections'], true)])>{{ $field[0] }}
        @if($type === 'textarea')
            <textarea name="{{ $name }}" rows="4" @required($required) @if($errors->has($name)) aria-invalid="true" @endif>{{ $value }}</textarea>
        @elseif($options)
            <select name="{{ $name }}" @required($required)>
                @foreach($options as $key => $label)
                    <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
        @else
            <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" @required($required)
                @if($type === 'number') min="{{ in_array($name, ['milligrams', 'budget_low', 'budget_high'], true) ? 1 : 0 }}" step="1" inputmode="numeric" @endif
                @if($type === 'password') autocomplete="new-password" @endif
                @if(in_array($name, ['link', 'canonical', 'rate_url', 'rate_path', 'rate_token', 'ai_url', 'ai_model', 'ai_token', 'sections', 'product'], true)) dir="ltr" @endif
                @if($errors->has($name)) aria-invalid="true" @endif>
            @if($type === 'password' && !empty($record[$name]))<small>توکن ذخیره شده است.</small>@endif
        @endif
    </label>
@endif
