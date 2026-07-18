import './bootstrap';
import '../css/app.css';

import { createInertiaApp, http } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

http.onRequest(async (config) => {
    const token = await window.getShopifySessionToken?.();

    if (token) {
        config.headers = {
            ...(config.headers || {}),
            Authorization: `Bearer ${token}`,
        };
    }

    return config;
});

createInertiaApp({
    title: (title) => (title ? `${title} - SEO & AEO Content Generator` : 'SEO & AEO Content Generator'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0f766e',
    },
});
