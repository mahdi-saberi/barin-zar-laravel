'use strict';

const image = document.querySelector('#product-image');
const thumbnails = document.querySelectorAll('[data-gallery]');
for (const thumbnail of thumbnails) {
    thumbnail.addEventListener('click', () => {
        image.src = thumbnail.dataset.gallery;
        image.alt = thumbnail.dataset.alt;
        for (const button of thumbnails) {
            button.setAttribute('aria-pressed', String(button === thumbnail));
        }
    });
}

for (const alternate of document.querySelectorAll('[data-product-alternate]')) {
    const link = alternate.parentElement;
    const loadPhoto = () => {
        if (!alternate.isConnected) return;
        const photo = alternate.content.firstElementChild;
        photo.addEventListener('load', () => photo.classList.add('is-loaded'), { once: true });
        alternate.replaceWith(photo);
    };
    link.addEventListener('pointerenter', event => {
        if (event.pointerType !== 'touch') loadPhoto();
    });
    link.addEventListener('focus', loadPhoto, { once: true });
}

const seo = document.querySelector('[data-seo-form]');
if (seo) {
    const update = () => {
        const title = seo.elements.title.value.trim();
        const description = seo.elements.description.value.trim();
        const keyword = seo.elements.keyword.value.trim();
        document.querySelector('#snippet-title').textContent = title || 'عنوان صفحه';
        document.querySelector('#snippet-description').textContent = description || 'توضیح متای صفحه';
        document.querySelector('#seo-checks').textContent = [
            title.length >= 30 && title.length <= 65 ? 'طول عنوان مناسب است' : 'عنوان پیشنهادی ۳۰ تا ۶۵ نویسه',
            description.length >= 70 && description.length <= 160 ? 'طول توضیح مناسب است' : 'توضیح پیشنهادی ۷۰ تا ۱۶۰ نویسه',
            keyword && title.includes(keyword) && description.includes(keyword) ? 'کلیدواژه در عنوان و توضیح وجود دارد' : 'کلیدواژه هدف را در عنوان و توضیح به‌کار ببرید',
        ].join(' · ');
    };
    seo.addEventListener('input', update);
    update();
}

const collectionNavigation = document.querySelector('[data-collection-tabs]');
if (collectionNavigation) {
    const tabs = Array.from(collectionNavigation.querySelectorAll('[data-collection-tab]'));
    const panels = tabs.map(tab => document.getElementById(tab.hash.slice(1)));
    const controls = document.querySelector('[data-collection-controls]');
    let selectedIndex = 0;
    collectionNavigation.setAttribute('role', 'tablist');

    const select = (index, focus = false) => {
        selectedIndex = index;
        tabs.forEach((tab, current) => {
            const selected = index === current;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            panels[current].hidden = !selected;
        });
        if (focus) tabs[index].focus();
    };

    tabs.forEach((tab, index) => {
        const panel = panels[index];
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-controls', panel.id);
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tab.id);
        tab.addEventListener('click', event => {
            event.preventDefault();
            select(index);
        });
        tab.addEventListener('keydown', event => {
            const next = {
                ArrowLeft: (index + 1) % tabs.length,
                ArrowRight: (index + tabs.length - 1) % tabs.length,
                Home: 0,
                End: tabs.length - 1,
                ' ': index,
            }[event.key];
            if (next === undefined) return;
            event.preventDefault();
            select(next, true);
        });
    });
    select(Math.max(0, tabs.findIndex(tab => tab.hash === location.hash)));

    if (controls) {
        const status = controls.querySelector('[role="status"]');
        for (const button of controls.querySelectorAll('[data-collection-step]')) {
            button.addEventListener('click', () => {
                select((selectedIndex + Number(button.dataset.collectionStep) + tabs.length) % tabs.length);
                tabs[selectedIndex].scrollIntoView({ block: 'nearest', inline: 'nearest' });
                status.textContent = tabs[selectedIndex].textContent;
            });
        }
        controls.hidden = false;
    }
}

