import { inject } from 'vue';
import { toastKey, type ToastPluginApi } from '@/plugins/toast';
import { useNotificationStore } from '@/Stores/notifications';

const createFallbackToast = (): ToastPluginApi => {
  const store = useNotificationStore();

  return {
    success(message, timeout) {
      store.success(message, timeout);
    },
    error(message, timeout) {
      store.error(message, timeout);
    },
    info(message, timeout) {
      store.info(message, timeout);
    },
  };
};

export const useToast = (): ToastPluginApi => {
  const injected = inject(toastKey, null);

  return injected ?? createFallbackToast();
};
