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
@if($type === 'checkbox')
    <label class="admin-checkbox admin-field-wide">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" value="1" @checked($value)>
        {{ $field[0] }}
    </label>
@else
    <label @class(['admin-field-wide' => in_array($name, ['description', 'footer', 'sections'], true)])>{{ $field[0] }}
        @if($type === 'textarea')
            <textarea name="{{ $name }}" rows="{{ $name === 'description' ? 5 : 4 }}" @required($required) @if($name === 'images') dir="ltr" @endif @if($errors->has($name)) aria-invalid="true" @endif>{{ $value }}</textarea>
            @if($name === 'images')<small>سطر اول تصویر اصلی است. حداکثر ۱۶ تصویر.</small>@endif
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
                @if(in_array($name, ['image', 'hero', 'guide_image', 'link', 'canonical', 'rate_url', 'rate_path', 'rate_token', 'ai_url', 'ai_model', 'ai_token', 'sections', 'product'], true)) dir="ltr" @endif
                @if($errors->has($name)) aria-invalid="true" @endif>
            @if($type === 'password' && !empty($record[$name]))<small>توکن ذخیره شده است.</small>@endif
        @endif
    </label>
@endif
