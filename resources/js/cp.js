/*
    The control panel's pages: the shared Templates, Designer and Images
    pages from social-images-core, mounted where the Twig templates leave a
    [data-social-images] element with the page's name and props.
*/
import { createApp } from 'vue';
import Templates from '@social-images/designer/pages/Templates.vue';
import Designer from '@social-images/designer/pages/Designer.vue';
import Images from '@social-images/designer/pages/Images.vue';
import http from './http.js';
import '@social-images/designer/designer.css';
import '../css/cp.css';

const PAGES = { Templates, Designer, Images };

/**
 * Translate as the shared pages do, __('Text :name', { name }), through
 * Craft's translations in the social-images category.
 */
function translate(text, replace = {}) {
    let translated = window.Craft?.t ? window.Craft.t('social-images', text) : text;

    for (const [key, value] of Object.entries(replace).sort(([a], [b]) => b.length - a.length)) {
        translated = translated.split(':' + key).join(String(value));
    }

    return translated;
}

const toast = {
    success: (message) => window.Craft?.cp?.displaySuccess ? window.Craft.cp.displaySuccess(message) : window.Craft?.cp?.displayNotice(message),
    error: (message) => window.Craft?.cp?.displayError(message),
};

for (const element of document.querySelectorAll('[data-social-images]')) {
    const page = PAGES[element.dataset.socialImages];

    if (!page) {
        continue;
    }

    const app = createApp(page, JSON.parse(element.dataset.props || '{}'));

    app.config.globalProperties.__ = translate;
    app.config.globalProperties.$axios = http;
    app.config.globalProperties.$toast = toast;
    app.mount(element);
}
