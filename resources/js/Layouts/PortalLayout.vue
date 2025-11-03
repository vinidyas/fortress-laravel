<template>
  <div class="min-h-screen bg-slate-950 text-slate-100">
    <PortalNotifications />
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600/20 text-indigo-300">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5V9l3 1.5" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25v-7.72a1.125 1.125 0 0 1 .513-.95L11.25 8.5c.46-.287 1.04-.287 1.5 0l6.237 3.08a1.125 1.125 0 0 1 .513.95v7.72" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 20.25h7.5" />
            </svg>
          </div>
          <div>
            <h1 class="text-lg font-semibold tracking-tight text-white">Portal do Locatário</h1>
            <p class="text-xs text-slate-400">Acesse seus boletos, acompanhe pagamentos e emita recibos</p>
          </div>
        </div>
        <div class="flex items-center gap-4 text-sm text-slate-300">
          <div v-if="tenant?.nome" class="text-right">
            <p class="font-medium text-white">{{ tenant.nome }}</p>
            <p class="text-xs text-slate-400">CPF/CNPJ: {{ tenant.cpf_cnpj }}</p>
          </div>
          <a
            href="/logout"
            method="post"
            as="button"
            class="rounded-md border border-slate-700 px-3 py-1.5 text-sm font-semibold text-slate-200 transition hover:border-rose-500/50 hover:bg-rose-500/10 hover:text-rose-200"
            @click.prevent="logout"
          >
            Sair
          </a>
        </div>
      </div>
    </header>

    <div class="mx-auto grid min-h-[calc(100vh-64px)] max-w-6xl grid-cols-1 gap-6 px-6 py-6 lg:grid-cols-[220px,1fr]">
      <nav class="flex flex-col gap-2 rounded-2xl border border-slate-800 bg-slate-900/60 p-4 shadow-xl shadow-black/40">
        <PortalNavLink :href="route('portal.dashboard', undefined, false)" icon="receipt">Minhas faturas</PortalNavLink>
      </nav>

      <main class="rounded-2xl border border-slate-800 bg-slate-900/40 p-6 shadow-inner shadow-black/30">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useTenantStore } from '@/Stores/portal/tenant';
import PortalNavLink from '@/Pages/Portal/Components/PortalNavLink.vue';
import PortalNotifications from '@/Pages/Portal/Components/PortalNotifications.vue';
import { route } from 'ziggy-js';

const tenantStore = useTenantStore();
const tenant = computed(() => tenantStore.profile);
const page = usePage();

const sharedProps = computed(() => {
  const raw = page?.props as unknown;

  if (!raw) return {};

  if (typeof raw === 'object' && raw && 'value' in raw) {
    return ((raw as { value?: Record<string, unknown> }).value ?? {}) as Record<string, unknown>;
  }

  return (raw as Record<string, unknown>) ?? {};
});

const portalTenant = computed(() => (sharedProps.value.portalTenant ?? null) as any);

watch(
  portalTenant,
  (value) => tenantStore.setProfile((value ?? null) as any),
  { immediate: true }
);

function logout() {
  router.post('/logout');
}
</script>
