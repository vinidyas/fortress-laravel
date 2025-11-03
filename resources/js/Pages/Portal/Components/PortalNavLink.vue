<template>
  <button
    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
    :class="[
      isActive ? 'bg-indigo-600/20 text-indigo-200' : 'text-slate-400 hover:bg-slate-800/70 hover:text-slate-100',
    ]"
    type="button"
    @click="navigate"
  >
    <slot name="icon">
      <svg v-if="icon === 'home'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 7.5-7.5 7.5 7.5" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 9.75v10.5h6v-6h3v6h6V9.75" />
      </svg>
      <svg v-else-if="icon === 'files'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l3 3v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 11h6" />
      </svg>
      <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h10a1.5 1.5 0 0 1 1.494 1.356L18.75 5.25v13.5A1.5 1.5 0 0 1 17.394 20.25L17.25 20.25H6.75a1.5 1.5 0 0 1-1.494-1.356L5.25 18.75V5.25a1.5 1.5 0 0 1 1.356-1.494L6.75 3.75H7z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7.5h6" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 11.25h6" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15h3.75" />
      </svg>
    </slot>
    <span><slot /></span>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps<{ href: string; icon?: string }>();

const page = usePage();

const normalizedHref = computed(() => normalizePath(props.href));
const currentPath = computed(() => normalizePath(page.url));

const isActive = computed(() => {
  if (!normalizedHref.value) return false;

  if (currentPath.value === normalizedHref.value) {
    return true;
  }

  return currentPath.value.startsWith(`${normalizedHref.value}/`);
});

function navigate() {
  router.visit(props.href);
}

function normalizePath(value?: string | null) {
  if (!value) return '/';

  const candidate = value.replace(/\\s+/g, '');

  try {
    const url = new URL(candidate, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
    return tidyPath(url.pathname);
  } catch (error) {
    return tidyPath(candidate.startsWith('/') ? candidate : `/${candidate}`);
  }
}

function tidyPath(path: string) {
  const sanitized = path.replace(/\/+/g, '/');
  const trimmed = sanitized.endsWith('/') && sanitized !== '/' ? sanitized.slice(0, -1) : sanitized;

  return trimmed || '/';
}
</script>
