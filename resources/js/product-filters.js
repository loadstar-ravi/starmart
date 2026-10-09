/**
 * Product listing filters: fetches the filtered results as JSON and swaps them into the
 * page, so searching, filtering, sorting and paging never reload the whole page.
 * Without JavaScript the form still works as a normal GET form.
 */
const SEARCH_DEBOUNCE_MS = 350;
const GENERIC_ERROR = 'Something went wrong while loading products. Please try again.';

const form = document.querySelector('[data-product-filters]');
const results = document.querySelector('[data-product-results]');
const errors = document.querySelector('[data-product-errors]');
const loading = document.querySelector('[data-product-loading]');

if (form && results && errors && loading) {
    let activeRequest = null;
    let searchTimer = null;

    const filterUrl = () => {
        const url = new URL(form.action);

        for (const [name, value] of new FormData(form)) {
            if (typeof value === 'string' && value.trim() !== '') {
                url.searchParams.set(name, value.trim());
            }
        }

        return url;
    };

    const showErrors = (messages) => {
        errors.replaceChildren(
            ...messages.map((message) => {
                const line = document.createElement('p');
                line.textContent = message;

                return line;
            }),
        );
        errors.hidden = messages.length === 0;
    };

    const setLoading = (isLoading) => {
        loading.hidden = !isLoading;
        results.setAttribute('aria-busy', String(isLoading));
    };

    const loadResults = async (url, { updateHistory = true } = {}) => {
        activeRequest?.abort();

        const request = new AbortController();
        activeRequest = request;
        setLoading(true);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: request.signal,
            });

            if (response.status === 422) {
                const body = await response.json();
                showErrors(Object.values(body.errors ?? {}).flat());

                return;
            }

            if (!response.ok) {
                throw new Error(`Unexpected response status ${response.status}`);
            }

            const body = await response.json();

            results.innerHTML = body.html;
            showErrors([]);

            if (updateHistory && url.href !== window.location.href) {
                window.history.pushState({}, '', url);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                showErrors([GENERIC_ERROR]);
            }
        } finally {
            if (activeRequest === request) {
                activeRequest = null;
                setLoading(false);
            }
        }
    };

    const syncFormWithUrl = () => {
        const params = new URLSearchParams(window.location.search);

        for (const field of form.elements) {
            if (!field.name) {
                continue;
            }

            if (field instanceof HTMLSelectElement) {
                const value = params.get(field.name);
                const option = [...field.options].find((candidate) => candidate.value === value);

                field.selectedIndex = option ? option.index : 0;
            } else {
                field.value = params.get(field.name) ?? '';
            }
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(searchTimer);
        loadResults(filterUrl());
    });

    form.addEventListener('change', () => {
        clearTimeout(searchTimer);
        loadResults(filterUrl());
    });

    form.addEventListener('input', (event) => {
        if (event.target.name !== 'search') {
            return;
        }

        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadResults(filterUrl()), SEARCH_DEBOUNCE_MS);
    });

    results.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-product-pagination] a[href]');

        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        await loadResults(new URL(link.href));
        results.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    window.addEventListener('popstate', () => {
        syncFormWithUrl();
        loadResults(new URL(window.location.href), { updateHistory: false });
    });
}
