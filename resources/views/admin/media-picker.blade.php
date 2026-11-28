<dialog class="media-picker" data-media-picker data-upload="{{ route('admin.upload', [], false) }}"
    data-generate="{{ route('admin.images.generate', [], false) }}"
    data-library="{{ route('admin.media', [], false) }}" data-token="{{ csrf_token() }}" aria-labelledby="media-picker-title">
    <header class="media-dialog-header">
        <div><h2 id="media-picker-title" data-picker-title>انتخاب رسانه</h2><p data-picker-context></p></div>
        <button type="button" class="media-icon-button media-close" data-picker-close aria-label="بستن">×</button>
    </header>
    <nav class="media-tabs" aria-label="منبع رسانه">
        <button type="button" data-picker-tab="upload" aria-pressed="true">بارگذاری</button>
        <button type="button" data-picker-tab="library" aria-pressed="false">کتابخانه</button>
        <button type="button" data-picker-tab="studio" aria-pressed="false">✦ استودیوی هوش مصنوعی</button>
    </nav>
    <div class="media-picker-body">
        <section data-picker-panel="upload">
            <div class="media-dropzone" data-media-dropzone>
                <span class="media-picture-icon" aria-hidden="true"></span>
                <strong>فایل را اینجا رها کنید</strong>
                <small>عکس تا ۸ مگابایت · ویدئوی MP4 و WebM تا ۳۲ مگابایت</small>
                <input type="file" data-picker-files hidden accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">
                <button class="admin-button" type="button" data-picker-choose>انتخاب فایل</button>
            </div>
            <div class="media-queue" data-upload-queue></div>
            <button class="admin-button admin-button-small" type="button" data-queue-studio hidden>✦ تصویرسازی با عکس انتخابی</button>
        </section>
        <section data-picker-panel="library" hidden>
            <form class="media-search" data-media-search>
                <input type="search" maxlength="120" aria-label="جست‌وجو در کتابخانه" name="q" placeholder="جست‌وجوی نام فایل">
                <select name="type" aria-label="نوع رسانه">
                    <option value="">همهٔ فایل‌ها</option>
                    <option value="image">عکس‌ها</option>
                    <option value="video">ویدئوها</option>
                </select>
                <button type="submit" class="admin-button">جست‌وجو</button>
            </form>
            <p class="media-library-note" data-library-note hidden></p>
            <div class="media-library-grid" data-library-items></div>
            <div class="media-library-more">
                <button class="admin-button" type="button" data-library-more hidden>نمایش فایل‌های بیشتر</button>
                <button class="admin-button" type="button" data-library-studio hidden>✦ استودیو با عکس انتخابی</button>
            </div>
        </section>
        <section data-picker-panel="studio" hidden>
            <div class="media-studio">
                <div class="media-reference">
                    <div class="media-reference-frame">
                        <span class="media-picture-icon" data-reference-empty aria-hidden="true"></span>
                        <img alt="عکس مرجع" data-reference-preview hidden>
                    </div>
                    <input type="file" data-reference-file hidden accept="image/jpeg,image/png,image/webp">
                    <button class="admin-button" type="button" data-reference-choose>انتخاب عکس مرجع</button>
                </div>
                <div class="media-studio-controls">
                    <label>طرح تصویرسازی
                        <select data-upload-preset>
                            @foreach(collect($presets)->groupBy('category') as $category => $choices)
                                <optgroup label="{{ config('barin.categories.'.$category, 'عمومی و مناسبتی') }}">
                                    @foreach($choices as $preset)
                                        <option value="{{ $preset['id'] }}" data-prompt="{{ $preset['prompt'] }}">{{ $preset['title'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>
                    <label>پرامپت این تصویر
                        <textarea data-studio-prompt dir="auto" rows="6" maxlength="8000"></textarea>
                    </label>
                    <p>یک عکس از قطعهٔ طلا انتخاب کنید. پیش‌نمایش خروجی قبل از افزودن به فرم نمایش داده می‌شود.</p>
                    <button class="admin-button" type="button" data-studio-generate>✦ تولید تصویر</button>
                </div>
            </div>
            <div class="media-studio-log" data-studio-log role="status" aria-live="polite" hidden></div>
            <div class="media-studio-result" data-studio-result hidden>
                <img data-studio-preview alt="تصویر تولیدشده در استودیو">
                <div><span>خروجی استودیو</span><a class="admin-button admin-button-small" data-studio-download download>دریافت تصویر</a></div>
            </div>
        </section>
        <label class="media-picker-alt" data-picker-alt-label hidden>متن جایگزین عکس
            <input maxlength="180" data-picker-alt placeholder="توصیف کوتاه عکس برای دسترس‌پذیری">
        </label>
    </div>
    <footer class="media-dialog-footer">
        <p data-picker-status role="status" aria-live="polite"></p>
        <button type="button" class="admin-button admin-primary" data-picker-apply disabled>افزودن</button>
    </footer>
</dialog>