const categorySlider = document.querySelector('[data-category-slider]');
if (categorySlider) {
    const gallery = categorySlider.querySelector('.category-gallery');
    const controls = categorySlider.querySelector('[data-slider-controls]');
    const previous = categorySlider.querySelector('[data-slider-previous]');
    const next = categorySlider.querySelector('[data-slider-next]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const updateControls = () => {
        const offset = Math.abs(gallery.scrollLeft);
        const maximum = gallery.scrollWidth - gallery.clientWidth;
        controls.hidden = maximum <= 2;
        previous.disabled = offset <= 2;
        next.disabled = offset >= maximum - 2;
    };
    const move = direction => gallery.scrollBy({
        left: direction * gallery.clientWidth,
        behavior: reducedMotion.matches ? 'auto' : 'smooth',
    });

    previous.addEventListener('click', () => move(1));
    next.addEventListener('click', () => move(-1));
    gallery.addEventListener('scroll', updateControls, { passive: true });
    new ResizeObserver(updateControls).observe(gallery);
    updateControls();
}

const rateBar = document.querySelector('[data-rate-url]');
if (rateBar) {
    const minute = 60_000;
    const price = rateBar.querySelector('[data-rate-price]');
    const updated = rateBar.querySelector('[data-rate-time]');
    const source = rateBar.querySelector('[data-rate-source]');
    const reload = rateBar.querySelector('[data-rate-reload]');
    const initialValue = Number(rateBar.dataset.rateValue);
    const panels = Array.from(document.querySelectorAll('[data-quote-slug]'));
    const slugs = new Set(panels.map(panel => panel.dataset.quoteSlug));
    const endpoint = new URL(rateBar.dataset.rateUrl, window.location.href);
    for (const slug of slugs) endpoint.searchParams.append('products[]', slug);
    const numberFormat = new Intl.NumberFormat('en-US');
    let lastCheck = Date.now();
    let timer;
    let checking = false;

    async function refreshRate() {
        if (document.hidden || checking) return;
        checking = true;
        lastCheck = Date.now();

        try {
            const response = await fetch(endpoint, {
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(10_000),
            });
            if (!response.ok) return;

            const rate = await response.json();
            rateBar.hidden = !rate.value;
            if (!rate.value) return;

            price.textContent = rate.price;
            updated.textContent = rate.updated ?? '';
            updated.hidden = !rate.datetime;
            if (rate.datetime) updated.dateTime = rate.datetime;
            else updated.removeAttribute('datetime');
            source.textContent = rate.source ?? '';
            source.hidden = !rate.source;
            for (const panel of panels) {
                const quote = rate.quotes?.[panel.dataset.quoteSlug];
                if (!Number.isSafeInteger(quote?.total)) continue;
                for (const field of panel.querySelectorAll('[data-quote-field]')) {
                    field.textContent = numberFormat.format(quote[field.dataset.quoteField]);
                }
                for (const element of panel.querySelectorAll('[data-quote-price]')) element.hidden = false;
                for (const element of panel.querySelectorAll('[data-quote-missing]')) element.hidden = true;
            }
            if (reload) reload.hidden = rate.value === initialValue || (slugs.size > 0 && Object.keys(rate.quotes ?? {}).length === slugs.size);
        } catch {
            // Keep the last rendered rate when the request fails.
        } finally {
            checking = false;
            scheduleCheck();
        }
    }

    function scheduleCheck() {
        window.clearTimeout(timer);
        if (document.hidden || checking) return;
        timer = window.setTimeout(refreshRate, Math.max(0, minute - (Date.now() - lastCheck)));
    }

    document.addEventListener('visibilitychange', scheduleCheck);
    window.addEventListener('pageshow', scheduleCheck);
    scheduleCheck();
}

const checkoutForm = document.querySelector('[data-checkout-form]');
if (checkoutForm) {
    const updateAddress = () => {
        const shipping = checkoutForm.elements.delivery.value === 'shipping';
        checkoutForm.querySelector('[data-shipping-address]').hidden = !shipping;
        checkoutForm.elements.address.required = shipping;
    };
    checkoutForm.elements.delivery.addEventListener('change', updateAddress);
    updateAddress();
}
