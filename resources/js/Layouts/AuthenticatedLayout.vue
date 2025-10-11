?<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted } from 'vue';
import { useNotificationStore } from '@/Stores/notifications';
import { route } from 'ziggy-js';

const props = defineProps<{ title: string }>();

interface NavItem {
  key: string;
  label: string;
  href?: string;
  icon?: string;
  ability?: string;
  exact?: boolean;
  children?: NavItem[];
}

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const abilities = computed<string[]>(() => (page.props.auth?.abilities ?? []) as string[]);
const csrfToken = computed(() => page.props.csrf_token ?? '');
const currentUrl = computed(() => page.url ?? '');
const isSidebarOpen = ref(false);
const isCollapsed = ref(true);
const pinned = ref(false);
const notificationStore = useNotificationStore();
const notifications = computed(() => notificationStore.items);
const userMenuOpen = ref(false);
const expanded = ref<Record<string, boolean>>({});
const toggleExpanded = (key: string) => { expanded.value[key] = !expanded.value[key]; };
onMounted(() => {
  // abre a seção do item ativo
  try {
    const url = new URL(window.location.href);
    const path = url.pathname;
    for (const item of navItems.value) {
      if (item.children?.length) {
        expanded.value[item.key] = item.children.some((c) => typeof c.href === 'string' && (new URL(c.href, window.location.origin)).pathname && path.startsWith((new URL(c.href, window.location.origin)).pathname));
      }
    }
  } catch {}
});

const can = (permission?: string) => !permission || abilities.value.includes(permission);

const navItems = computed<NavItem[]>(() => {
  const items: NavItem[] = [
    {
      key: 'dashboard',
      label: 'Dashboard',
      href: route('dashboard'),
      icon: 'M3 12h18M3 6h18M3 18h18',
      exact: true,
    },
    {
      key: 'imoveis',
      label: 'Imóveis',
      href: route('imoveis.index'),
      icon: 'M4 12l8-6 8 6v8a2 2 0 01-2 2H6a2 2 0 01-2-2z',
      ability: 'imoveis.view',
    },
    {
      key: 'pessoas',
      label: 'Pessoas',
      href: route('pessoas.index'),
      icon: 'M5.5 17a6.5 6.5 0 0113 0M12 9a4 4 0 110-8 4 4 0 010 8z',
      ability: 'pessoas.view',
    },
    {
      key: 'contratos',
      label: 'Contratos',
      href: route('contratos.index'),
      icon: 'M7 7h10M7 12h10M7 17h6',
      ability: 'contratos.view',
    },
    {
      key: 'faturas',
      label: 'Faturas',
      href: route('faturas.index'),
      icon: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
      ability: 'faturas.view',
    },
    {
      key: 'financeiro',
      label: 'Financeiro',
      href: route('financeiro.index'),
      icon: 'M3 3h18M5 7h14v12H5z',
      ability: 'financeiro.view',
      children: [
        {
          key: 'financeiro-accounts',
          label: 'Contas',
          href: route('financeiro.accounts'),
          ability: 'financeiro.view',
        },
        {
          key: 'financeiro-centers',
          label: 'Centros de Custo',
          href: route('financeiro.cost-centers'),
          ability: 'financeiro.view',
        },
        {
          key: 'financeiro-lancamentos',
          label: 'Lançamentos',
          href: route('financeiro.index'),
          ability: 'financeiro.view',
        },
        {
          key: 'financeiro-schedules',
          label: 'Agendamentos',
          href: route('financeiro.payment-schedules'),
          ability: 'financeiro.view',
        },
      ],
    },
    {
      key: 'auditoria',
      label: 'Auditoria',
      href: route('auditoria.index'),
      icon: 'M4 5h16M4 12h16M4 19h16',
      ability: 'auditoria.view',
      children: [
        {
          key: 'auditoria-logs',
          label: 'Logs de Auditoria',
          href: route('auditoria.index'),
          ability: 'auditoria.view',
        },
      ],
    },
    {
      key: 'relatorios',
      label: 'Relatórios',
      icon: 'M3 4h18l-2 14H5zM9 2h6v4H9z',
      children: [
        {
          key: 'relatorios-financeiro',
          label: 'Relatório Financeiro',
          href: route('relatorios.financeiro'),
          ability: 'reports.view.financeiro',
        },
        {
          key: 'relatorios-operacional',
          label: 'Relatório Operacional',
          href: route('relatorios.operacional'),
          ability: 'reports.view.operacional',
        },
        {
          key: 'relatorios-pessoas',
          label: 'Relatório de Pessoas',
          href: route('relatorios.pessoas'),
          ability: 'reports.view.pessoas',
        },
      ],
    },
  ];

  return items.filter((item) => can(item.ability));
});

