/// <reference types="vite/client" />

import type { ToastPluginApi } from './plugins/toast';

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    $toast: ToastPluginApi;
  }
}

export {};
declare module '@/Layouts/PortalLayout.vue';
declare module '@/Pages/Portal/Dashboard.vue';
declare module '@/Pages/Portal/Contracts.vue';
declare module '@/Pages/Portal/Invoices.vue';
declare module '@/Stores/portal/tenant.ts';
declare module '@/Stores/portal/contracts.ts';
declare module '@/Stores/portal/invoices.ts';

declare module '@/Pages/Admin/Portal/Tenants.vue';
