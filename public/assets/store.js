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
