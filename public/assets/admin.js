'use strict';

const upload = document.querySelector('[data-inline-upload]');
if (upload) {
    const form = upload.closest('form');
    const fileInput = upload.querySelector('[data-upload-file]');
    const button = upload.querySelector('[data-upload-button]');
    const message = upload.querySelector('[data-upload-message]');
    const save = form.querySelector('[data-save-entry]');
    upload.hidden = false;

    button.addEventListener('click', async () => {
        if (!fileInput.files.length) {
            message.textContent = 'یک فایل تصویر انتخاب کنید.';
            fileInput.focus();
            return;
        }
        if (form.elements.images.value.split('\n').filter(line => line.trim()).length >= 16) {
            message.textContent = 'برای افزودن تصویر، ابتدا یکی از مسیرها را حذف کنید.';
            return;
        }

        const data = new FormData();
        data.set('image', fileInput.files[0]);
        data.set('_token', form.elements._token.value);
        button.disabled = true;
        save.disabled = true;
        message.textContent = 'در حال بارگذاری…';
        try {
            const response = await fetch(upload.dataset.inlineUpload, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: data,
            });
            const result = await response.json();
            if (!response.ok || typeof result.path !== 'string') {
                message.textContent = result.errors?.image?.[0] || 'بارگذاری ناموفق بود. فایل و وضعیت ورود را بررسی کنید.';
                return;
            }

            const images = form.elements.images;
            images.value = [images.value.trimEnd(), result.path].filter(Boolean).join('\n');
            fileInput.value = '';
            message.textContent = 'مسیر تصویر اضافه شد. تغییرات محصول را ذخیره کنید.';
        } catch {
            message.textContent = 'بارگذاری ناموفق بود. دوباره تلاش کنید.';
        } finally {
            button.disabled = false;
            save.disabled = false;
        }
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
