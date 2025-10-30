<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useNotificationStore } from '@/Stores/notifications';
import { route } from 'ziggy-js';
import axios from '@/bootstrap';

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

interface AuthUser {
  id: number;
  username: string;
  nome: string;
  ativo: boolean;
  avatar_url?: string | null;
}

const page = usePage<{
  auth?: { user?: AuthUser | null; abilities?: string[] };
  csrf_token?: string;
}>();

const user = computed<AuthUser | null>(() => page.props.auth?.user ?? null);
const abilities = computed<string[]>(() => page.props.auth?.abilities ?? []);
const csrfToken = computed(() => page.props.csrf_token ?? '');
const currentUrl = computed(() => page.url ?? '');

const isSidebarOpen = ref(false);
const isCollapsed = ref(true);
const pinned = ref(false);

const notificationStore = useNotificationStore();
const notifications = computed(() => notificationStore.items);

const userMenuOpen = ref(false);
const userMenuContainer = ref<HTMLDivElement | null>(null);
const userMenuButton = ref<HTMLButtonElement | null>(null);
const menuStyles = ref<Record<string, string>>({ top: '0px', right: '0px' });

const userDisplayName = computed(() => user.value?.nome ?? 'Usuário');
const userLogin = computed(() => user.value?.username ?? '');
const userAvatar = computed(() => user.value?.avatar_url ?? null);
const userInitials = computed(() => {
  if (!user.value) return '';
  const source = user.value.nome?.trim() || user.value.username?.trim() || '';
  if (!source) return '';
  const parts = source.split(/\s+/).filter(Boolean);
  const initials = parts.slice(0, 2).map((segment) => segment.charAt(0)).join('') || source.slice(0, 2);
  return initials.toUpperCase();
});

const updateMenuPosition = () => {
  if (!userMenuOpen.value) return;
  if (typeof window === 'undefined') return;
  const trigger = userMenuButton.value;
  if (!trigger) return;
  const rect = trigger.getBoundingClientRect();
  const top = rect.bottom + window.scrollY + 12; // 12px matches mt-3 spacing
  const right = window.innerWidth - rect.right + window.scrollX;
  menuStyles.value = {
    top: `${top}px`,
    right: `${Math.max(right, 16)}px`,
  };
};

const closeUserMenu = () => { userMenuOpen.value = false; };
const toggleUserMenu = () => {
  userMenuOpen.value = !userMenuOpen.value;
  if (userMenuOpen.value) {
    nextTick(updateMenuPosition);
  }
};

const handleClickOutside = (event: MouseEvent) => {
  if (!userMenuOpen.value || !userMenuContainer.value) return;
  const target = event.target as Node | null;
  if (target && !userMenuContainer.value.contains(target) && target !== userMenuButton.value) {
    closeUserMenu();
  }
};

const handleEscape = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    closeUserMenu();
  }
};

const expanded = ref<Record<string, boolean>>({});
const toggleExpanded = (key: string) => { expanded.value[key] = !expanded.value[key]; };

onMounted(() => {
  // Abrir automaticamente a seção do item ativo
  try {
    const url = new URL(window.location.href);
    const path = url.pathname;
    for (const item of navItems.value) {
      if (item.children?.length) {
        expanded.value[item.key] = item.children.some((c) => {
          if (!c.href) return false;
          const childPath = new URL(c.href, window.location.origin).pathname;
          return path.startsWith(childPath);
        });
      }
    }
  } catch {}

  if (typeof document !== 'undefined') {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('resize', updateMenuPosition);
    window.addEventListener('scroll', updateMenuPosition, true);
  }

  if (canViewBalances.value) {
    void loadSidebarBalances();
  }
});

onBeforeUnmount(() => {
  if (typeof document !== 'undefined') {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleEscape);
  }

  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateMenuPosition);
    window.removeEventListener('scroll', updateMenuPosition, true);
  }
});

const can = (permission?: string) => !permission || abilities.value.includes(permission);

