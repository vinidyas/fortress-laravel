<template>
  <div class="fixed right-4 top-4 z-50 flex w-80 flex-col gap-3">
    <TransitionGroup name="toast" tag="div">
      <div
        v-for="alert in notifications.alerts"
        :key="alert.id"
        class="rounded-lg border px-4 py-3 text-sm shadow-lg"
        :class="toastClass(alert.type)"
      >
        <div class="flex items-start justify-between gap-3">
          <span class="font-medium">{{ title(alert.type) }}</span>
          <button class="text-xs text-slate-200/70 hover:text-slate-50" @click="dismiss(alert.id)">Fechar</button>
        </div>
        <p class="mt-1 text-sm">{{ alert.message }}</p>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { usePortalNotificationStore } from '@/Stores/portal/notifications';

const notifications = usePortalNotificationStore();

function dismiss(id: string) {
  notifications.remove(id);
}

function toastClass(type: string) {
  switch (type) {
    case 'success':
      return 'border-emerald-500/50 bg-emerald-500/20 text-emerald-100 backdrop-blur';
    case 'error':
      return 'border-rose-500/50 bg-rose-500/20 text-rose-100 backdrop-blur';
    case 'warning':
      return 'border-amber-500/50 bg-amber-500/20 text-amber-100 backdrop-blur';
    default:
      return 'border-slate-700/50 bg-slate-800/70 text-slate-100 backdrop-blur';
  }
}

function title(type: string) {
  switch (type) {
    case 'success':
      return 'Sucesso';
    case 'error':
      return 'Erro';
    case 'warning':
      return 'Alerta';
    default:
      return 'Informação';
  }
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.18s ease-out;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
