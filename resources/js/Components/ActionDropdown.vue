<template>
  <div class="relative inline-flex" ref="root">
    <button
      type="button"
      class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
      :class="buttonClass"
      @click="toggle"
    >
      <slot name="label">
        Ações
      </slot>
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9l3.75 3.75L15.75 9" />
      </svg>
    </button>

    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="open"
        class="absolute right-0 z-40 mt-2 w-52 rounded-xl border border-slate-800 bg-slate-900/95 p-2 shadow-xl"
      >
        <button
          v-for="action in actions"
          :key="action.label"
          type="button"
          class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-100 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="action.disabled"
          @click="() => handleAction(action)"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path
              v-for="(d, index) in iconMap[action.icon ?? 'document']"
              :key="index"
              stroke-linecap="round"
              stroke-linejoin="round"
              :d="d"
            />
          </svg>
          <span>{{ action.label }}</span>
        </button>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

type IconName = 'download' | 'email' | 'refresh' | 'document' | 'ticket' | 'copy';

export type DropdownAction = {
  label: string;
  icon?: IconName;
  disabled?: boolean;
  action: () => void;
};

const props = defineProps<{
  actions: DropdownAction[];
  buttonClass?: string;
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const iconMap: Record<IconName, string[]> = {
  download: ['M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1', 'M7 10l5 5 5-5M12 4v11'],
  email: ['M3 8l7.89 5.26a1 1 0 001.22 0L20 8', 'M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z'],
  refresh: ['M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9', 'M20 20v-5h-.581m0 0A8.003 8.003 0 015.022 15m0 0H4'],
  document: ['M7 3h8l6 6v12a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z'],
  ticket: ['M4 7h16', 'M4 17h16', 'M12 7v10'],
  copy: ['M9 9h13v13H9z', 'M4 15H3a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1'],
};

function toggle() {
  open.value = !open.value;
}

function handleAction(action: DropdownAction) {
  action.action();
  open.value = false;
}

function onClickOutside(event: MouseEvent) {
  if (!root.value) return;
  if (!root.value.contains(event.target as Node)) {
    open.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>
