import type { App, InjectionKey } from 'vue';
import { useNotificationStore } from '@/Stores/notifications';

type ToastType = 'success' | 'error' | 'info';

export interface ToastApi {
  success(message: string, timeout?: number): void;
  error(message: string, timeout?: number): void;
  info(message: string, timeout?: number): void;
}

export const toastKey: InjectionKey<ToastApi> = Symbol('toast');

type Handler = (message: string, timeout?: number) => void;

const createHandler = (type: ToastType): Handler => {
  return (message: string, timeout?: number) => {
    const store = useNotificationStore();

    switch (type) {
      case 'success':
        store.success(message, timeout);
        break;
      case 'error':
        store.error(message, timeout);
        break;
      default:
        store.info(message, timeout);
        break;
    }
  };
};

const createToastApi = (): ToastApi => ({
  success: createHandler('success'),
  error: createHandler('error'),
  info: createHandler('info'),
});

export const toastPlugin = {
  install(app: App) {
    const toast = createToastApi();

    app.config.globalProperties.$toast = toast;
    app.provide(toastKey, toast);
  },
};

export type { ToastApi as ToastPluginApi };
