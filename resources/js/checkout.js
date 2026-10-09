/**
 * Checkout: the Place order button is switched off once the form is sent, so a double click
 * cannot send it twice and leave the customer looking at an "empty cart" message for an order
 * that was in fact placed. It is switched back on when the page is shown again, which covers
 * coming back with the browser's Back button.
 */
const form = document.querySelector('[data-checkout-form]');
const button = form?.querySelector('[data-checkout-submit]');

if (form && button) {
    const label = button.textContent;

    form.addEventListener('submit', () => {
        button.disabled = true;
        button.textContent = 'Placing order…';
    });

    window.addEventListener('pageshow', () => {
        button.disabled = false;
        button.textContent = label;
    });
}
