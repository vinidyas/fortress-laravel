import { defineStore } from 'pinia';

export type NotificationType = 'success' | 'error' | 'info';

export interface NotificationItem {
  id: number;
  message: string;
  type: NotificationType;
  timeout?: number;
}

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    items: [] as NotificationItem[],
  }),
  actions: {
    push(message: string, type: NotificationType = 'info', timeout = 3500) {
      const id = Date.now() + Math.random();
      this.items.push({ id, message, type, timeout });

      if (timeout && timeout > 0) {
        setTimeout(() => this.remove(id), timeout);
      }
    },
    success(message: string, timeout = 3500) {
      this.push(message, 'success', timeout);
    },
    error(message: string, timeout = 5000) {
      this.push(message, 'error', timeout);
    },
    info(message: string, timeout = 3500) {
      this.push(message, 'info', timeout);
    },
    remove(id: number) {
      this.items = this.items.filter((notification) => notification.id !== id);
    },
  },
});
