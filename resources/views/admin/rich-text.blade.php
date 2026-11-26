@php
    $format = $name.'_html';
    $formatted = in_array(old($format, $record[$format] ?? false), [true, 1, '1'], true);
    $initial = \App\Support\RichText::render([$name => $value, $format => $formatted], $name);
    $allowMedia = $name === 'description' && in_array($kind, ['page', 'product'], true);
    $max = (int) \Illuminate\Support\Str::after($field[1], 'max:');
@endphp
<div class="admin-field-wide admin-rich-field" data-rich-field data-rich-limit="{{ $max }}">
    <label id="label-{{ $name }}" for="field-{{ $name }}">{{ $field[0] }}</label>
    <textarea id="field-{{ $name }}" name="{{ $name }}" rows="5" @if($errors->has($name)) aria-invalid="true" @endif>{{ $value }}</textarea>
    <input type="hidden" name="{{ $format }}" value="{{ $formatted ? 1 : 0 }}" data-rich-format>
    <div class="admin-rich-editor" data-rich-editor hidden>
        <div class="admin-rich-toolbar" role="group" aria-label="ابزارهای ویرایش {{ $field[0] }}" data-rich-toolbar>
            <span class="ql-formats">
                <select class="ql-header" aria-label="سبک پاراگراف">
                    <option selected value="">متن عادی</option>
                    <option value="2">تیتر اصلی</option>
                    <option value="3">زیرتیتر</option>
                    <option value="4">تیتر کوچک</option>
                </select>
            </span>
            <span class="ql-formats">
                <button type="button" class="ql-bold" title="پررنگ" aria-label="پررنگ"></button>
                <button type="button" class="ql-italic" title="مایل" aria-label="مایل"></button>
                <button type="button" class="ql-underline" title="زیرخط" aria-label="زیرخط"></button>
                <button type="button" class="ql-strike" title="خط‌خورده" aria-label="خط‌خورده"></button>
            </span>
            <span class="ql-formats">
                <button type="button" class="ql-list" value="bullet" title="فهرست نشانه‌دار" aria-label="فهرست نشانه‌دار"></button>
                <button type="button" class="ql-list" value="ordered" title="فهرست شماره‌دار" aria-label="فهرست شماره‌دار"></button>
                <button type="button" class="ql-blockquote" title="نقل‌قول" aria-label="نقل‌قول"></button>
                <select class="ql-align" aria-label="تراز پاراگراف">
                    <option selected value="">تراز پیش‌فرض</option>
                    <option value="right">راست‌چین</option>
                    <option value="center">وسط‌چین</option>
                    <option value="left">چپ‌چین</option>
                    <option value="justify">کشیده</option>
                </select>
                <button class="admin-rich-symbol" type="button" data-rich-direction title="تغییر جهت متن" aria-label="تغییر جهت متن">⇆</button>
            </span>
            <span class="ql-formats">
                <button type="button" class="ql-link" title="افزودن یا ویرایش پیوند" aria-label="افزودن یا ویرایش پیوند"></button>
                @if($allowMedia)
                    <button class="admin-rich-media" type="button" data-rich-open-media aria-haspopup="dialog" title="افزودن یا ویرایش رسانه">＋ رسانه</button>
                @endif
            </span>
            <span class="ql-formats">
                <button class="admin-rich-symbol" type="button" data-rich-undo title="واگرد" aria-label="واگرد">↶</button>
                <button class="admin-rich-symbol" type="button" data-rich-redo title="بازانجام" aria-label="بازانجام">↷</button>
                <button type="button" class="ql-clean" title="پاک کردن قالب‌بندی" aria-label="پاک کردن قالب‌بندی"></button>
            </span>
        </div>
        <div class="admin-rich-surface" data-rich-body data-label="label-{{ $name }}">{{ $initial }}</div>
        <div class="admin-rich-footer">
            <span data-rich-status role="status"></span>
            <small data-rich-count></small>
        </div>
    </div>
</div>
