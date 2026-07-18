<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Bot,
    BarChart3,
    CalendarClock,
    FileText,
    Layers,
    LineChart,
    Lightbulb,
    LifeBuoy,
    LogOut,
    Package,
    Settings,
    ShoppingBag,
    Users,
    Building2,
    CreditCard,
    DollarSign,
    LockKeyhole,
    PanelLeftClose,
    PanelLeftOpen,
} from 'lucide-vue-next';

const page = usePage();
const auth = computed(() => page.props.auth ?? {});
const permissions = computed(() => auth.value.permissions ?? {});
const planAccess = computed(() => auth.value.plan_access ?? auth.value.account?.plan_access ?? {});
const setup = computed(() => auth.value.setup ?? {});
const isAdmin = computed(() => Boolean(auth.value.user?.is_platform_admin));
const shopify = computed(() => page.props.shopify ?? {});
const sidebarCollapsed = ref(false);
const showAuthActions = computed(() => isAdmin.value || !shopify.value.embedded);
const primaryStoreName = computed(() => auth.value.account?.stores?.[0]?.name ?? auth.value.account?.name ?? 'Shopify workspace');
const planLabel = computed(() => {
    const key = auth.value.account?.plan_key;

    if (!key) {
        return 'trial setup';
    }

    return String(key)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
});
const brandLogo = '/images/growthpilot-ai-logo.png';
const requiresCatalogSync = computed(() => Boolean(setup.value.requires_catalog_sync));
const contextQuery = computed(() => {
    const query = new URLSearchParams();

    if (shopify.value.shop) query.set('shop', shopify.value.shop);
    if (shopify.value.host) query.set('host', shopify.value.host);
    if (shopify.value.embedded) query.set('embedded', '1');

    return query.toString();
});

const withShopifyContext = (href) => {
    if (!contextQuery.value) return href;

    return href.includes('?') ? `${href}&${contextQuery.value}` : `${href}?${contextQuery.value}`;
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    sidebarCollapsed.value = window.localStorage.getItem('gsh-sidebar-collapsed') === '1';
});

watch(sidebarCollapsed, (value) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem('gsh-sidebar-collapsed', value ? '1' : '0');
});

const customerItems = computed(() => [
    { href: '/dashboard', label: 'Dashboard', section: 'Overview', icon: BarChart3, show: true, locked: false },
    { href: '/stores', label: 'Store', section: 'Overview', icon: ShoppingBag, show: permissions.value['stores.view'] || permissions.value['stores.manage'] || permissions.value['stores.sync'], locked: false },
    { href: '/store-audit', label: 'Store Audit', section: 'Overview', icon: ShoppingBag, show: true, locked: !planAccess.value.store_audit },
    { href: '/billing', label: 'Billing', section: 'Overview', icon: CreditCard, show: permissions.value['billing.manage'] || permissions.value['stores.manage'], locked: false },
    { href: '/products', label: 'Products', section: 'Content', icon: Package, show: permissions.value['stores.view'] || permissions.value['stores.manage'] || permissions.value['stores.sync'], locked: !planAccess.value.products },
    { href: '/collections', label: 'Collections', section: 'Content', icon: Layers, show: permissions.value['stores.view'] || permissions.value['stores.manage'] || permissions.value['stores.sync'], locked: !planAccess.value.collections },
    { href: '/topics', label: 'Topics', section: 'Content', icon: Lightbulb, show: permissions.value['topics.manage'], locked: !planAccess.value.topics },
    { href: '/blogs', label: 'Blogs', section: 'Content', icon: FileText, show: permissions.value['blogs.edit'] || permissions.value['blogs.approve'], locked: !planAccess.value.blogs },
    { href: '/blogs?status=scheduled', label: 'Schedule', section: 'Content', icon: CalendarClock, show: permissions.value['blogs.approve'], locked: !planAccess.value.schedule },
    { href: '/rank-tracking', label: 'Keyword Tracking', section: 'Growth', icon: LineChart, show: true, locked: !planAccess.value.rank_tracking },
    { href: '/ai-visibility', label: 'AI Visibility', section: 'Growth', icon: Bot, show: true, locked: !planAccess.value.ai_visibility },
    { href: '/help', label: 'Support', section: 'Help', icon: LifeBuoy, show: true, locked: false },
]);

