@php
    $format = $name.'_html';
    $formatted = in_array(old($format, $record[$format] ?? false), [true, 1, '1'], true);
    $initial = \App\Support\RichText::render([$name => $value, $format => $formatted], $name);
@endphp
<div class="admin-field-wide admin-rich-field" data-rich-field>
    <label id="label-{{ $name }}" for="field-{{ $name }}">{{ $field[0] }}</label>
    <textarea id="field-{{ $name }}" name="{{ $name }}" rows="5" @if($errors->has($name)) aria-invalid="true" @endif>{{ $value }}</textarea>
    <input type="hidden" name="{{ $format }}" value="{{ $formatted ? 1 : 0 }}" data-rich-format>
    <div class="admin-rich-editor" data-rich-editor hidden>
        <div class="admin-rich-toolbar" role="group" aria-label="ابزارهای ویرایش {{ $field[0] }}">
            <button type="button" data-format="bold"><strong>پررنگ</strong></button>
            <button type="button" data-format="italic"><em>مایل</em></button>
            <button type="button" data-format="formatBlock" data-format-value="h3">تیتر</button>
            <button type="button" data-format="formatBlock" data-format-value="p">پاراگراف</button>
            <button type="button" data-format="insertUnorderedList">فهرست</button>
            <button type="button" data-format="insertOrderedList">شماره‌گذاری</button>
            <button type="button" data-format="createLink">پیوند</button>
            <button type="button" data-format="unlink">حذف پیوند</button>
        </div>
        <div class="admin-rich-body rich-content" data-rich-body contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="label-{{ $name }}" @if($errors->has($name)) aria-invalid="true" @endif>{{ $initial }}</div>
        <small class="admin-rich-feedback" role="status" data-rich-feedback hidden></small>
    </div>
</div>