const isLinkActive = (href?: string, exact?: boolean) => {
  if (!href) return false;
  return exact ? currentUrl.value === href : currentUrl.value.startsWith(href);
};

const itemClasses = (active: boolean) =>
  [
    'group flex items-center gap-3 rounded-xl px-4 py-2.5 transition',
    active
      ? 'bg-indigo-500/15 text-white ring-1 ring-inset ring-indigo-500/40'
      : 'text-slate-300 hover:bg-slate-900/60 hover:text-white',
  ].join(' ');

const childItemClasses = (active: boolean) =>
  [
    active ? 'text-indigo-300' : 'text-slate-400 hover:text-indigo-200',
  ].join(' ');

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

// Persistir prefer�ncia de pin do menu lateral
if (typeof window !== 'undefined') {
  try {
    const saved = localStorage.getItem('sidebarPinned');
    pinned.value = saved === 'true';
    // Se estiver fixado, come�a expandido
    if (pinned.value) {
      isCollapsed.value = false;
    }
  } catch {}
}

const togglePin = () => {
  pinned.value = !pinned.value;
  try {
    localStorage.setItem('sidebarPinned', String(pinned.value));
  } catch {}
  if (!pinned.value) {
    // Ao desafixar, recolhe novamente
    isCollapsed.value = true;
  } else {
    isCollapsed.value = false;
  }
};
</script>

