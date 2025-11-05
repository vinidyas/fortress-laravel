<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types/page';

const page = usePage<PageProps>();
const sharedProps = computed<PageProps>(() => {
  const raw = page?.props as unknown;

  if (!raw) return {} as PageProps;

  if (typeof raw === 'object' && raw && 'value' in raw) {
    return ((raw as { value?: PageProps }).value ?? {}) as PageProps;
  }

  return (raw as PageProps) ?? ({} as PageProps);
});
const portalHost = computed(() => sharedProps.value.portal?.domain ?? 'portal.fortressempreendimentos.com.br');
const isPortal = computed(() => {
  const sharedFlag = sharedProps.value.portal?.isPortalDomain ?? false;

  if (sharedFlag) return true;

  if (typeof window === 'undefined') {
    return false;
  }

  try {
    return window.location.hostname === portalHost.value;
  } catch {
    return false;
  }
});
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-slate-950 text-slate-100">
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900/70 to-slate-950" />
      <div class="absolute -top-32 -left-24 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl" />
      <div class="absolute -bottom-40 -right-32 h-[28rem] w-[28rem] rounded-full bg-violet-500/15 blur-[160px]" />
      <div class="absolute top-1/4 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-indigo-400/10 blur-3xl" />
    </div>

    <div class="relative z-10 flex min-h-screen items-center justify-center px-6 py-12">
      <div class="relative flex w-full max-w-md justify-center">
        <div
          class="relative w-full overflow-hidden rounded-[2rem] border border-slate-800/80 bg-slate-900/75 shadow-2xl shadow-black/40 backdrop-blur-xl"
        >
          <div class="absolute -top-24 -right-16 h-56 w-56 rounded-full bg-indigo-500/15 blur-3xl" />
          <div class="absolute -bottom-32 -left-20 h-64 w-64 rounded-full bg-violet-500/10 blur-3xl" />
          <div class="relative z-10 p-8 sm:p-10">
            <div v-if="isPortal" class="mb-8 space-y-2 text-center">
              <p class="text-sm uppercase tracking-[0.35em] text-indigo-300">Portal do Locatário</p>
              <h1 class="text-2xl font-semibold text-white">Acesse seus boletos e contratos</h1>
              <p class="text-sm text-slate-400">
                Entre com o usuário enviado pela administradora para acompanhar pagamentos e documentos.
              </p>
            </div>
            <div v-else class="mb-8 flex flex-col items-center gap-2 text-center">
              <span class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-900/60">
                <img src="/logo-square.png" alt="Fortress Empreendimentos" class="h-16 w-16 rounded-2xl object-cover shadow-lg shadow-indigo-900/30" />
              </span>
              <p class="text-lg font-semibold text-white">Fortress Empreendimentos</p>
            </div>
            <slot />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
