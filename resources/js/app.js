import "./bootstrap";
import "../css/app.css";
import { toastPlugin } from "./plugins/toast";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { createPinia } from "pinia";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { InertiaProgress } from "@inertiajs/progress";

const appName = import.meta.env.VITE_APP_NAME || "Fortress Gestao Imobiliaria";

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob("./Pages/**/*.vue")),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();

        const vueApp = createApp({
            render: () => h(App, props),
        });

        vueApp.use(plugin);
        vueApp.use(pinia);
        vueApp.use(toastPlugin);

        vueApp.mount(el);
    },
});

InertiaProgress.init({ color: "#2563eb", showSpinner: false });



