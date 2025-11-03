<template>
  <Transition name="fade">
    <div v-if="open" class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-4 py-6">
      <div class="relative w-full max-w-3xl rounded-2xl border border-slate-800 bg-slate-950/90 p-6 shadow-2xl">
        <button class="absolute right-4 top-4 text-slate-400 hover:text-white" @click="$emit('close')">
          <span class="sr-only">Fechar</span>
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 18 12-12M6 6l12 12" />
          </svg>
        </button>
        <slot />
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
defineProps<{ open: boolean }>();
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
