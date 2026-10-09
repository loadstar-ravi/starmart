/**
 * Product details gallery: clicking a thumbnail shows that image in the main frame.
 */
const gallery = document.querySelector('[data-product-gallery]');
const mainImage = gallery?.querySelector('[data-gallery-main]');

if (gallery && mainImage) {
    gallery.addEventListener('click', (event) => {
        const thumbnail = event.target.closest('[data-gallery-thumb]');

        if (!thumbnail) {
            return;
        }

        mainImage.src = thumbnail.dataset.galleryThumb;

        gallery.querySelectorAll('[data-gallery-thumb]').forEach((button) => {
            button.setAttribute('aria-current', String(button === thumbnail));
        });
    });
}
