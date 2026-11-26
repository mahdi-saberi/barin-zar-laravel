<div class="admin-inline-upload" data-inline-upload="{{ route('admin.upload', [], false) }}" hidden>
    <div class="admin-fields">
        <label>شیوهٔ آماده‌سازی عکس
            <select data-upload-preset>
                <optgroup label="تصویرسازی با استودیو">
                    @foreach($presets as $preset)<option value="{{ $preset['id'] }}">{{ $preset['title'] }}</option>@endforeach
                </optgroup>
                <option value="">بارگذاری اصل عکس</option>
            </select>
        </label>
        <label>انتخاب عکس محصول
            <input type="file" accept="image/webp,image/jpeg,image/png" aria-describedby="upload-help" data-upload-file>
        </label>
    </div>
    <small id="upload-help">با انتخاب فایل، بارگذاری آغاز می‌شود. JPG، PNG یا WebP تا ۸ مگابایت.</small>
    <div class="admin-upload-progress">
        <p role="status" data-upload-message></p>
        <button class="admin-button admin-button-small" type="button" data-upload-retry hidden>تلاش دوباره</button>
    </div>
    <div class="admin-upload-preview" data-upload-preview hidden>
        <a target="_blank" rel="noopener" data-upload-result-link><img alt="پیش‌نمایش عکس تازه" width="136" height="136"></a>
        <button class="admin-button admin-button-small" type="button" data-upload-remove>حذف این عکس از فرم</button>
    </div>
</div>
@if(!empty($record['images']))
    <div class="admin-image-strip" aria-label="تصاویر فعلی">
        @foreach(array_filter($record['images']) as $i => $path)
            <img src="{{ $path }}" alt="{{ ($record['image_alts'][$i] ?? '') ?: 'تصویر '.($i + 1) }}" width="82" height="82" loading="lazy">
        @endforeach
    </div>
@endif