const preSyncAllowedHrefs = new Set(['/stores', '/billing', '/help']);

const itemIsDisabled = (item) => {
    if (isAdmin.value) {
        return false;
    }

    if (item.locked) {
        return true;
    }

    if (!requiresCatalogSync.value) {
        return false;
    }

    return !preSyncAllowedHrefs.has(item.href);
};

const itemHint = (item) => {
    if (item.locked) {
        return 'Upgrade your plan to unlock this area.';
    }

    if (!isAdmin.value && requiresCatalogSync.value && !preSyncAllowedHrefs.has(item.href)) {
        return 'Sync your Shopify store first to unlock this area.';
    }

    return undefined;
};

const adminItems = [
    { href: '/admin/dashboard', label: 'Overview', icon: BarChart3, show: true },
    { href: '/admin/accounts', label: 'Customers', icon: Building2, show: true },
    { href: '/admin/support', label: 'Support Inbox', icon: LifeBuoy, show: true },
    { href: '/admin/users', label: 'Team', icon: Users, show: true },
    { href: '/admin/activity', label: 'Activity Logs', icon: Activity, show: true },
    { href: '/admin/dashboard?focus=costs', label: 'AI Cost', icon: DollarSign, show: true },
    { href: '/admin/plans', label: 'Plans', icon: Package, show: true },
    { href: '/admin/settings', label: 'Settings', icon: Settings, show: true },
    { href: '/admin/topics', label: 'Topics', icon: Lightbulb, show: true },
    { href: '/admin/blogs', label: 'Blogs', icon: FileText, show: true },
];

const items = computed(() => (isAdmin.value ? adminItems : customerItems.value));
const navGroups = computed(() => {
    const baseItems = items.value.filter((item) => item.show);

    if (isAdmin.value) {
        return [{ label: 'Platform', items: baseItems }];
    }

    const order = ['Overview', 'Content', 'Growth', 'Help'];

    return order
        .map((label) => ({
            label,
            items: baseItems.filter((item) => item.section === label),
        }))
        .filter((group) => group.items.length);
});

const isActive = (href) => {
    const path = window.location.pathname + window.location.search;

    if (href.includes('?')) {
        return path === href;
    }

    return href === '/dashboard' ? window.location.pathname === href : path.startsWith(href);
};

const logout = () => router.delete('/logout');
</script>

