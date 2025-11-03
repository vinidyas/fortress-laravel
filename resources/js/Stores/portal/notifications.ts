import { defineStore } from 'pinia';
import { ref } from 'vue';

export type PortalAlert = {
  id: string;
  type: 'info' | 'success' | 'warning' | 'error';
  message: string;
  timeout?: number;
};

export const usePortalNotificationStore = defineStore('portalNotifications', () => {
  const alerts = ref<PortalAlert[]>([]);

  function push(alert: PortalAlert) {
    alerts.value.push(alert);
  }

  function remove(id: string) {
    alerts.value = alerts.value.filter((item) => item.id !== id);
  }

  return {
    alerts,
    push,
    remove,
  };
});
