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
    collectionNavigation.setAttribute('role', 'tablist');

    const select = (index, focus = false) => {
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
}
