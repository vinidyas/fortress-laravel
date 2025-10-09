<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useNotificationStore } from "@/Stores/notifications";

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
const csrfToken = computed(() => page.props.csrf_token ?? "");
const currentUrl = computed(() => page.url ?? "");
const isSidebarOpen = ref(false);
const notificationStore = useNotificationStore();
const notifications = computed(() => notificationStore.items);

const can = (permission?: string) => !permission || abilities.value.includes(permission);

const navItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            key: "dashboard",
            label: "Dashboard",
            href: "/",
            icon: "M3 12h18M3 6h18M3 18h18",
            exact: true,
        },
        {
            key: "imoveis",
            label: "Imoveis",
            href: "/imoveis",
            icon: "M4 12l8-6 8 6v8a2 2 0 01-2 2H6a2 2 0 01-2-2z",
            ability: "imoveis.view",
        },
        {
            key: "pessoas",
            label: "Pessoas",
            href: "/pessoas",
            icon: "M5.5 17a6.5 6.5 0 0113 0M12 9a4 4 0 110-8 4 4 0 010 8z",
            ability: "pessoas.view",
        },
        {
            key: "contratos",
            label: "Contratos",
            href: "/contratos",
            icon: "M7 7h10M7 12h10M7 17h6",
            ability: "contratos.view",
        },
        {
            key: "faturas",
            label: "Faturas",
            href: "/faturas",
            icon: "M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01",
            ability: "faturas.view",
        },
        {
            key: "financeiro",
            label: "Financeiro",
            href: "/financeiro",
            icon: "M3 3h18M5 7h14v12H5z",
            ability: "financeiro.view",
            children: [
                { key: "financeiro-accounts", label: "Contas", href: "/financeiro/contas", ability: "financeiro.view" },
                { key: "financeiro-centers", label: "Centros de Custo", href: "/financeiro/centros", ability: "financeiro.view" },
                { key: "financeiro-lancamentos", label: "Lancamentos", href: "/financeiro", ability: "financeiro.view" },
                { key: "financeiro-schedules", label: "Agendamentos", href: "/financeiro/agendamentos", ability: "financeiro.view" },
            ],
        },
        {
            key: "auditoria",
            label: "Auditoria",
            href: "/auditoria",
            icon: "M4 5h16M4 12h16M4 19h16",
            ability: "auditoria.view",
            children: [
                { key: "auditoria-logs", label: "Logs de Auditoria", href: "/auditoria", ability: "auditoria.view" },
            ],
        },
        {
            key: "relatorios",
            label: "Relatorios",
            icon: "M3 4h18l-2 14H5zM9 2h6v4H9z",
            children: [
                { key: "relatorios-financeiro", label: "Relatorio Financeiro", href: "/relatorios/financeiro", ability: "reports.view.financeiro" },
                { key: "relatorios-operacional", label: "Relatorio Operacional", href: "/relatorios/operacional", ability: "reports.view.operacional" },
                { key: "relatorios-pessoas", label: "Relatorio de Pessoas", href: "/relatorios/pessoas", ability: "reports.view.pessoas" },
            ],
        },
    ];

    return items.reduce<NavItem[]>((accumulator, item) => {
        if (item.children && item.children.length > 0) {
            const allowedChildren = item.children.filter((child) => can(child.ability));
            const parentAllowed = can(item.ability) || allowedChildren.length > 0;

            if (!parentAllowed) {
                return accumulator;
            }

            if (allowedChildren.length === 0) {
                return accumulator;
            }

            accumulator.push({ ...item, children: allowedChildren });
            return accumulator;
        }

        if (!can(item.ability)) {
            return accumulator;
        }

        accumulator.push(item);
        return accumulator;
    }, []);
});

const isLinkActive = (href?: string, exact?: boolean) => {
    if (!href) {
        return false;
    }

    if (exact) {
        return currentUrl.value === href;
    }

    return currentUrl.value.startsWith(href);
};

const isItemActive = (item: NavItem) => {
    if (isLinkActive(item.href, item.exact)) {
        return true;
    }

    return (item.children ?? []).some((child) => isLinkActive(child.href, child.exact));
};

const navItemClasses = (active: boolean) =>
    active
        ? "border-indigo-400/40 bg-indigo-500/20 text-indigo-200 shadow-lg shadow-indigo-500/10"
        : "text-slate-300 hover:border-slate-700 hover:bg-slate-800/60 hover:text-white";