<template>
    <div class="min-h-screen bg-zinc-50">
        <aside class="fixed inset-y-0 left-0 hidden border-r border-zinc-200 bg-white lg:flex lg:flex-col" :class="sidebarCollapsed ? 'w-20' : 'w-64'">
            <div class="flex h-16 items-center gap-3 border-b border-zinc-200 px-4" :class="sidebarCollapsed ? 'justify-center' : ''">
                <div class="grid size-11 place-items-center rounded-xl border border-zinc-200 bg-zinc-50">
                    <img :src="brandLogo" alt="SEO & AEO Content Generator" class="h-9 w-9 rounded-lg object-cover object-left" />
                </div>
                <div v-if="!sidebarCollapsed">
                    <div class="text-sm font-bold leading-tight text-zinc-950">SEO & AEO Content Generator</div>
                    <div class="text-xs text-zinc-500">{{ isAdmin ? 'Platform admin' : primaryStoreName }}</div>
                </div>
            </div>

            <div class="border-b border-zinc-100 px-4 py-4" :class="sidebarCollapsed ? 'px-2' : ''">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                    <div class="flex items-center justify-between gap-2" :class="sidebarCollapsed ? 'justify-center' : ''">
                        <span v-if="!sidebarCollapsed" class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Workspace</span>
                        <span class="badge bg-white text-zinc-700">{{ isAdmin ? 'Admin' : planLabel }}</span>
                    </div>
                    <p v-if="!sidebarCollapsed" class="mt-2 text-sm font-semibold text-zinc-950">{{ isAdmin ? 'Internal operations' : primaryStoreName }}</p>
                    <p v-if="!sidebarCollapsed" class="mt-1 text-xs leading-5 text-zinc-500">
                        {{ isAdmin ? 'Customer health, pricing, support, and activity controls.' : 'Store content, AI visibility, and publishing in one place.' }}
                    </p>
                </div>
            </div>

            <div class="flex flex-1 flex-col overflow-hidden">
                <div class="px-3 pb-2 pt-3">
                    <button class="editor-tool w-full" type="button" :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'" @click="sidebarCollapsed = !sidebarCollapsed">
                        <PanelLeftOpen v-if="sidebarCollapsed" class="size-4" />
                        <PanelLeftClose v-else class="size-4" />
                    </button>
                </div>

                <nav class="flex-1 space-y-4 overflow-y-auto px-3 pb-4">
                <section v-for="group in navGroups" :key="group.label" class="space-y-1">
                    <div v-if="!sidebarCollapsed" class="px-3 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">
                        {{ group.label }}
                    </div>
                    <component
                        :is="itemIsDisabled(item) ? 'div' : Link"
                        v-for="item in group.items"
                        :key="item.href"
                        v-bind="itemIsDisabled(item) ? {} : { href: withShopifyContext(item.href) }"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                        :title="sidebarCollapsed ? item.label : itemHint(item)"
                        :class="itemIsDisabled(item)
                            ? 'cursor-not-allowed bg-zinc-50 text-zinc-400'
                            : (isActive(item.href)
                                ? 'bg-teal-50 text-teal-800 shadow-sm ring-1 ring-teal-100'
                                : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950')"
                    >
                        <component :is="item.icon" class="size-4" />
                        <span v-if="!sidebarCollapsed">{{ item.label }}</span>
                        <LockKeyhole v-if="itemIsDisabled(item)" class="ml-auto size-3.5 text-zinc-400" />
                    </component>
                </section>
                </nav>
            </div>
        </aside>

        <div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'">
            <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 backdrop-blur">
                <div class="flex min-h-16 items-center justify-between gap-4 px-4 lg:px-6">
                    <div>
                        <h1 class="text-base font-bold text-zinc-950">
                            <slot name="title">Dashboard</slot>
                        </h1>
                        <p class="text-xs text-zinc-500">
                            {{ auth.user?.name }} · {{ isAdmin ? 'super admin' : planLabel }}
                            <span v-if="shopify.embedded" class="ml-2 rounded-full bg-teal-50 px-2 py-0.5 text-[11px] font-semibold text-teal-800">Shopify App Home</span>
                        </p>
                    </div>

                    <div v-if="showAuthActions" class="flex items-center gap-2">
                        <Link :href="withShopifyContext(isAdmin ? '/admin/settings' : '/billing')" class="btn btn-secondary">
                            <Settings class="size-4" />
                            <span class="hidden sm:inline">Settings</span>
                        </Link>
                        <button type="button" class="btn btn-secondary" @click="logout">
                            <LogOut class="size-4" />
                            <span class="hidden sm:inline">Logout</span>
                        </button>
                    </div>
                </div>

                <nav class="flex gap-1 overflow-x-auto border-t border-zinc-100 px-2 py-2 lg:hidden">
                    <component
                        v-for="item in items.filter((item) => item.show)"
                        :key="item.href"
                        :is="itemIsDisabled(item) ? 'div' : Link"
                        v-bind="itemIsDisabled(item) ? {} : { href: withShopifyContext(item.href) }"
                        class="flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium"
                        :title="itemHint(item)"
                        :class="itemIsDisabled(item) ? 'bg-zinc-50 text-zinc-400' : (isActive(item.href) ? 'bg-teal-50 text-teal-800' : 'text-zinc-600')"
                    >
                        <component :is="item.icon" class="size-4" />
                        {{ item.label }}
                        <LockKeyhole v-if="itemIsDisabled(item)" class="size-3.5 text-zinc-400" />
                    </component>
                </nav>
            </header>

            <main class="p-4 lg:p-6">
                <div v-if="page.props.flash?.status" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ page.props.flash.status }}
                </div>
                <div v-if="Object.keys(page.props.errors ?? {}).length" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                    <div v-for="(error, key) in page.props.errors" :key="key">{{ error }}</div>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
