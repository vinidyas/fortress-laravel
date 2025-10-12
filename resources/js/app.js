import './bootstrap';
import '../css/app.css';
import { toastPlugin } from './plugins/toast';
import { route } from 'ziggy-js';
import { Ziggy } from './ziggy';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { InertiaProgress } from '@inertiajs/progress';
import { useNotificationStore } from './Stores/notifications';

const appName = import.meta.env.VITE_APP_NAME || 'Fortress Gestao Imobiliaria';

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    const pinia = createPinia();

    const vueApp = createApp({
      render: () => h(App, props),
    });

    vueApp.use(plugin);
    vueApp.use(pinia);
    vueApp.use(toastPlugin);

    // Bridge global notifications from axios response interceptor
    const notifications = useNotificationStore();
    window.addEventListener('notify', (event) => {
      try {
        const detail = event && event.detail ? event.detail : {};
        const type = detail.type || 'info';
        const message = detail.message || '';
        const timeout = detail.timeout;

        if (type === 'success') notifications.success(message, timeout);
        else if (type === 'error') notifications.error(message, timeout);
        else notifications.info(message, timeout);
      } catch (_) {}
    });

    // Make Ziggy routes available globally for route() calls
    if (typeof window !== 'undefined') {
      Object.assign(window, { Ziggy });
    }

    vueApp.mount(el);
  },
});

InertiaProgress.init({ color: '#2563eb', showSpinner: false });
