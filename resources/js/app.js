import './bootstrap';
import '../css/app.css';
import { route } from 'ziggy-js';
import { Ziggy } from './ziggy';
import { toastPlugin } from './plugins/toast';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { InertiaProgress } from '@inertiajs/progress';
import { useNotificationStore } from './Stores/notifications';
import PortalNotifications from '@/Pages/Portal/Components/PortalNotifications.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Fortress Gestao Imobiliaria';

const preferredBackendUrl = import.meta.env.VITE_BACKEND_URL;
const isLocalUrl = (value) => {
  if (!value) return false;
  try {
    const { hostname } = new URL(value);
    return ['127.0.0.1', 'localhost'].includes(hostname);
  } catch {
    return false;
  }
};

let rawBackendUrl = preferredBackendUrl && !isLocalUrl(preferredBackendUrl) ? preferredBackendUrl : null;
if (!rawBackendUrl && typeof window !== 'undefined') {
  rawBackendUrl = window.location.origin;
}
if (!rawBackendUrl || isLocalUrl(rawBackendUrl)) {
  rawBackendUrl = Ziggy.url ?? window.location.origin;
}

try {
  const normalized = rawBackendUrl.replace(/\/+$/, '');
  const parsed = new URL(normalized);
  const runtimeHostname = typeof window !== 'undefined' ? window.location.hostname : null;
  const isRuntimeLocal = runtimeHostname && ['127.0.0.1', 'localhost'].includes(runtimeHostname);
  if (isRuntimeLocal) {
    Ziggy.url = `${parsed.protocol}//${parsed.hostname}`;
    Ziggy.port = parsed.port || null;
  } else if (typeof window !== 'undefined') {
    const currentOrigin = window.location.origin.replace(/\/+$/, '');
    const currentParsed = new URL(currentOrigin);
    Ziggy.url = `${currentParsed.protocol}//${currentParsed.hostname}`;
    Ziggy.port = currentParsed.port || null;
  } else {
    Ziggy.url = `${parsed.protocol}//${parsed.hostname}`;
    Ziggy.port = parsed.port || null;
  }
} catch {
  const fallbackOrigin =
    typeof window !== 'undefined' ? window.location.origin.replace(/\/+$/, '') : rawBackendUrl.replace(/\/+$/, '');
  Ziggy.url = fallbackOrigin;
  Ziggy.port = null;
}

if (typeof window !== 'undefined') {
  Object.assign(window, { Ziggy });
}

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

    vueApp.component('PortalNotifications', PortalNotifications);

    vueApp.mount(el);
  },
});

InertiaProgress.init({ color: '#2563eb', showSpinner: false });