const navItems = computed<NavItem[]>(() => {
  const r = (name: string, params?: any, fallback?: string) => {
    try { return route(name, params as any); } catch { return fallback ?? '#'; }
  };
  const items: NavItem[] = [
    { key: 'dashboard', label: 'Dashboard', href: r('dashboard','', '/'), icon: 'M3 12h18M3 6h18M3 18h18', exact: true },
    { key: 'cadastros', label: 'Cadastros', href: r('cadastros.index', undefined, '/cadastros'), icon: 'M4 5h16M4 10h16M4 15h16' },
    { key: 'imoveis', label: 'Imóveis', href: r('imoveis.index', undefined, '/imoveis'), icon: 'M4 12l8-6 8 6v8a2 2 0 01-2 2H6a2 2 0 01-2-2z', ability: 'imoveis.view' },
    { key: 'contratos', label: 'Contratos', href: r('contratos.index', undefined, '/contratos'), icon: 'M7 7h10M7 12h10M7 17h6', ability: 'contratos.view' },
    { key: 'faturas', label: 'Faturas', href: r('faturas.index', undefined, '/faturas'), icon: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01', ability: 'faturas.view' },
    {
      key: 'financeiro',
      label: 'Financeiro',
      href: route('financeiro.index'),
      icon: 'M3 3h18M5 7h14v12H5z',
      ability: 'financeiro.view',
      children: [
        { key: 'financeiro-accounts', label: 'Contas', href: route('financeiro.accounts'), ability: 'financeiro.view' },
        { key: 'financeiro-centers', label: 'Centros de Custo', href: route('financeiro.cost-centers'), ability: 'financeiro.view' },
        { key: 'financeiro-lancamentos', label: 'Lançamentos', href: route('financeiro.index'), ability: 'financeiro.view' },
      ],
    },
    {
      key: 'auditoria',
      label: 'Auditoria',
      href: route('auditoria.index'),
      icon: 'M4 5h16M4 12h16M4 19h16',
      ability: 'auditoria.view',
      children: [ { key: 'auditoria-logs', label: 'Logs de Auditoria', href: route('auditoria.index'), ability: 'auditoria.view' } ],
    },
    { key: 'alerts-history', label: 'Histórico de Alertas', href: route('alerts.history'), icon: 'M13 16h-1v-4h-1m1-4h.01M12 4a8 8 0 100 16 8 8 0 000-16z', ability: 'alerts.view' },
    {
      key: 'admin',
      label: 'Administração',
      icon: 'M4 6h16M6 10h12M8 14h8M10 18h4',
      ability: 'admin.access',
      children: [
        { key: 'admin-dashboard', label: 'Painel', href: r('admin.dashboard', undefined, '/admin'), ability: 'admin.access' },
        { key: 'admin-users', label: 'Usuários', href: r('admin.users.index', undefined, '/admin/usuarios'), ability: 'admin.access' },
        { key: 'admin-roles', label: 'Papéis & Permissões', href: r('admin.roles.index', undefined, '/admin/roles'), ability: 'admin.access' },
      ],
    },
    {
      key: 'relatorios',
      label: 'Relatórios',
      icon: 'M3 4h18l-2 14H5zM9 2h6v4H9z',
      children: [
        { key: 'relatorios-extrato-conta', label: 'Extrato por Conta', href: r('relatorios.bank-account-statement', undefined, '/relatorios/extratos/conta'), ability: 'reports.view.financeiro' },
        { key: 'relatorios-geral-analitico', label: 'Relatório Geral Analítico', href: route('relatorios.general-analytic'), ability: 'reports.view.financeiro' },
        { key: 'relatorios-extrato-detalhado', label: 'Relatório de Despesas e Receitas', href: route('relatorios.bank-ledger'), ability: 'reports.view.financeiro' },
      ],
    },
  ];

  return items.filter((item) => can(item.ability));
});

const canViewBalances = computed(
  () =>
    abilities.value.includes('financeiro.balance.view') ||
    abilities.value.includes('financeiro.view')
);

const canExportReports = computed(
  () =>
    abilities.value.includes('reports.export') &&
    abilities.value.includes('reports.view.financeiro')
);

type SidebarBalance = { id: number; nome: string; saldo: number };

const sidebarBalances = ref<SidebarBalance[]>([]);
const sidebarBalancesLoading = ref(false);
const sidebarBalancesError = ref<string | null>(null);
const reportLoading = ref(false);
const reportError = ref<string | null>(null);

const formatCurrency = (value: number) =>
  Number(value ?? 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  });