const childItemClasses = (active: boolean) =>
    active
        ? "text-indigo-200 bg-indigo-500/10"
        : "text-slate-300 hover:bg-slate-800/60 hover:text-white";

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};
</script>

<template>
    <div class="flex min-h-screen bg-slate-950 text-slate-100 antialiased">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-72 transform border-r border-slate-800 bg-slate-900/95 backdrop-blur transition-transform duration-200 ease-in-out lg:relative lg:flex lg:w-72 lg:translate-x-0"
            :class="{ '-translate-x-full lg:translate-x-0': !isSidebarOpen, 'translate-x-0': isSidebarOpen }"
        >
            <div class="flex h-full flex-col">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-6">
                    <div>
                        <p class="text-lg font-semibold text-white">Fortress</p>
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Gestão Imobiliaria</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-2 text-slate-400 transition hover:text-white lg:hidden"
                        @click="toggleSidebar"
                    >
                        <span class="sr-only">Fechar menu</span>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-6">
                    <ul class="space-y-1">
                        <li v-for="item in navItems" :key="item.key" class="space-y-1">
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                class="group flex items-center gap-3 rounded-xl border border-transparent px-3 py-2 text-sm font-medium transition"
                                :class="navItemClasses(isItemActive(item))"
                                @click="isSidebarOpen = false"
                            >
                                <span
                                    v-if="item.icon"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700/60 bg-slate-800/60 text-slate-300 group-hover:border-transparent group-hover:bg-indigo-500/30 group-hover:text-indigo-100"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                    </svg>
                                </span>
                                <span>{{ item.label }}</span>
                                <svg
                                    v-if="item.children?.length"
                                    class="ml-auto h-3 w-3 text-slate-500 transition group-hover:text-indigo-200"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 011.08 1.04l-4.25 4.25a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </Link>

                            <div
                                v-else
                                class="group flex items-center gap-3 rounded-xl border border-transparent px-3 py-2 text-sm font-medium"
                                :class="navItemClasses(isItemActive(item))"
                            >
                                <span
                                    v-if="item.icon"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700/60 bg-slate-800/60 text-slate-300"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                    </svg>
                                </span>
                                <span>{{ item.label }}</span>
                            </div>

                            <ul v-if="item.children?.length" class="mt-1 space-y-1 pl-11">
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

                <div class="border-t border-slate-800 px-4 py-5 text-sm">
                    <div v-if="user" class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4 shadow-inner shadow-black/30">
                        <p class="font-semibold text-white">{{ user.nome }}</p>
                        <p class="text-xs text-slate-400">{{ user.username }}</p>
                        <form class="mt-4" method="post" action="/logout">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <button
                                type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-500/40 bg-rose-500/15 px-3 py-2 text-sm font-medium text-rose-200 transition hover:border-rose-400 hover:bg-rose-500/25"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-3 3m3-3l-3-3m3 3h-8a4 4 0 01-4-4V5a2 2 0 012-2h3" />
                                </svg>
                                Sair
                            </button>
                        </form>
                    </div>
                    <div v-else class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-300">
                        <p>Acesso restrito. Faça login para continuar.</p>
                        <Link class="mt-2 inline-flex items-center font-medium text-indigo-300 hover:text-indigo-200" href="/login">
                            Entrar
                        </Link>
                    </div>
                </div>
            </div>
        </aside>

        <div class="flex w-full flex-col bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950">
            <header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-10">
                    <div>
                        <h1 class="text-xl font-semibold text-white">{{ props.title }}</h1>
                        <p class="text-sm text-slate-400">Visão geral do Fortress Gestão Imobiliaria</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2 text-sm font-medium text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 lg:hidden"
                        @click="toggleSidebar"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
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
                    <template v-if="user">
                        Logado como {{ user.nome }}
                    </template>
                    <template v-else>
                        Não autenticado
                    </template>
                </div>
            </footer>
        </div>

        <transition-group name="toast" tag="div" class="fixed bottom-4 right-4 z-50 flex w-full max-w-xs flex-col gap-3 px-4 sm:px-0">
            <div
                v-for="notification in notifications"
                :key="notification.id"
                :class="[
                    'relative rounded-xl border px-4 py-3 text-sm shadow-lg shadow-black/40 backdrop-blur transition',
                    notification.type === 'success' ? 'border-emerald-500/40 bg-emerald-500/15 text-emerald-100' : '',
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
.toast-enter-active, .toast-leave-active {
    transition: all 0.2s ease;
}

.toast-enter-from, .toast-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
