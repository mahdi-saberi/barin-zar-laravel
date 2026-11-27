'use strict';

(() => {
    const dialog = document.querySelector('[data-media-picker]');
    if (!dialog) return;

    const get = key => dialog.querySelector('[data-' + key + ']');
    const number = new Intl.NumberFormat('fa-IR');
    const types = { jpg: 'image', jpeg: 'image', png: 'image', webp: 'image', mp4: 'video', webm: 'video' };
    const fileTypes = { 'image/jpeg': 'image', 'image/png': 'image', 'image/webp': 'image', 'video/mp4': 'video', 'video/webm': 'video' };
    const extension = value => value.split(/[?#]/, 1)[0].split('.').pop().toLowerCase();
    const local = path => typeof path === 'string' && /^\/(?!\/)[^\s\x00-\x1f\x7f\\<>]+$/.test(path);
    const knownType = ext => Object.hasOwn(types, ext) ? types[ext] : undefined;
    const mediaType = path => local(path) ? knownType(extension(path)) : undefined;
    const fromFile = file => (Object.hasOwn(fileTypes, file.type) ? fileTypes[file.type] : knownType(extension(file.name)));
    const titleOf = item => item.label || item.path.split('/').pop().split(/[?#]/, 1)[0]
        .replace(/-[0-9a-hjkmnp-tv-z]{26}(?=\.)/, '');
    const fileInput = get('picker-files');
    const referenceInput = get('reference-file');
    const altInput = get('picker-alt');
    const apply = get('picker-apply');
    const search = get('media-search');
    const libraryGrid = get('library-items');
    const selection = new Map();
    let context, tab, reference, referenceUrl, studioResult, libraryRequest, nextPage;
    let queue = [];
    let uploaded = [];
    let busy = false;

    function button(label, className, onClick) {
        const node = document.createElement('button');
        node.type = 'button';
        node.className = className;
        node.textContent = label;
        node.addEventListener('click', onClick);
        return node;
    }

    function preview(item, controls = false) {
        if (item.type === 'video' && !controls) {
            const glyph = document.createElement('span');
            glyph.className = 'media-video-placeholder';
            glyph.textContent = '▶';
            glyph.setAttribute('aria-hidden', 'true');
            return glyph;
        }

        const node = document.createElement(item.type === 'video' ? 'video' : 'img');
        node.src = item.path;
        if (item.type === 'video') {
            node.controls = true;
            node.playsInline = true;
            node.preload = 'metadata';
            node.setAttribute('aria-label', item.label || 'پیش‌نمایش ویدئو');
        } else {
            node.alt = item.alt || '';
            node.decoding = 'async';
            node.loading = 'lazy';
            node.draggable = false;
        }
        return node;
    }

    function status(message = '', error = false) {
        get('picker-status').textContent = message;
        get('picker-status').classList.toggle('media-error', error);
    }

    function updatePicker() {
        const picked = [...selection.values()];
        const items = tab === 'library' ? picked : tab === 'studio' ? (studioResult ? [studioResult] : []) : queue;
        const count = items.length + (tab === 'upload' ? uploaded.length : 0);
        const isPhoto = tab === 'upload' ? (queue[0] || uploaded[0])?.type === 'image' : items[0]?.type === 'image';
        apply.disabled = busy || count === 0;
        apply.textContent = tab === 'upload'
            ? (context.library ? 'بارگذاری در کتابخانه' : 'بارگذاری و افزودن')
            : (context.library ? 'انتخاب فایل' : (context.replace ? 'جایگزینی' : 'افزودن')) + (count > 1 ? ' ' + number.format(count) + ' فایل' : '');
        if (tab === 'studio' && context.library) apply.textContent = 'ذخیره در کتابخانه';
        get('picker-alt-label').hidden = !context.describe || count !== 1 || !isPhoto;
        get('queue-studio').hidden = queue.length !== 1 || queue[0]?.type !== 'image';
        get('library-studio').hidden = picked.length !== 1 || picked[0]?.type !== 'image';
        get('studio-generate').disabled = busy || !reference;
    }

    function setBusy(value) {
        busy = value;
        dialog.setAttribute('aria-busy', String(value));
        for (const node of dialog.querySelectorAll('[data-picker-close], [data-picker-tab], [data-picker-choose], [data-reference-choose], [data-upload-preset], [data-queue-studio], .media-queue button')) {
            node.disabled = value;
        }
        updatePicker();
    }

    function setTab(next) {
        if (busy) return;
        tab = next;
        status();
        for (const node of dialog.querySelectorAll('[data-picker-tab]')) {
            node.setAttribute('aria-pressed', String(next === node.dataset.pickerTab));
        }
        for (const node of dialog.querySelectorAll('[data-picker-panel]')) {
            node.hidden = next !== node.dataset.pickerPanel;
        }
        updatePicker();
        if (tab === 'library') loadLibrary(1);
    }

    function showStudioLog(message = '', error = false) {
        const box = get('studio-log');
        if (!box) return;
        box.hidden = !message;
        box.classList.toggle('is-error', error);
        box.replaceChildren();
        if (!message) return;
        const text = document.createElement('strong');
        text.textContent = message;
        box.append(text);
        if (error) {
            const note = document.createElement('small');
            note.textContent = 'جزئیات کامل‌تر (کد وضعیت، زمان و پاسخ سرویس) در لاگ استودیو و لاگ سرور ثبت شد.';
            box.append(note);
        }
    }

    async function refreshStudioLogs() {
        const container = document.querySelector('[data-studio-logs]');
        if (!container?.dataset.studioLogsUrl) return;
        try {
            const response = await fetch(container.dataset.studioLogsUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            const list = container.querySelector('[data-studio-log-items]');
            const clearForm = container.querySelector('[data-studio-logs-clear]');
            const logs = Array.isArray(data?.logs) ? data.logs : [];
            if (clearForm) clearForm.hidden = logs.length === 0;
            if (!list) return;
            if (!logs.length) {
                const empty = document.createElement('p');
                empty.className = 'admin-muted';
                empty.textContent = 'هنوز تلاشی در استودیو ثبت نشده است. برای آزمایش، روی «باز کردن کتابخانه» بزنید و در تب «✦ استودیوی هوش مصنوعی» تصویر تولید کنید.';
                list.replaceChildren(empty);
                return;
            }
            list.replaceChildren(...logs.map(item => {
                const ok = item.state === 'success';
                const article = document.createElement('article');
                article.className = 'admin-studio-log-item ' + (ok ? 'is-success' : 'is-error');

                const head = document.createElement('div');
                head.className = 'admin-studio-log-head';
                const stateBadge = document.createElement('span');
                stateBadge.className = 'admin-badge ' + (ok ? 'badge-success' : 'badge-danger');
                stateBadge.textContent = ok ? 'موفق' : 'خطا';
                const stageBadge = document.createElement('span');
                stageBadge.className = 'admin-badge badge-neutral';
                stageBadge.textContent = item.stage_label || item.stage || '-';
                head.append(stateBadge, stageBadge);
                if (item.http_status) {
                    const httpBadge = document.createElement('span');
                    httpBadge.className = 'admin-badge badge-warning';
                    httpBadge.dir = 'ltr';
                    httpBadge.textContent = 'HTTP ' + item.http_status;
                    head.append(httpBadge);
                }
                if (typeof item.duration_ms === 'number') {
                    const dur = document.createElement('span');
                    dur.className = 'admin-code';
                    dur.dir = 'ltr';
                    dur.textContent = item.duration_ms + ' ms';
                    head.append(dur);
                }
                const time = document.createElement('time');
                time.className = 'admin-muted admin-numeric';
                time.textContent = item.attempted_at_label || item.attempted_at || '';
                head.append(time);

                const msg = document.createElement('p');
                msg.className = 'admin-studio-log-message';
                msg.textContent = item.message || '';

                const meta = document.createElement('p');
                meta.className = 'admin-code admin-studio-log-meta';
                const parts = [
                    'مدل: ' + (item.model || '-'),
                    'نشانی: ' + (item.url || '-'),
                ];
                if (item.preset_id) parts.push('طرح: ' + (item.preset_title || '#' + item.preset_id));
                if (item.source_mime || item.source_bytes) {
                    const kb = item.source_bytes ? Math.round(item.source_bytes / 1024) + ' KB' : '?';
                    parts.push('فایل مرجع: ' + (item.source_mime || '?') + ' (' + kb + ')');
                }
                meta.textContent = parts.join(' · ');

                article.append(head, msg, meta);
                if (item.detail) {
                    const detail = document.createElement('pre');
                    detail.className = 'admin-studio-log-detail';
                    detail.dir = 'ltr';
                    detail.textContent = item.detail;
                    article.append(detail);
                }
                return article;
            }));
        } catch {
            // Keep existing server-rendered logs if refresh fails.
        }
    }

    function setReference(source) {
        if (referenceUrl) URL.revokeObjectURL(referenceUrl);
        referenceUrl = source?.file ? URL.createObjectURL(source.file) : '';
        reference = source;
        studioResult = null;
        get('studio-result').hidden = true;
        showStudioLog();
        const photo = get('reference-preview');
        if (source) photo.src = referenceUrl || source.path;
        else photo.removeAttribute('src');
        photo.hidden = !source;
        get('reference-empty').hidden = Boolean(source);
        updatePicker();
    }

    function open(options) {
        if (dialog.open) return;
        context = options;
        tab = 'upload';
        queue = [];
        uploaded = [];
        selection.clear();
        libraryGrid.replaceChildren();
        search.reset();
        get('picker-title').textContent = options.library ? 'کتابخانهٔ رسانه' : (options.replace ? 'ویرایش رسانه' : 'افزودن رسانه');
        get('picker-context').textContent = options.title;
        get('upload-preset').selectedIndex = 0;
        fileInput.multiple = options.limit > 1;
        altInput.value = options.alt || '';
        renderQueue();
        setReference(options.reference);
        setTab(options.tab || 'upload');
        if (options.files) enqueue(options.files);
        dialog.showModal();
        get('picker-tab="' + tab + '"')?.focus();
    }

    function close() {
        if (busy) return;
        libraryRequest?.abort();
        for (const item of queue) URL.revokeObjectURL(item.path);
        if (referenceUrl) URL.revokeObjectURL(referenceUrl);
        referenceUrl = '';
        dialog.close();
    }

    function finish(items) {
        const onSelect = context.onSelect;
        const alt = context.describe && !get('picker-alt-label').hidden ? altInput.value.trim() : '';
        close();
        onSelect(alt ? items.map(item => ({ ...item, alt })) : items);
    }

    function renderQueue() {
        get('upload-queue').replaceChildren(...queue.map(item => {
            const row = document.createElement('div');
            row.className = 'media-queue-item';
            const details = document.createElement('span');
            const name = document.createElement('bdi');
            name.textContent = item.file.name;
            const size = document.createElement('small');
            size.textContent = number.format(Math.max(1, Math.round(item.file.size / 1024))) + ' کیلوبایت';
            details.append(name, size);
            const remove = button('×', 'media-icon-button', () => {
                queue = queue.filter(queued => queued !== item);
                URL.revokeObjectURL(item.path);
                renderQueue();
                get('picker-choose').focus();
            });
            remove.setAttribute('aria-label', 'برداشتن ' + item.file.name);
            row.append(preview(item), details, remove);
            return row;
        }));
        updatePicker();
    }

    function validateFile(file, imageOnly = false) {
        const type = fromFile(file);
        if (!type || (imageOnly && type !== 'image')) {
            throw new Error(imageOnly ? 'عکس مرجع باید JPG، PNG یا WebP باشد.' : 'عکس JPG، PNG یا WebP یا ویدئوی MP4 و WebM انتخاب کنید.');
        }
        if (file.size > (type === 'video' ? 32 : 8) * 1024 ** 2) {
            throw new Error(type === 'video' ? 'سقف حجم ویدئو ۳۲ مگابایت است.' : 'سقف حجم عکس ۸ مگابایت است.');
        }
        return type;
    }

    function enqueue(files) {
        if (busy) return;
        if (context.limit === 1) {
            for (const item of queue) URL.revokeObjectURL(item.path);
            queue = [];
        }
        const errors = [];
        for (const file of files) {
            if (queue.length + uploaded.length >= context.limit) {
                errors.push('تعداد فایل‌ها از ظرفیت این بخش بیشتر است.');
                break;
            }
            try {
                const type = validateFile(file);
                queue.push({ file, path: URL.createObjectURL(file), type });
            } catch (error) {
                errors.push(error.message);
            }
        }
        renderQueue();
        status(errors.join(' '), errors.length > 0);
    }

    async function readJson(response, fallback) {
        const text = await response.text().catch(() => '');
        let data = null;
        try {
            data = text ? JSON.parse(text) : null;
        } catch {
            data = null;
        }
        if (response.ok && data) return data;
        const detail = data?.errors && Object.values(data.errors)[0]?.[0];
        if (detail) throw new Error(detail);
        if (response.status === 413) throw new Error('حجم فایل بیشتر از حد مجاز سرور است (کد ۴۱۳).');
        if (response.status === 419) throw new Error('نشست منقضی شده است (کد ۴۱۹). صفحه را نوسازی کنید.');
        const serverMessage = typeof data?.message === 'string' && data.message.trim() ? data.message.trim() : '';
        const suffix = serverMessage ? `${serverMessage} — کد ${response.status}` : `کد HTTP ${response.status}`;
        throw new Error(`${fallback} (${suffix})`);
    }

    async function loadLibrary(page) {
        libraryRequest?.abort();
        const request = new AbortController();
        libraryRequest = request;
        const note = get('library-note');
        const more = get('library-more');
        const filters = new URLSearchParams(new FormData(search));
        filters.set('page', page);
        if (page === 1) libraryGrid.replaceChildren();
        more.hidden = true;
        note.hidden = false;
        note.textContent = 'در حال دریافت فایل‌ها…';
        try {
            const response = await fetch(dialog.dataset.library + '?' + filters, {
                headers: { Accept: 'application/json' }, signal: request.signal,
            });
            const result = await readJson(response, 'دریافت فایل‌ها ناموفق بود. دوباره جست‌وجو کنید.');
            if (request.signal.aborted) return;
            for (const item of result.items) {
                if (!mediaType(item.path)) continue;
                const label = titleOf(item);
                const card = button('', 'media-library-item', () => {
                    if (context.limit === 1) selection.clear();
                    else if (!selection.has(item.path) && selection.size >= context.limit) {
                        status('ظرفیت انتخاب فایل تکمیل شده است.', true);
                        return;
                    }
                    if (card.getAttribute('aria-pressed') === 'true') selection.delete(item.path);
                    else selection.set(item.path, item);
                    for (const tile of libraryGrid.children) {
                        tile.setAttribute('aria-pressed', String(selection.has(tile.dataset.path)));
                    }
                    status(selection.size ? number.format(selection.size) + ' فایل انتخاب شده' : '');
                    updatePicker();
                });
                card.dataset.path = item.path;
                card.title = label;
                card.setAttribute('aria-pressed', String(selection.has(item.path)));
                card.disabled = context.existing?.includes(item.path) ?? false;
                const frame = document.createElement('span');
                frame.className = 'media-library-art';
                frame.append(preview(item));
                const caption = document.createElement('bdi');
                caption.textContent = label;
                card.append(frame, caption);
                libraryGrid.append(card);
            }
            nextPage = result.next;
            more.hidden = !nextPage;
            note.hidden = libraryGrid.childElementCount > 0;
            note.textContent = 'فایلی با این مشخصات پیدا نشد.';
        } catch (error) {
            if (!request.signal.aborted) note.textContent = error.message;
        }
    }

    async function send(file, preset = '') {
        const type = validateFile(file, Boolean(preset));
        const body = new FormData();
        body.set('_token', dialog.dataset.token);
        body.set('kind', type);
        body.set(type === 'video' ? 'file' : 'image', file);
        if (preset) body.set('preset', preset);
        const response = await fetch(preset ? dialog.dataset.generate : dialog.dataset.upload, {
            method: 'POST', body, headers: { Accept: 'application/json' },
        });
        const result = await readJson(response, 'ذخیرهٔ فایل ناموفق بود. دوباره تلاش کنید.');
        if (mediaType(result.path) !== type) throw new Error('نشانی فایل ذخیره‌شده معتبر نیست.');
        return { ...result, type };
    }

    async function generate() {
        if (busy || !reference) return;
        setBusy(true);
        showStudioLog();
        status('استودیو در حال تولید تصویر است…');
        try {
            let file = reference.file;
            if (!file) {
                const response = await fetch(reference.path);
                if (!response.ok) throw new Error('عکس مرجع در دسترس نیست (کد HTTP ' + response.status + ').');
                const blob = await response.blob();
                file = new File([blob], titleOf(reference), { type: blob.type });
            }
            studioResult = await send(file, get('upload-preset').value);
            get('studio-preview').src = studioResult.path;
            get('studio-download').href = studioResult.path;
            get('studio-result').hidden = false;
            status('تصویر آماده است.');
            showStudioLog('تصویر با موفقیت تولید شد.');
        } catch (error) {
            const message = error instanceof TypeError
                ? 'خطای شبکه یا قطع ارتباط با سرور برنامه (' + error.message + ').'
                : error.message;
            status(message, true);
            showStudioLog(message, true);
        } finally {
            setBusy(false);
            refreshStudioLogs();
        }
    }

    async function commit() {
        if (busy || apply.disabled) return;
        if (tab === 'studio') {
            setBusy(true);
            status('در حال ذخیرهٔ تصویر در کتابخانه…');
            let result;
            try {
                if (!local(studioResult.publish)) throw new Error('مسیر ذخیرهٔ تصویر معتبر نیست.');
                const body = new FormData();
                body.set('_token', dialog.dataset.token);
                result = await readJson(await fetch(studioResult.publish, {
                    method: 'POST', body, headers: { Accept: 'application/json' },
                }), 'ذخیرهٔ خروجی استودیو ناموفق بود.');
                if (mediaType(result.path) !== 'image') throw new Error('نشانی تصویر ذخیره‌شده معتبر نیست.');
            } catch (error) {
                status(error.message, true);
                return;
            } finally {
                setBusy(false);
            }
            return finish([{ ...result, type: 'image' }]);
        }
        if (tab === 'library') return finish([...selection.values()]);

        setBusy(true);
        try {
            let completed = uploaded.length;
            const total = completed + queue.length;
            while (queue.length) {
                status('در حال بارگذاری فایل ' + number.format(++completed) + ' از ' + number.format(total) + '…');
                const item = queue[0];
                uploaded.push(await send(item.file));
                queue.shift();
                URL.revokeObjectURL(item.path);
            }
        } catch (error) {
            renderQueue();
            status(error.message, true);
            return;
        } finally {
            setBusy(false);
        }
        finish(uploaded);
    }

    for (const item of dialog.querySelectorAll('[data-picker-tab]')) {
        item.addEventListener('click', () => setTab(item.dataset.pickerTab));
    }
    get('picker-close').addEventListener('click', close);
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        close();
    });
    get('picker-choose').addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
        enqueue(fileInput.files);
        fileInput.value = '';
    });
    const dropzone = get('media-dropzone');
    dropzone.addEventListener('dragover', event => {
        event.preventDefault();
        dropzone.classList.toggle('is-dragging', !busy);
    });
    dropzone.addEventListener('dragleave', event => {
        if (!dropzone.contains(event.relatedTarget)) dropzone.classList.remove('is-dragging');
    });
    dropzone.addEventListener('drop', event => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
        enqueue(event.dataTransfer.files);
    });
    get('reference-choose').addEventListener('click', () => referenceInput.click());
    referenceInput.addEventListener('change', () => {
        try {
            const file = referenceInput.files[0];
            if (!file) return;
            validateFile(file, true);
            setReference({ file });
            status();
        } catch (error) {
            status(error.message, true);
        } finally {
            referenceInput.value = '';
        }
    });
    get('queue-studio').addEventListener('click', () => {
        setReference({ file: queue[0].file });
        setTab('studio');
        get('upload-preset').focus();
    });
    get('library-studio').addEventListener('click', () => {
        setReference([...selection.values()][0]);
        setTab('studio');
        get('upload-preset').focus();
    });
    get('studio-generate').addEventListener('click', generate);
    search.addEventListener('submit', event => {
        event.preventDefault();
        loadLibrary(1);
    });
    search.elements.type.addEventListener('change', () => loadLibrary(1));
    get('library-more').addEventListener('click', () => loadLibrary(nextPage));
    apply.addEventListener('click', commit);

    function mount(field) {
        const input = field.querySelector('[data-media-value]');
        const singleAlt = field.querySelector('[data-media-alt]');
        const grid = field.querySelector('[data-media-items]');
        const add = field.querySelector('[data-media-add]');
        const count = field.querySelector('[data-media-count]');
        const max = Number(field.dataset.limit);
        const library = field.hasAttribute('data-media-library');
        const announce = field.querySelector('[data-media-field-status]');
        let items = [];
        let dragging = -1;

        if (max > 1) {
            try {
                const initial = JSON.parse(input.value);
                if (Array.isArray(initial)) {
                    items = initial.filter(item => local(item?.path)).map(item => ({
                        path: item.path,
                        type: mediaType(item.path) || 'image',
                        alt: typeof item.alt === 'string' ? item.alt : '',
                    }));
                }
            } catch {
                items = [];
            }
        } else if (local(input.value)) {
            items = [{ path: input.value, alt: singleAlt?.value || '', type: mediaType(input.value) || 'image' }];
        }

        function saveValue() {
            input.value = max > 1 ? JSON.stringify(items.map(({ path, alt }) => ({ path, alt }))) : (items[0]?.path || '');
            if (singleAlt) singleAlt.value = items[0]?.alt || '';
        }

        function notify(message, error = false) {
            announce.textContent = message;
            announce.hidden = !message;
            announce.classList.toggle('media-error', error);
        }

        function choose(index = -1, initialTab = 'upload') {
            const replacing = index >= 0;
            const chosen = replacing ? items[index] : null;
            open({
                title: field.dataset.title,
                library,
                replace: replacing && !library,
                tab: initialTab,
                reference: initialTab === 'studio' ? chosen : null,
                limit: replacing || library ? 1 : max - items.length,
                existing: library ? [] : items.filter((_, i) => i !== index).map(item => item.path),
                onSelect(selected) {
                    if (replacing || library) {
                        items.splice(Math.max(0, index), 1, { ...selected[0], alt: selected[0].type === chosen?.type ? chosen.alt : '' });
                    } else {
                        items.push(...selected.map(item => ({ ...item, alt: '' })));
                    }
                    render();
                    notify(library ? 'فایل در کتابخانه آماده است.' : 'انتخاب رسانه در این فرم اعمال شد.');
                    (grid.children[Math.max(0, index)] || add).focus({ preventScroll: true });
                },
            });
        }

        function move(from, to) {
            if (to < 0 || to >= items.length || from === to) return;
            items.splice(to, 0, items.splice(from, 1)[0]);
            render();
            grid.children[to].focus({ preventScroll: true });
            notify('ترتیب گالری تغییر کرد.');
        }

        function render() {
            const cover = max > 1 ? items.findIndex(item => item.type === 'image') : -1;
            grid.replaceChildren(...items.map((item, index) => {
                const card = document.createElement('article');
                card.className = 'media-card';
                card.tabIndex = -1;
                card.classList.toggle('is-cover', cover === index);
                const art = document.createElement('div');
                art.className = 'media-card-art';
                art.append(preview(item, true));
                const badge = document.createElement('span');
                badge.className = 'media-card-badge';
                badge.textContent = cover === index ? 'کاور محصول' : (item.type === 'video' ? 'ویدئو' : 'عکس');
                art.append(badge);

                const body = document.createElement('div');
                body.className = 'media-card-body';
                const filename = document.createElement('bdi');
                filename.className = 'media-card-name';
                filename.textContent = titleOf(item);
                filename.title = titleOf(item);
                body.append(filename);
                if (!library && item.type === 'image') {
                    const label = document.createElement('label');
                    label.textContent = 'متن جایگزین عکس';
                    const alt = document.createElement('input');
                    alt.value = item.alt;
                    alt.maxLength = 180;
                    alt.placeholder = 'مثلاً نمای نزدیک نگین';
                    alt.addEventListener('input', () => {
                        item.alt = alt.value;
                        art.querySelector('img').alt = alt.value;
                        saveValue();
                    });
                    label.append(alt);
                    body.append(label);
                }

                const actions = document.createElement('div');
                actions.className = 'media-card-actions';
                if (item.type === 'image') {
                    actions.append(button('✦ استودیو', 'media-card-action', () => choose(index, 'studio')));
                }
                if (library) {
                    const link = document.createElement('a');
                    link.className = 'media-card-action';
                    link.textContent = 'باز کردن فایل';
                    link.href = item.path;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    actions.append(link);
                } else {
                    actions.append(button('جایگزینی', 'media-card-action', () => choose(index, 'library')));
                    actions.append(button('حذف', 'media-card-action media-error', () => {
                        items.splice(index, 1);
                        render();
                        (grid.children[Math.min(index, items.length - 1)] || add).focus({ preventScroll: true });
                        notify('فایل از این فرم برداشته شد.');
                    }));
                }
                if (max > 1 && item.type === 'image' && cover !== index) {
                    actions.append(button('انتخاب کاور', 'media-card-action', () => move(index, 0)));
                }
                body.append(actions);
                if (max > 1 && items.length > 1) {
                    const reorder = document.createElement('div');
                    reorder.className = 'media-reorder';
                    const grab = document.createElement('span');
                    grab.textContent = '⠿';
                    grab.title = 'برای تغییر ترتیب بکشید';
                    grab.draggable = true;
                    grab.setAttribute('aria-hidden', 'true');
                    grab.addEventListener('dragstart', event => {
                        dragging = index;
                        event.dataTransfer.setData('text/plain', titleOf(item));
                        event.dataTransfer.effectAllowed = 'move';
                    });
                    grab.addEventListener('dragend', () => { dragging = -1; });
                    const earlier = button('→', 'media-icon-button', () => move(index, index - 1));
                    earlier.disabled = index === 0;
                    earlier.setAttribute('aria-label', 'منتقل کردن به جایگاه قبل');
                    const later = button('←', 'media-icon-button', () => move(index, index + 1));
                    later.disabled = index === items.length - 1;
                    later.setAttribute('aria-label', 'منتقل کردن به جایگاه بعد');
                    reorder.append(grab, earlier, later);
                    card.addEventListener('dragover', event => {
                        if (dragging >= 0) event.preventDefault();
                    });
                    card.addEventListener('drop', event => {
                        if (dragging < 0) return;
                        event.preventDefault();
                        const from = dragging;
                        dragging = -1;
                        move(from, index);
                    });
                    body.append(reorder);
                }
                card.append(art, body);
                return card;
            }));
            field.querySelector('[data-media-empty]').hidden = items.length > 0;
            add.hidden = !library && items.length >= max;
            count.hidden = max === 1;
            count.textContent = number.format(items.length) + ' / ' + number.format(max);
            saveValue();
        }

        add.addEventListener('click', () => choose(-1, library ? 'library' : 'upload'));
        if (max > 1) {
            field.closest('form').addEventListener('submit', event => {
                if (event.defaultPrevented || items.some(item => item.type === 'image')) return;
                event.preventDefault();
                notify('حداقل یک عکس برای کاور محصول انتخاب کنید.', true);
                (add.hidden ? grid.firstElementChild : add).focus();
            });
        }
        render();
        field.querySelector('[data-media-field-ui]').hidden = false;
    }

    for (const field of document.querySelectorAll('[data-media-field]')) mount(field);
    window.BarinMedia = { open, mediaType, fileTypes };
})();
