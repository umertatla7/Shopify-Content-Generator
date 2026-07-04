<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    activity: Object,
    filters: Object,
    actions: Array,
    modules: Array,
    statuses: Array,
    accounts: Array,
    stores: Array,
});

const filters = reactive({
    search: props.filters.search ?? '',
    action: props.filters.action ?? '',
    module: props.filters.module ?? '',
    status: props.filters.status ?? '',
    account_id: props.filters.account_id ?? '',
    shopify_store_id: props.filters.shopify_store_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const apply = () => router.get('/admin/activity', filters, { preserveState: true, preserveScroll: true });
const formatValues = (value) => {
    if (!value || !Object.keys(value).length) return '-';

    return Object.entries(value)
        .map(([key, item]) => `${key}: ${item ?? '-'}`)
        .join('\n');
};
</script>

<template>
    <Head title="Admin Activity" />
    <AppLayout>
        <template #title>Activity</template>

        <section class="panel mb-6">
            <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">Audit Trail</h2></div>
            <div class="panel-body grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                <div><label>Search</label><input v-model="filters.search" @keydown.enter="apply" /></div>
                <div>
                    <label>Module</label>
                    <select v-model="filters.module" @change="apply">
                        <option value="">All</option>
                        <option v-for="module in props.modules" :key="module" :value="module">{{ module }}</option>
                    </select>
                </div>
                <div>
                    <label>Action</label>
                    <select v-model="filters.action" @change="apply">
                        <option value="">All</option>
                        <option v-for="action in props.actions" :key="action" :value="action">{{ action }}</option>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select v-model="filters.status" @change="apply">
                        <option value="">All</option>
                        <option v-for="status in props.statuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                </div>
                <div>
                    <label>Account</label>
                    <select v-model="filters.account_id" @change="apply">
                        <option value="">All</option>
                        <option v-for="account in props.accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                </div>
                <div>
                    <label>Store</label>
                    <select v-model="filters.shopify_store_id" @change="apply">
                        <option value="">All</option>
                        <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                    </select>
                </div>
                <div>
                    <label>Date from</label>
                    <input v-model="filters.date_from" type="date" @change="apply" />
                </div>
                <div>
                    <label>Date to</label>
                    <input v-model="filters.date_to" type="date" @change="apply" />
                </div>
                <div class="flex items-end"><button class="btn btn-primary w-full" @click="apply">Apply</button></div>
            </div>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>When</th><th>Action</th><th>Status</th><th>User</th><th>Account</th><th>Store</th><th>Entity</th><th>Description</th><th>Previous</th><th>New</th><th>IP</th></tr></thead>
                    <tbody>
                        <tr v-for="item in props.activity.data" :key="item.id">
                            <td>{{ new Date(item.created_at).toLocaleString() }}</td>
                            <td class="font-semibold text-zinc-950">{{ item.action }}</td>
                            <td><span class="badge" :class="`badge-${item.status || 'success'}`">{{ item.status || 'success' }}</span></td>
                            <td>{{ item.user?.email ?? 'System' }}</td>
                            <td>{{ item.account?.name ?? 'Platform' }}</td>
                            <td>{{ item.store?.name ?? '-' }}</td>
                            <td>{{ item.entity_type ?? item.subject_type ?? '-' }}</td>
                            <td class="whitespace-normal">{{ item.description ?? '-' }}</td>
                            <td><pre class="max-w-64 whitespace-pre-wrap rounded-md bg-zinc-50 p-2 text-xs text-zinc-600">{{ formatValues(item.previous_values) }}</pre></td>
                            <td><pre class="max-w-64 whitespace-pre-wrap rounded-md bg-zinc-50 p-2 text-xs text-zinc-600">{{ formatValues(item.new_values) }}</pre></td>
                            <td>{{ item.ip_address ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
