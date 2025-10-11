/// <reference types="vite/client" />

import type { ToastPluginApi } from './plugins/toast';

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    $toast: ToastPluginApi;
  }
}

export {};
