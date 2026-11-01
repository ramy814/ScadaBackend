import '@fontsource/ibm-plex-sans-arabic/400.css';
import '@fontsource/ibm-plex-sans-arabic/500.css';
import '@fontsource/ibm-plex-sans-arabic/600.css';
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppShell from './Layouts/AppShell.vue';

const standalone = ['Auth/', 'Errors/'];

createInertiaApp({
    title: (title) => (title ? `${title} · MOG` : 'MOG Operations Platform'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];
        page.default.layout ??= standalone.some((prefix) => name.startsWith(prefix)) ? undefined : AppShell;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: 'var(--accent)', showSpinner: false },
});
