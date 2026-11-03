import { router } from '@inertiajs/vue3';

/** Filters live in the URL so every view is shareable and survives refresh. */
export function visit(params, options = {}) {
    const url = new URL(window.location.href);
    for (const [key, value] of Object.entries(params)) {
        if (value === null || value === undefined || value === '' || value === false) url.searchParams.delete(key);
        else url.searchParams.set(key, value === true ? '1' : value);
    }
    if (!('page' in params)) url.searchParams.delete('page');
    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true, ...options });
}

export function debounce(fn, ms = 300) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
}
