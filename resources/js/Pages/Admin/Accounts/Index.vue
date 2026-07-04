<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ExternalLink, Store, Users } from 'lucide-vue-next';

const props = defineProps({
    accounts: Object,
    filters: Object,
});

const filters = reactive({ search: props.filters.search ?? '' });
const apply = () => router.get('/admin/accounts', filters, { preserveState: true, preserveScroll: true });

const rows = computed(() => props.accounts.data.map((account) => {
    const primaryStore = account.stores?.[0] ?? null;

    return {
        ...account,
        primaryStore,
        detailHref: `/admin/accounts/${account.id}`,
    };
}));
</script>

<template>
    <Head title="Customers" />
    <AppLayout>
        <template #title>Customers</template>

        <section class="panel mb-6">
            <div class="panel-header flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-zinc-950">Customer directory</h2>
                    <p class="text-sm text-zinc-500">One row per customer workspace, with the connected store and support link in the same view.</p>
                </div>
                <Link href="/admin/accounts/create" class="btn btn-primary">Create Customer</Link>
            </div>
            <div class="panel-body grid gap-3 md:grid-cols-[1fr_160px]">
                <div>
                    <label>Search</label>
                    <input v-model="filters.search" @keydown.enter="apply" placeholder="Customer name, email, slug..." />
                </div>
                <div class="flex items-end">
                    <button class="btn btn-primary w-full" @click="apply">Apply</button>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Store</th>
                            <th>Store URL</th>
                            <th>Plan</th>
                            <th>Owner Email</th>
                            <th>Status</th>
                            <th>Credits</th>
                            <th>Members</th>
                            <th>Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="account in rows" :key="account.id">
                            <td>
                                <div class="font-semibold text-zinc-950">{{ account.name }}</div>
                                <div class="text-xs text-zinc-500">{{ account.slug }}</div>
                            </td>
                            <td>
                                <div v-if="account.primaryStore" class="font-medium text-zinc-900">
                                    {{ account.primaryStore.name }}
                                </div>
                                <div v-else class="text-zinc-500">No store connected</div>
                                <div class="mt-1 text-xs text-zinc-500">
                                    {{ account.stores_count }} store{{ account.stores_count === 1 ? '' : 's' }}
                                </div>
                            </td>
                            <td>
                                <a
                                    v-if="account.primaryStore?.shop_url"
                                    :href="account.primaryStore.shop_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-sm font-medium text-teal-700 hover:text-teal-800"
                                >
                                    <span class="truncate">{{ account.primaryStore.shop_domain }}</span>
                                    <ExternalLink class="size-3.5" />
                                </a>
                                <span v-else class="text-zinc-500">-</span>
                            </td>
                            <td>{{ account.plan_key }}</td>
                            <td>{{ account.owner?.email ?? account.billing_email ?? '-' }}</td>
                            <td>
                                <span class="badge" :class="`badge-${account.primaryStore?.status || account.status || 'active'}`">
                                    {{ account.primaryStore?.status || account.status || 'active' }}
                                </span>
                            </td>
                            <td>{{ Number(account.credit_balance ?? 0).toLocaleString() }}</td>
                            <td>
                                <div class="inline-flex items-center gap-1 text-zinc-600">
                                    <Users class="size-4" />
                                    <span>{{ account.users_count }}</span>
                                </div>
                            </td>
                            <td>
                                <Link :href="account.detailHref" class="btn btn-secondary">
                                    <Store class="size-4" />
                                    Open
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