const loadSidebarBalances = async () => {
  if (!canViewBalances.value || sidebarBalancesLoading.value) {
    return;
  }

  sidebarBalancesLoading.value = true;
  sidebarBalancesError.value = null;

  try {
    const { data } = await axios.get('/api/financeiro/account-balances');
    const accounts = Array.isArray(data?.data?.accounts) ? data.data.accounts : [];

    sidebarBalances.value = accounts.map((account: any) => ({
      id: Number(account?.id ?? 0),
      nome: String(account?.nome ?? 'Conta'),
      saldo: Number(account?.saldo_atual ?? 0),
    }));
  } catch (error: any) {
    console.error(error);
    sidebarBalancesError.value =
      error?.response?.data?.message ?? 'Não foi possível carregar os saldos.';
  } finally {
    sidebarBalancesLoading.value = false;
  }
};

const refreshSidebarBalances = () => {
  sidebarBalances.value = [];
  void loadSidebarBalances();
};

const todayIsoDate = () => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const triggerExpenseReportPdf = () => {
  if (!canExportReports.value || reportLoading.value) {
    return;
  }

  reportLoading.value = true;
  reportError.value = null;

  const today = todayIsoDate();

  axios
    .get(route('reports.bank-ledger.export'), {
      params: {
        format: 'pdf',
        type: 'despesa',
        date_from: today,
        date_to: today,
      },
      responseType: 'blob',
    })
    .then((response) => {
      reportError.value = null;

      const contentType = response.headers['content-type'] ?? 'application/pdf';
      const blob = new Blob([response.data], { type: contentType });
      const blobUrl = URL.createObjectURL(blob);

      const link = document.createElement('a');
      link.href = blobUrl;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      setTimeout(() => {
        URL.revokeObjectURL(blobUrl);
      }, 60_000);
    })
    .catch((error: any) => {
      console.error(error);
      const message =
        error?.response?.data?.message ??
        error?.response?.data ??
        'Não foi possível gerar o relatório.';
      reportError.value = typeof message === 'string' ? message : 'Não foi possível gerar o relatório.';
      notificationStore.error('Falha ao gerar relatório de despesas.');
    })
    .finally(() => {
      reportLoading.value = false;
    });
};

watch(
  canViewBalances,
  (value) => {
    if (value && sidebarBalances.value.length === 0) {
      void loadSidebarBalances();
    }
  },
  { immediate: false }
);

const isLinkActive = (href?: string, exact?: boolean) => {
  if (!href) return false;
  return exact ? currentUrl.value === href : currentUrl.value.startsWith(href);
};

const itemClasses = (active: boolean) => [
  'relative flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-left transition',
  active ? 'bg-indigo-500/15 text-white ring-1 ring-inset ring-indigo-500/40' : 'text-slate-300 hover:bg-slate-900/60 hover:text-white',
].join(' ');

const childItemClasses = (active: boolean) => [ active ? 'text-indigo-300' : 'text-slate-400 hover:text-indigo-200' ].join(' ');

const toggleSidebar = () => { isSidebarOpen.value = !isSidebarOpen.value; };

// Persistir preferência de “fixar” o menu lateral
if (typeof window !== 'undefined') {
  try {
    const saved = localStorage.getItem('sidebarPinned');
    pinned.value = saved === 'true';
    if (pinned.value) isCollapsed.value = false;
  } catch {}
}

const togglePin = () => {
  pinned.value = !pinned.value;
  try { localStorage.setItem('sidebarPinned', String(pinned.value)); } catch {}
  isCollapsed.value = !pinned.value ? true : false;
};

watch(currentUrl, () => {
  closeUserMenu();
});

const navigateToAccount = () => {
  closeUserMenu();
  router.visit(route('profile.edit'));
};

const navigateToPassword = () => {
  closeUserMenu();
  router.visit(route('profile.password.edit'));
};

const submitLogout = () => {
  closeUserMenu();
  router.post(route('logout'));
};
</script>

