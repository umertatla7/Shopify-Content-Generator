<script setup>
import { computed } from 'vue';
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
    LogOut,
    Package,
    Settings,
    ShoppingBag,
    Users,
    Building2,
} from 'lucide-vue-next';

const page = usePage();
const auth = computed(() => page.props.auth ?? {});
const permissions = computed(() => auth.value.permissions ?? {});
const isAdmin = computed(() => Boolean(auth.value.user?.is_platform_admin));
const brandLogo = '/images/growthpilot-ai-logo.png';

const customerItems = computed(() => [
    { href: '/dashboard', label: 'Dashboard', icon: BarChart3, show: true },
    { href: '/stores', label: 'Stores', icon: ShoppingBag, show: permissions.value['stores.manage'] || permissions.value['stores.sync'] },
    { href: '/products', label: 'Products', icon: Package, show: permissions.value['stores.view'] || permissions.value['stores.manage'] || permissions.value['stores.sync'] },
    { href: '/collections', label: 'Collections', icon: Layers, show: permissions.value['stores.view'] || permissions.value['stores.manage'] || permissions.value['stores.sync'] },
    { href: '/rank-tracking', label: 'Rank Tracking', icon: LineChart, show: permissions.value['stores.view'] || permissions.value['blogs.edit'] },
    { href: '/ai-visibility', label: 'AI Visibility', icon: Bot, show: permissions.value['stores.view'] || permissions.value['blogs.edit'] },
    { href: '/topics', label: 'Topics', icon: Lightbulb, show: permissions.value['topics.manage'] },
    { href: '/blogs', label: 'Blogs', icon: FileText, show: permissions.value['blogs.edit'] || permissions.value['blogs.approve'] },
    { href: '/blogs?status=scheduled', label: 'Schedule', icon: CalendarClock, show: permissions.value['blogs.approve'] },
    { href: '/team', label: 'Team', icon: Users, show: permissions.value['team.manage'] },
]);

const adminItems = [
    { href: '/admin/dashboard', label: 'Admin', icon: BarChart3, show: true },
    { href: '/admin/users', label: 'Users', icon: Users, show: true },
    { href: '/admin/accounts', label: 'Accounts', icon: Building2, show: true },
    { href: '/admin/plans', label: 'Plans', icon: Package, show: true },
    { href: '/admin/stores', label: 'Stores', icon: ShoppingBag, show: true },
    { href: '/admin/topics', label: 'Topics', icon: Lightbulb, show: true },
    { href: '/admin/blogs', label: 'Blogs', icon: FileText, show: true },
    { href: '/admin/activity', label: 'Activity', icon: Activity, show: true },
];

const items = computed(() => (isAdmin.value ? adminItems : customerItems.value));

const isActive = (href) => {
    const path = window.location.pathname + window.location.search;
    return href === '/dashboard' ? window.location.pathname === href : path.startsWith(href.split('?')[0]);
};

const logout = () => router.delete('/logout');
</script>

<template>
    <div class="min-h-screen bg-zinc-50">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-zinc-200 bg-white lg:block">
            <div class="flex h-16 items-center gap-3 border-b border-zinc-200 px-4">
                <img :src="brandLogo" alt="SEO & AEO Content Generator" class="h-10 w-10 rounded-md object-cover object-left" />
                <div>
                    <div class="text-sm font-bold leading-tight text-zinc-950">SEO & AEO Content Generator</div>
                    <div class="text-xs text-zinc-500">{{ isAdmin ? 'Platform admin' : auth.account?.name ?? 'No account' }}</div>
                </div>
            </div>

            <nav class="space-y-1 p-3">
                <Link
                    v-for="item in items.filter((item) => item.show)"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                    :class="isActive(item.href) ? 'bg-teal-50 text-teal-800' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950'"
                >
                    <component :is="item.icon" class="size-4" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 backdrop-blur">
                <div class="flex min-h-16 items-center justify-between gap-4 px-4 lg:px-6">
                    <div>
                        <h1 class="text-base font-bold text-zinc-950">
                            <slot name="title">Dashboard</slot>
                        </h1>
                        <p class="text-xs text-zinc-500">{{ auth.user?.name }} · {{ isAdmin ? 'super admin' : auth.account?.plan_key ?? 'starter' }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link :href="isAdmin ? '/admin/accounts' : '/stores'" class="btn btn-secondary">
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
                    <Link
                        v-for="item in items.filter((item) => item.show)"
                        :key="item.href"
                        :href="item.href"
                        class="flex shrink-0 items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
                        :class="isActive(item.href) ? 'bg-teal-50 text-teal-800' : 'text-zinc-600'"
                    >
                        <component :is="item.icon" class="size-4" />
                        {{ item.label }}
                    </Link>
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
