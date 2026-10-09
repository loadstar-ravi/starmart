/**
 * Forms marked data-submit-once switch their button off once they are sent, so a double click
 * cannot send them twice. The second request would find the order already placed or paid, and
 * its error message would replace the page the customer should see. The button is switched
 * back on when the page is shown again, which covers coming back with the browser's Back button.
 */
document.querySelectorAll('[data-submit-once]').forEach((form) => {
    const button = form.querySelector('[type="submit"]');

    if (!button) {
        return;
    }

    const label = button.textContent;

    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }

        button.disabled = true;
        button.textContent = button.dataset.busyLabel ?? label;
    });

    window.addEventListener('pageshow', () => {
        button.disabled = false;
        button.textContent = label;
    });
});
