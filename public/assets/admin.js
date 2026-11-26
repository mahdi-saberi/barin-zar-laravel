'use strict';

for (const field of document.querySelectorAll('[data-rich-field]')) {
    const textarea = field.querySelector('textarea');
    const format = field.querySelector('[data-rich-format]');
    const editor = field.querySelector('[data-rich-editor]');
    const body = field.querySelector('[data-rich-body]');
    const feedback = field.querySelector('[data-rich-feedback]');
    let range = null;

    const sync = () => {
        textarea.value = body.innerHTML;
        format.value = '1';
    };
    const announce = text => {
        feedback.textContent = text;
        feedback.hidden = text === '';
    };
    document.addEventListener('selectionchange', () => {
        const selection = window.getSelection();
        if (selection.rangeCount && body.contains(selection.getRangeAt(0).commonAncestorContainer)) {
            range = selection.getRangeAt(0).cloneRange();
        }
    });
    body.addEventListener('input', sync);
    body.addEventListener('paste', event => {
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        sync();
    });
    body.addEventListener('drop', event => event.preventDefault());
    body.addEventListener('click', event => {
        if (event.target.closest('a')) event.preventDefault();
    });
    for (const button of editor.querySelectorAll('[data-format]')) {
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
            const command = button.dataset.format;
            let value = button.dataset.formatValue || null;
            announce('');
            if (command === 'createLink') {
                if (!range || range.collapsed) {
                    announce('ابتدا متن پیوند را در ویرایشگر انتخاب کنید.');
                    return;
                }
                value = window.prompt('نشانی پیوند', 'https://')?.trim();
                if (!value) return;
                if (/[\s\x00-\x1f\x7f\\]/.test(value) || !/^(?:https:\/\/[^/]+|\/(?!\/)|#)/i.test(value)) {
                    announce('نشانی HTTPS یا مسیر داخلی مانند /shop وارد کنید.');
                    return;
                }
            }

            body.focus();
            if (range) {
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            }
            document.execCommand(command, false, value);
            sync();
        });
    }
    field.querySelector('label').addEventListener('click', event => {
        event.preventDefault();
        body.focus();
    });
    textarea.hidden = true;
    editor.hidden = false;
}

const upload = document.querySelector('[data-inline-upload]');
if (upload) {
    const form = upload.closest('form');
    const fileInput = upload.querySelector('[data-upload-file]');
    const preset = upload.querySelector('[data-upload-preset]');
    const message = upload.querySelector('[data-upload-message]');
    const retry = upload.querySelector('[data-upload-retry]');
    const preview = upload.querySelector('[data-upload-preview]');
    const previewLink = upload.querySelector('[data-upload-result-link]');
    const remove = upload.querySelector('[data-upload-remove]');
    const save = form.querySelector('[data-save-entry]');
    const images = form.elements.images;
    const alternatives = form.elements.image_alts;
    let busy = false;
    let latestPath = '';
    upload.hidden = false;

    async function sendImage() {
        const file = fileInput.files[0];
        if (busy || !file) return;

        retry.hidden = true;
        if (images.value.split('\n').filter(line => line.trim()).length >= 16) {
            message.textContent = 'برای افزودن تصویر، ابتدا یکی از مسیرها را حذف کنید.';
            retry.hidden = false;
            return;
        }
        if (file.size > 8 * 1024 * 1024) {
            message.textContent = 'حجم تصویر باید حداکثر ۸ مگابایت باشد.';
            return;
        }

        const data = new FormData();
        const generate = preset.value !== '';
        data.set('image', file);
        data.set('_token', form.elements._token.value);
        if (generate) data.set('preset', preset.value);
        busy = true;
        fileInput.disabled = preset.disabled = save.disabled = remove.disabled = true;
        images.readOnly = true;
        message.textContent = generate ? 'در حال ارسال عکس و تصویرسازی در استودیو…' : 'در حال بارگذاری عکس…';
        try {
            const response = await fetch(upload.dataset.inlineUpload, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: data,
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || typeof result.path !== 'string' || !/^\/storage\/catalog\/[\w-]+\.(png|jpe?g|webp)$/i.test(result.path)) {
                message.textContent = result.errors?.image?.[0] || result.errors?.preset?.[0] || result.errors?.ai_url?.[0]
                    || 'بارگذاری ناموفق بود. فایل و وضعیت ورود را بررسی کنید.';
                retry.hidden = false;
                return;
            }

            const separator = images.value && !images.value.endsWith('\n') ? '\n' : '';
            images.value += separator + result.path;
            latestPath = result.path;
            previewLink.href = result.path;
            previewLink.querySelector('img').src = result.path;
            preview.hidden = false;
            fileInput.value = '';
            message.textContent = generate
                ? 'خروجی استودیو به گالری فرم اضافه شد. تغییرات محصول را ذخیره کنید.'
                : 'عکس اصلی به گالری فرم اضافه شد. تغییرات محصول را ذخیره کنید.';
        } catch {
            message.textContent = 'بارگذاری ناموفق بود. دوباره تلاش کنید.';
            retry.hidden = false;
        } finally {
            busy = false;
            fileInput.disabled = preset.disabled = save.disabled = remove.disabled = false;
            images.readOnly = false;
        }
    }

    fileInput.addEventListener('change', sendImage);
    retry.addEventListener('click', sendImage);
    remove.addEventListener('click', () => {
        const paths = images.value.split('\n');
        const position = paths.findIndex(path => path.trim() === latestPath);
        if (position !== -1) {
            const alts = alternatives.value.split('\n');
            paths.splice(position, 1);
            alts.splice(position, 1);
            images.value = paths.join('\n');
            alternatives.value = alts.join('\n');
        }
        preview.hidden = true;
        message.textContent = 'عکس تازه از فرم برداشته شد.';
    });
}

const copy = document.querySelector('[data-copy-path]');
if (copy) {
    const source = document.querySelector('[data-copy-source]');
    copy.hidden = false;
    copy.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(source.value);
            copy.textContent = 'کپی شد';
        } catch {
            source.focus();
            source.select();
        }
    });
}
