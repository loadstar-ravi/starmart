/**
 * Cart page: changing a quantity saves it in the background and swaps in the updated cart,
 * so the totals follow without reloading the page. The server checks the stock, and its
 * message is shown when it refuses. Without JavaScript every control is still a normal form.
 */
const GENERIC_ERROR = 'Something went wrong while updating your cart. Please try again.';

const contents = document.querySelector('[data-cart-contents]');
const errors = document.querySelector('[data-cart-errors]');
const loading = document.querySelector('[data-cart-loading]');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

if (contents && errors && loading && csrfToken) {
    let saving = false;

    const showError = (message) => {
        errors.textContent = message;
        errors.hidden = message === '';
    };

    const setSaving = (isSaving) => {
        saving = isSaving;
        loading.hidden = !isSaving;
        contents.setAttribute('aria-busy', String(isSaving));
    };

    const saveQuantity = async (form) => {
        if (saving) {
            return;
        }

        const control = form.dataset.cartQuantity;
        setSaving(true);

        try {
            const response = await fetch(form.action, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ quantity: new FormData(form).get('quantity') }),
            });

            if (response.status === 422) {
                const body = await response.json();
                showError(body.message ?? GENERIC_ERROR);
                form.reset();

                return;
            }

            if (!response.ok) {
                throw new Error(`Unexpected response status ${response.status}`);
            }

            const body = await response.json();

            contents.innerHTML = body.html;
            showError('');

            const count = document.querySelector('[data-cart-count]');

            if (count) {
                count.textContent = body.total_quantity;
            }

            // The clicked control was replaced with the rest of the cart, so keyboard users get their place back.
            contents
                .querySelector(`[data-cart-quantity="${control}"] :is(button, input[type="number"])`)
                ?.focus();
        } catch {
            showError(GENERIC_ERROR);
            form.reset();
        } finally {
            setSaving(false);
        }
    };

    contents.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-cart-quantity]');

        if (!form) {
            return;
        }

        event.preventDefault();
        saveQuantity(form);
    });

    contents.addEventListener('change', (event) => {
        const form = event.target.closest('[data-cart-quantity]');

        if (form && event.target.name === 'quantity') {
            form.requestSubmit();
        }
    });
}
