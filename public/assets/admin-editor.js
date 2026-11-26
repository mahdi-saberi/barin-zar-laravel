'use strict';

(() => {
    const Quill = window.Quill;
    if (!Quill) return;

    const media = window.BarinMedia;
    const Delta = Quill.import('delta');
    const Link = Quill.import('formats/link');
    const Image = Quill.import('formats/image');
    const BlockEmbed = Quill.import('blots/block/embed');
    const direction = Quill.import('formats/direction');
    const alignment = Quill.import('formats/align');
    const formatNumber = new Intl.NumberFormat('fa-IR');
    const linkDialog = document.querySelector('[data-rich-link]');
    const linkForm = linkDialog.querySelector('form');
    const linkRemove = linkDialog.querySelector('[data-link-remove]');
    const linkError = linkDialog.querySelector('[data-link-error]');
    let linkEdit;

    function validLink(url) {
        if (!url || /[\s\x00-\x1f\x7f\\<>]/.test(url)) return false;
        if (url.startsWith('#') || /^\/(?!\/)/.test(url)) return true;
        if (!/^https:\/\//i.test(url)) return false;
        try {
            return Boolean(new URL(url).hostname);
        } catch {
            return false;
        }
    }

    class StoreLink extends Link {
        static sanitize(url) {
            return validLink(url) ? url : this.SANITIZED_URL;
        }
    }

    class StoreImage extends Image {
        static sanitize(path) {
            return media?.mediaType(path) === 'image' ? path : '//:0';
        }
    }

    class StoreVideo extends BlockEmbed {
        static blotName = 'storeVideo';
        static tagName = 'VIDEO';

        static create(path) {
            const node = super.create();
            if (media?.mediaType(path) === 'video') node.setAttribute('src', path);
            node.controls = true;
            node.preload = 'metadata';
            node.playsInline = true;
            node.contentEditable = 'false';
            return node;
        }

        static value(node) {
            return node.getAttribute('src');
        }
    }

    direction.whitelist = ['rtl', 'ltr'];
    alignment.whitelist = ['left', 'right', 'center', 'justify'];
    Quill.register(StoreLink, true);
    Quill.register(StoreImage, true);
    Quill.register(StoreVideo);

    function openLink(quill) {
        let range = quill.getSelection(true);
        if (!range) return;

        const [anchor] = quill.scroll.descendant(StoreLink, range.index);
        if (anchor && range.length === 0) range = { index: quill.getIndex(anchor), length: anchor.length() };
        const format = quill.getFormat(range);
        const url = typeof format.link === 'string' ? format.link : '';
        const text = quill.getText(range.index, range.length).trim();
        linkEdit = { quill, range, text };
        linkForm.elements.label.value = text;
        linkForm.elements.url.value = url;
        linkRemove.hidden = !url;
        linkError.textContent = '';
        linkDialog.showModal();
        (text ? linkForm.elements.url : linkForm.elements.label).focus();
    }

    linkForm.addEventListener('submit', event => {
        event.preventDefault();
        const url = linkForm.elements.url.value.trim();
        const label = linkForm.elements.label.value.trim();
        if (!validLink(url) || !label) {
            linkError.textContent = 'متن پیوند و نشانی HTTPS یا مسیر داخلی مانند /shop را وارد کنید.';
            return;
        }
        const { quill, range, text } = linkEdit;
        quill.history.cutoff();
        if (label === text && range.length) {
            quill.formatText(range.index, range.length, 'link', url, 'user');
        } else {
            quill.updateContents(new Delta().retain(range.index).delete(range.length).insert(label, { link: url }), 'user');
        }
        quill.history.cutoff();
        linkDialog.close();
        quill.setSelection(range.index + label.length, 0);
    });
    linkRemove.addEventListener('click', () => {
        const { quill, range } = linkEdit;
        quill.formatText(range.index, range.length, 'link', false, 'user');
        linkDialog.close();
        quill.setSelection(range.index, range.length);
    });
    linkDialog.querySelector('[data-link-close]').addEventListener('click', () => linkDialog.close());

    function mount(field) {
        const editor = field.querySelector('[data-rich-editor]');
        const toolbar = field.querySelector('[data-rich-toolbar]');
        const surface = field.querySelector('[data-rich-body]');
        const textarea = field.querySelector('textarea');
        const format = field.querySelector('[data-rich-format]');
        const counter = field.querySelector('[data-rich-count]');
        const status = field.querySelector('[data-rich-status]');
        const directionButton = field.querySelector('[data-rich-direction]');
        const mediaButton = field.querySelector('[data-rich-open-media]');
        const mediaEnabled = Boolean(media && mediaButton);
        const max = Number(field.dataset.richLimit);
        const formats = ['header', 'bold', 'italic', 'underline', 'strike', 'list', 'indent', 'blockquote', 'align', 'direction', 'link'];
        if (mediaEnabled) formats.push('image', 'storeVideo');
        const allowedEmbed = (tag, expected) => [
            tag, (node, delta) => media?.mediaType(node.getAttribute('src')) === expected ? delta : new Delta(),
        ];
        const quill = new Quill(surface, {
            theme: 'snow',
            placeholder: 'متن را بنویسید…',
            formats,
            modules: {
                history: { userOnly: true },
                toolbar: { container: toolbar, handlers: { link() { openLink(this.quill); } } },
                clipboard: { matchers: [allowedEmbed('IMG', 'image'), allowedEmbed('VIDEO', 'video')] },
                uploader: {
                    mimetypes: mediaEnabled ? Object.keys(media.fileTypes) : [],
                    handler(range, files) { openMedia(range, files); },
                },
            },
        });
        quill.root.classList.add('rich-content');
        quill.root.dir = 'rtl';
        quill.root.setAttribute('role', 'textbox');
        quill.root.setAttribute('aria-multiline', 'true');
        quill.root.setAttribute('aria-labelledby', surface.dataset.label);
        let serverInvalid = textarea.getAttribute('aria-invalid') === 'true';
        const undo = field.querySelector('[data-rich-undo]');
        const redo = field.querySelector('[data-rich-redo]');
        let length;

        function sync() {
            length = Array.from(quill.getText().trim().replace(/\s+/g, ' ')).length;
            counter.textContent = formatNumber.format(length) + ' / ' + formatNumber.format(max) + ' نویسه';
            counter.classList.toggle('media-error', length > max);
            quill.root.setAttribute('aria-invalid', String(length > max || serverInvalid));
            undo.disabled = quill.history.stack.undo.length === 0;
            redo.disabled = quill.history.stack.redo.length === 0;
        }

        function embedAt(range) {
            if (!range || range.length > 1) return null;
            const op = quill.getContents(range.index, 1).ops[0];
            if (!op?.insert || typeof op.insert !== 'object') return null;
            const path = op.insert.image || op.insert.storeVideo;
            return path ? { path, type: media.mediaType(path), alt: op.attributes?.alt || '' } : null;
        }

        function openMedia(range, files = null) {
            const current = files ? null : embedAt(range);
            media.open({
                title: field.querySelector('label').textContent.trim(),
                tab: current ? 'library' : 'upload',
                reference: current?.type === 'image' ? current : null,
                replace: Boolean(current),
                limit: 1,
                alt: current?.alt,
                describe: true,
                files,
                onSelect([item]) {
                    const asset = item.type === 'video' ? { storeVideo: item.path } : { image: item.path };
                    const change = new Delta().retain(range.index).delete(current ? 1 : range.length);
                    quill.history.cutoff();
                    quill.updateContents(change.insert(asset, item.alt ? { alt: item.alt } : undefined), 'user');
                    quill.history.cutoff();
                    quill.setSelection(range.index + 1, 0);
                    status.textContent = 'رسانه در متن قرار گرفت.';
                },
            });
        }

        quill.on('text-change', () => {
            textarea.value = quill.getSemanticHTML().replaceAll('&nbsp;', ' ');
            format.value = '1';
            serverInvalid = false;
            status.textContent = '';
            sync();
        });
        quill.on('selection-change', range => {
            if (!range) return;
            directionButton.setAttribute('aria-pressed', String(quill.getFormat(range).direction === 'ltr'));
            if (mediaEnabled) mediaButton.textContent = embedAt(range) ? 'ویرایش رسانه' : '＋ رسانه';
        });
        directionButton.addEventListener('click', () => {
            quill.focus();
            const ltr = quill.getFormat().direction === 'ltr';
            quill.format('direction', ltr ? 'rtl' : 'ltr', 'user');
            directionButton.setAttribute('aria-pressed', String(!ltr));
        });
        undo.addEventListener('click', () => quill.history.undo());
        redo.addEventListener('click', () => quill.history.redo());
        if (mediaEnabled) mediaButton.addEventListener('click', () => openMedia(quill.getSelection(true)));
        quill.root.addEventListener('click', event => {
            if (event.target.closest('a')) event.preventDefault();
            if (!mediaEnabled || !event.target.matches('img, video')) return;
            const blot = Quill.find(event.target);
            if (blot) quill.setSelection(quill.getIndex(blot), 1, 'user');
        });
        for (const select of toolbar.querySelectorAll('select')) {
            select.previousElementSibling?.querySelector('.ql-picker-label')?.setAttribute('aria-label', select.getAttribute('aria-label'));
        }
        field.querySelector('label').addEventListener('click', event => {
            event.preventDefault();
            quill.focus();
        });
        field.closest('form').addEventListener('submit', event => {
            if (event.defaultPrevented || length <= max) return;
            event.preventDefault();
            status.textContent = 'تعداد نویسه‌ها از حد مجاز این بخش بیشتر است.';
            quill.focus();
        });
        sync();
        textarea.hidden = true;
        editor.hidden = false;
    }

    for (const field of document.querySelectorAll('[data-rich-field]')) mount(field);
})();