<template>
  <div class="app-compact min-h-screen w-full bg-slate-950 overflow-x-hidden transition-all" :class="isCollapsed ? 'lg:pl-20' : 'lg:pl-80'">
    <transition name="fade">
      <div v-if="userMenuOpen" class="fixed inset-0 z-[9980]" @click="closeUserMenu" />
    </transition>

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
              <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Gestão Imobiliária</p>
            </div>
          </Link>

          <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2 text-sm font-medium text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 lg:hidden" @click="toggleSidebar">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M3 6h18M3 18h18"/></svg>
            Fechar
          </button>
          <button type="button" class="hidden lg:inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2 text-xs font-medium text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20" @click="togglePin" :title="pinned ? 'Soltar menu (recolher ao sair)' : 'Fixar menu (sempre aberto)'"><svg v-if="!pinned" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 00-.586 1.414V17h1.999a2 2 0 001.414-.586L18 9.828m0 0L14.172 6M18 9.828V4"/></svg><svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg><span v-if="!isCollapsed">{{ pinned ? 'Soltar' : 'Fixar' }}</span></button>
        </div>

        <nav class="flex-1 overflow-y-auto">
          <ul class="space-y-1">
            <li v-for="item in navItems" :key="item.key">
              <template v-if="item.children?.length && can(item.ability)">
                <button type="button" :class="itemClasses(false)" @click="toggleExpanded(item.key)">
                  <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" /></svg>
                  <span v-if="!isCollapsed" class="flex-1 font-medium">{{ item.label }}</span>
                  <svg
                    v-if="!isCollapsed"
                    class="h-4 w-4 text-slate-400 transition-transform"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    :class="{ 'rotate-180 text-indigo-300': expanded[item.key] }"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                  </svg>
                </button>
                <transition name="accordion">
                  <ul v-show="expanded[item.key]" class="space-y-1 pl-12">
                    <li v-for="child in item.children" :key="child.key">
                      <Link
                        v-if="child.href && (!child.ability || abilities.includes(child.ability))"
                        :href="child.href"
                        class="group flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition hover:bg-slate-900/70"
                        :class="childItemClasses(isLinkActive(child.href, child.exact))"
                        @click="isSidebarOpen = false"
                      >
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-500 transition group-hover:bg-indigo-300" :class="{ 'bg-indigo-300': isLinkActive(child.href, child.exact) }" />
                        <span>{{ child.label }}</span>
                      </Link>
                    </li>
                  </ul>
                </transition>
              </template>
              <template v-else>
                <Link
                  v-if="item.href && can(item.ability)"
                  :href="item.href"
                  class="group"
                  :class="itemClasses(isLinkActive(item.href, item.exact))"
                  @click="isSidebarOpen = false"
                >
                  <svg class="h-5 w-5 text-slate-400 group-hover:text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" /></svg>
                  <span v-if="!isCollapsed" class="font-medium">{{ item.label }}</span>
                </Link>
              </template>
            </li>
          </ul>

          <div
            v-if="canViewBalances"
            class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-3 text-sm text-slate-200 shadow-inner shadow-black/30"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <span v-if="!isCollapsed" class="font-semibold text-slate-100">Saldos das contas</span>
              </div>
              <button
                v-if="!isCollapsed"
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-800/60 px-2 py-1 text-xs font-medium text-slate-300 transition hover:border-indigo-500 hover:bg-indigo-500/10"
                @click="refreshSidebarBalances"
              >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5 19a9 9 0 0014-7.5M19 5A9 9 0 005 12.5" />
                </svg>
                Atualizar
              </button>
            </div>

            <div
              v-if="sidebarBalancesLoading"
              class="mt-3 flex items-center justify-center gap-2 text-xs text-slate-400"
            >
              <svg class="h-4 w-4 animate-spin text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.364 6.364l-2.121-2.121M8.757 8.757 6.636 6.636m0 10.728L8.757 15.243m8.486-8.486L19.364 6.636" />
              </svg>
              Carregando saldos...
            </div>
            <div
              v-else-if="sidebarBalancesError"
              class="mt-3 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs text-rose-100"
            >
              {{ sidebarBalancesError }}
            </div>
            <template v-else>
              <ul
                v-if="!isCollapsed"
                class="mt-3 max-h-52 space-y-2 overflow-y-auto pr-1 text-sm"
              >
                <li
                  v-for="account in sidebarBalances"
                  :key="account.id"
                  class="flex items-start justify-between gap-3 rounded-lg border border-slate-800/70 bg-slate-900/80 px-3 py-2"
                >
                  <span class="flex-1 truncate text-slate-200">{{ account.nome }}</span>
                  <span
                    class="whitespace-nowrap text-right text-xs font-semibold"
                    :class="account.saldo >= 0 ? 'text-emerald-300' : 'text-rose-300'"
                  >
                    {{ formatCurrency(account.saldo) }}
                  </span>
                </li>
                <li v-if="sidebarBalances.length === 0" class="rounded-lg border border-dashed border-slate-800/70 bg-slate-900/70 px-3 py-4 text-center text-xs text-slate-400">
                  Nenhuma conta financeira cadastrada.
                </li>
              </ul>
              <div v-else class="mt-3 text-center text-[0.65rem] text-slate-400">
                {{ sidebarBalances.length }} contas
              </div>
            </template>
          </div>

          <div
            v-if="canExportReports"
            class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-3 text-sm text-slate-200 shadow-inner shadow-black/30"
          >
            <div class="flex items-center justify-start">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-indigo-500/50 bg-indigo-500/15 px-3 py-1.5 text-xs font-semibold text-indigo-100 transition hover:border-indigo-400 hover:bg-indigo-500/25 disabled:cursor-not-allowed disabled:opacity-60"
                @click="triggerExpenseReportPdf"
                :title="isCollapsed ? 'Gerar PDF de despesas (hoje)' : undefined"
                :disabled="reportLoading"
              >
                <svg v-if="!reportLoading" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7v8.5A2.5 2.5 0 0116.5 18H7l-4 4V5.5A2.5 2.5 0 015.5 3h11A2.5 2.5 0 0119 5.5V7" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v6m-3-3h6" />
                </svg>
                <svg v-else class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5 19a9 9 0 0014-7.5M19 5A9 9 0 005 12.5" />
                </svg>
                <span v-if="!isCollapsed">Despesas (PDF - hoje)</span>
              </button>
            </div>
            <div v-if="reportError && !isCollapsed" class="mt-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs text-rose-100">
              {{ reportError }}
            </div>
          </div>
        </nav>
      </div>
    </aside>

    <div class="flex w-full flex-col bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 ">
      <header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
        <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-10 overflow-visible">
          <div>
            <h1 class="text-xl font-semibold text-white">{{ props.title }}</h1>
            <p class="text-sm text-slate-400">Visão geral do Fortress Gestão Imobiliária</p>
          </div>
          <div class="flex items-center gap-3">
            <slot name="header-actions" />

            <div v-if="user" class="relative">
              <button
                ref="userMenuButton"
                type="button"
                class="group flex items-center gap-2 rounded-xl border border-slate-700/80 bg-slate-900/60 px-2 py-1.5 text-left text-slate-200 shadow-sm transition hover:border-indigo-500/60 hover:bg-indigo-600/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50"
                @click.stop="toggleUserMenu"
                :aria-expanded="userMenuOpen ? 'true' : 'false'"
                aria-haspopup="menu"
              >
                <span class="relative flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-indigo-600/85 text-sm font-semibold text-white shadow-inner shadow-indigo-900/40 ring-2 ring-indigo-500/40 transition group-hover:ring-indigo-400/70">
                  <img
                    v-if="userAvatar"
                    :src="userAvatar"
                    :alt="'Avatar de ' + userDisplayName"
                    class="h-full w-full rounded-full object-cover"
                  />
                  <span v-else>{{ userInitials }}</span>
                </span>
                <div class="hidden min-w-[120px] flex-col sm:flex">
                  <span class="text-sm font-medium leading-tight text-white">{{ userDisplayName }}</span>
                  <span class="text-xs text-slate-400">@{{ userLogin }}</span>
                </div>
                <svg
                  class="hidden h-4 w-4 text-slate-400 transition-transform duration-150 sm:block"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  :class="{ 'rotate-180 text-indigo-300': userMenuOpen }"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                </svg>
              </button>

              <Teleport to="body">
                <transition name="fade">
                  <div v-if="userMenuOpen">
                    <div class="fixed inset-0 z-[9990]" @click="closeUserMenu" />
                    <div
                      ref="userMenuContainer"
                      class="fixed z-[9999] w-64 overflow-hidden rounded-xl border border-slate-800/80 bg-slate-900/95 shadow-xl shadow-black/40 backdrop-blur"
                      role="menu"
                      aria-label="Menu do usuário"
                      :style="menuStyles"
                    >
                      <div class="flex items-center gap-3 px-4 py-3">
                        <span class="relative flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-600/85 text-sm font-semibold text-white shadow-inner shadow-indigo-900/40 ring-2 ring-indigo-500/40">
                          <img
                            v-if="userAvatar"
                            :src="userAvatar"
                            :alt="'Avatar de ' + userDisplayName"
                            class="h-full w-full rounded-full object-cover"
                          />
                          <span v-else>{{ userInitials }}</span>
                        </span>
                        <div class="min-w-0">
                          <p class="truncate text-sm font-semibold text-white">{{ userDisplayName }}</p>
                          <p class="truncate text-xs text-slate-400">@{{ userLogin }}</p>
                        </div>
                      </div>
                      <div class="border-t border-slate-800/80 bg-slate-900/80">
                        <button
                          type="button"
                          class="flex w-full cursor-pointer items-center gap-2 border-b border-slate-800/70 px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-slate-800/70 hover:text-white focus:outline-none focus-visible:bg-slate-800/80"
                          @click.stop.prevent="navigateToAccount"
                        >
                          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 6.75a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12c0-1.785 1.465-3.25 3.25-3.25h4c1.785 0 3.25 1.465 3.25 3.25 0 1.176-.63 2.2-1.567 2.772a8.983 8.983 0 01-2.433 4.132l-.013.012a1.125 1.125 0 01-1.572 0l-.013-.012a8.983 8.983 0 01-2.433-4.132A3.248 3.248 0 016.75 12z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 1115 0" />
                          </svg>
                          Configurações da conta
                        </button>
                        <button
                          type="button"
                          class="flex w-full cursor-pointer items-center gap-2 border-b border-slate-800/70 px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-slate-800/70 hover:text-white focus:outline-none focus-visible:bg-slate-800/80"
                          @click.stop.prevent="navigateToPassword"
                        >
                          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15l3-3-3-3" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.5 5.75A2.75 2.75 0 018.25 3h7.5A2.75 2.75 0 0118.5 5.75v12.5A2.75 2.75 0 0115.75 21h-7.5A2.75 2.75 0 015.5 18.25V5.75z" />
                          </svg>
                          Alterar senha
                        </button>
                        <button
                          type="button"
                          class="flex w-full cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-rose-100 transition hover:bg-rose-600/10 hover:text-rose-200 focus:outline-none focus-visible:bg-rose-600/15 focus-visible:text-rose-100"
                          @click.stop.prevent="submitLogout"
                        >
                          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H9m3 3l-3-3 3-3" />
                          </svg>
                          Desconectar
                        </button>
                      </div>
                    </div>
                  </div>
                </transition>
              </Teleport>
            </div>

            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2 text-sm font-medium text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 lg:hidden"
              @click="toggleSidebar"
            >
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M3 6h18M3 18h18"/></svg>
              Menu
            </button>
          </div>
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

    <transition-group name="toast" tag="div" class="fixed bottom-4 right-4 z-50 flex w-full max-w-xs flex-col gap-3 px-4 sm:px-0">
      <div v-for="notification in notifications" :key="notification.id" :class="[
          'relative rounded-xl border px-4 py-3 text-sm shadow-lg shadow-black/40 backdrop-blur transition',
          notification.type === 'success' ? 'border-emerald-500/40 bg-emerald-500/15 text-emerald-100' : '',
          notification.type === 'error' ? 'border-rose-500/40 bg-rose-500/15 text-rose-100' : '',
          notification.type === 'info' ? 'border-slate-500/40 bg-slate-500/15 text-slate-100' : '',
        ]"
      >
        <button type="button" class="absolute right-2 top-2 text-xs text-slate-400 transition hover:text-white" @click="notificationStore.remove(notification.id)">fechar</button>
        {{ notification.message }}
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active { transition: all 0.2s ease; }
.toast-enter-from,
.toast-leave-to { opacity: 0; transform: translateY(8px); }
.fade-enter-active,
.fade-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; transform: translateY(-6px); }
.accordion-enter-active,
.accordion-leave-active { transition: all 0.18s ease-in-out; }
.accordion-enter-from,
.accordion-leave-to { opacity: 0; transform: translateY(-4px); }
</style>

<style>
/* Densidade compacta global aplicada pelo wrapper .app-compact */
.app-compact table th,
.app-compact table td { padding-top: 0.5rem; padding-bottom: 0.5rem; }
.app-compact .p-6 { padding: 1rem !important; }
.app-compact header.sticky .py-4 { padding-top: .5rem !important; padding-bottom: .5rem !important; }
</style>
