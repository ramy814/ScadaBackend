import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ar from '../lang/ar.js';
import en from '../lang/en.js';

const dictionaries = { ar, en };

// Keys may themselves contain dots (e.g. 'wells.readings.delete'), so try the longest segment first.
function find(node, parts) {
    if (!parts.length) return node;
    if (!node || typeof node !== 'object') return undefined;
    for (let i = parts.length; i > 0; i--) {
        const key = parts.slice(0, i).join('.');
        if (key in node) {
            const found = find(node[key], parts.slice(i));
            if (found !== undefined) return found;
        }
    }
    return undefined;
}

const lookup = (dict, key) => find(dict, key.split('.'));

/** t('fleet.queue.awaiting', { count: 3 }) — falls back to English, then to the key itself. */
export function translate(locale, key, params = {}) {
    let text = lookup(dictionaries[locale] ?? ar, key) ?? lookup(en, key) ?? key;
    if (typeof text !== 'string') return key;
    // Whole-token replacement so ':to' never eats the start of ':total'.
    return text.replace(/:([a-z_]+)/gi, (match, name) => (name in params ? String(params[name]) : match));
}

// Latin digits everywhere (project convention), localized words and order.
const intlLocale = (locale) => (locale === 'ar' ? 'ar-u-nu-latn' : 'en-GB');

export function useI18n() {
    const page = usePage();
    const locale = computed(() => page.props.locale ?? 'ar');

    const t = (key, params) => translate(locale.value, key, params);
    const num = (value, digits = 0) =>
        value === null || value === undefined || value === '' ? '—'
            : new Intl.NumberFormat('en-US', { maximumFractionDigits: digits, minimumFractionDigits: 0 }).format(Number(value));
    const date = (iso, style = 'datetime') => {
        if (!iso) return '—';
        const options = style === 'date' ? { dateStyle: 'medium' } : style === 'time' ? { timeStyle: 'short' } : { dateStyle: 'medium', timeStyle: 'short' };
        return new Intl.DateTimeFormat(intlLocale(locale.value), options).format(new Date(iso));
    };
    const relative = (iso) => {
        if (!iso) return '—';
        const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
        const units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]];
        const [unit, size] = units.find(([, s]) => Math.abs(seconds) >= s) ?? ['second', 1];
        return new Intl.RelativeTimeFormat(intlLocale(locale.value), { numeric: 'auto' }).format(Math.round(seconds / size), unit);
    };
    const duration = (minutes) => {
        if (minutes === null || minutes === undefined) return '—';
        const h = Math.floor(minutes / 60);
        const m = Math.round(minutes % 60);
        return h ? t('common.hours_minutes', { h, m }) : t('common.minutes', { m });
    };

    return { t, num, date, relative, duration, locale };
}
