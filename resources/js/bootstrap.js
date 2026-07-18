import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.getShopifySessionToken = async () => {
    const isEmbedded = window.self !== window.top;

    if (!isEmbedded || !window.shopify || typeof window.shopify.idToken !== 'function') {
        return null;
    }

    return window.shopify.idToken();
};

window.axios.interceptors.request.use(async (config) => {
    const token = await window.getShopifySessionToken();

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});