<template>
  <div class="app-compact min-h-screen w-full bg-slate-950 overflow-x-hidden transition-all"
    :class="isCollapsed ? 'lg:pl-20' : 'lg:pl-80'"
  >
    <aside
      class="fixed left-0 top-0 z-40 h-full -translate-x-full border-r border-slate-800 bg-slate-950/95 p-4 text-slate-300 backdrop-blur transition-all duration-200 lg:translate-x-0"
      :class="[isCollapsed ? 'w-20' : 'w-80', { 'translate-x-0': isSidebarOpen }]"
      @mouseenter="isCollapsed = false"
      @mouseleave="!pinned && (isCollapsed = true)"
    >
      <div class="flex h-full flex-col">
        <div class="mb-4 flex items-center justify-between">
          <Link href="/" class="inline-flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white">FG</div>
            <div v-if="!isCollapsed" class="transition-opacity">
              <h2 class="text-lg font-semibold text-white">Fortress</h2>
              <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Gest�o Imobili�ria</p>
            </div>
          </Link>
          

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2 text-sm font-medium text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 lg:hidden"
            @click="toggleSidebar"
          >
            <svg
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M3 6h18M3 18h18" />
            </svg>
            Fechar
          </button>
          <button
            type="button"
            class="hidden lg:inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2 text-xs font-medium text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20"
            @click="togglePin"
            :title="pinned ? 'Soltar menu (recolher ao sair)' : 'Fixar menu (sempre aberto)'"
          >
            <svg v-if="!pinned" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 00-.586 1.414V17h1.999a2 2 0 001.414-.586L18 9.828m0 0L14.172 6M18 9.828V4"/></svg>
            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
            <span v-if="!isCollapsed">{{ pinned ? 'Soltar' : 'Fixar' }}</span>
          </button>
        </div>

        <nav class="flex-1 overflow-y-auto">
          <ul class="space-y-1">
            <li v-for="item in navItems" :key="item.key">
              <Link
                v-if="item.href && can(item.ability)"
                :href="item.href"
                class="group"
                :class="itemClasses(isLinkActive(item.href, item.exact))"
                @click="isSidebarOpen = false"
              >
                <svg class="h-5 w-5 text-slate-400 group-hover:text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span v-if="!isCollapsed" class="font-medium">{{ item.label }}</span>
              </Link>

              <div v-else class="px-4 py-2 text-slate-400" v-if="!isCollapsed">{{ item.label }}</div>

              <button
                v-if="item.children?.length && !isCollapsed"
                type="button"
                class="ml-10 mb-1 inline-flex items-center gap-1 text-xs text-slate-400 hover:text-slate-200"
                @click.stop="toggleExpanded(item.key)"
              >
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <span>{{ expanded[item.key] ? 'Recolher' : 'Expandir' }}</span>
              </button>

              <ul v-if="item.children?.length && !isCollapsed" class="mt-1 space-y-1 pl-11" v-show="expanded[item.key]">
                <li v-for="child in item.children" :key="child.key">
                  <Link
                    v-if="child.href"
                    :href="child.href"
                    class="group flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm"
                    :class="childItemClasses(isLinkActive(child.href, child.exact))"
                    @click="isSidebarOpen = false"
                  >
                    <span
                      class="h-1.5 w-1.5 rounded-full bg-slate-500 transition group-hover:bg-indigo-300"
                      :class="{ 'bg-indigo-300': isLinkActive(child.href, child.exact) }"
                    />
                    <span>{{ child.label }}</span>
                  </Link>
                </li>
              </ul>
            </li>
          </ul>
        </nav>

        <div v-if="false" class="border-t border-slate-800 px-4 py-5 text-sm">
          <div
            v-if="user"
            class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4 shadow-inner shadow-black/30"
          >
            <p class="font-semibold text-white">{{ user.nome }}</p>
            <p class="text-xs text-slate-400">{{ user.username }}</p>
            <form class="mt-4" method="post" action="/logout">
              <input type="hidden" name="_token" :value="csrfToken" />
              <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-500/40 bg-rose-500/15 px-3 py-2 text-sm font-medium text-rose-200 transition hover:border-rose-400 hover:bg-rose-500/25"
              >
                <svg
                  class="h-4 w-4"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 17h5l-3 3m3-3l-3-3m3 3h-8a4 4 0 01-4-4V5a2 2 0 012-2h3"
                  />
                </svg>
                Sair
              </button>
            </form>
          </div>
          <div class="hidden sm:flex items-center gap-3">
            <div v-if="user" class="relative">
              <button type="button" @click="userMenuOpen = !userMenuOpen"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2 text-sm font-medium text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20">
                <div class="flex h-6 w-6 items-center justify-center rounded bg-indigo-600 text-white text-xs">
                  {{ (user.nome || user.username || 'U').substring(0,2).toUpperCase() }}
                </div>
                <span class="hidden xl:inline">{{ user.nome || user.username }}</span>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
              </button>
              <div v-show="userMenuOpen" class="absolute right-0 mt-2 w-44 overflow-hidden rounded-xl border border-slate-700 bg-slate-900/95 text-sm shadow-xl shadow-black/40">
                <form method="POST" action="/logout">
                  <input type="hidden" name="_token" :value="csrfToken" />
                  <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-slate-200 hover:bg-slate-800">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-3 3m3-3l-3-3m3 3H9a4 4 0 01-4-4V5a2 2 0 012-2h3"/></svg>
                    Sair
                  </button>
                </form>
              </div>
            </div>
          </div>
          <div
            v-if="false"
            class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-300"
          >
            <p>Acesso restrito. Fa�a login para continuar.</p>
            <Link
              class="mt-2 inline-flex items-center font-medium text-indigo-300 hover:text-indigo-200"
              href="/login"
            >
              Entrar
            </Link>
          </div>
        </div>
      </div>
    </aside>

    <div class="flex w-full flex-col bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 ">
      <header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
        <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-10">
          <div>
            <h1 class="text-xl font-semibold text-white">{{ props.title }}</h1>
            <p class="text-sm text-slate-400">Vis�o geral do Fortress Gest�o Imobili�ria</p>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2 text-sm font-medium text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 lg:hidden"
            @click="toggleSidebar"
          >
            <svg
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M3 6h18M3 18h18" />
            </svg>
            Menu
          </button>
        </div>
      </header>

      <main class="flex-1 px-4 py-8 sm:px-6 lg:px-12">
        <slot />
      </main>

      <footer class="border-t border-slate-800 bg-slate-950/90 backdrop-blur">
        <div class="px-4 py-4 text-sm text-slate-500 sm:px-6 lg:px-10">
          <template v-if="user"> Logado como {{ user.nome }} </template>
          <template v-else> Não autenticado </template>
        </div>
      </footer>
    </div>

    <transition-group
      name="toast"
      tag="div"
      class="fixed bottom-4 right-4 z-50 flex w-full max-w-xs flex-col gap-3 px-4 sm:px-0"
    >
      <div
        v-for="notification in notifications"
        :key="notification.id"
        :class="[
          'relative rounded-xl border px-4 py-3 text-sm shadow-lg shadow-black/40 backdrop-blur transition',
          notification.type === 'success'
            ? 'border-emerald-500/40 bg-emerald-500/15 text-emerald-100'
            : '',
          notification.type === 'error' ? 'border-rose-500/40 bg-rose-500/15 text-rose-100' : '',
          notification.type === 'info' ? 'border-slate-500/40 bg-slate-500/15 text-slate-100' : '',
        ]"
      >
        <button
          type="button"
          class="absolute right-2 top-2 text-xs text-slate-400 transition hover:text-white"
          @click="notificationStore.remove(notification.id)"
        >
          fechar
        </button>
        {{ notification.message }}
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>

<style>
/* Densidade compacta global aplicada pelo wrapper .app-compact */
.app-compact table th,
.app-compact table td {
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
}
.app-compact .p-6 { padding: 1rem !important; }
.app-compact header.sticky .py-4 { padding-top: .5rem !important; padding-bottom: .5rem !important; }
</style>



