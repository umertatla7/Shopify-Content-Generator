<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stores: Object,
    filters: Object,
});

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const apply = () => router.get('/admin/stores', filters, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Admin Stores" />
    <AppLayout>
        <template #title>All Stores</template>

        <section class="panel mb-6">
            <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">Store Directory</h2></div>
            <div class="panel-body grid gap-3 md:grid-cols-3">
                <div><label>Search</label><input v-model="filters.search" @keydown.enter="apply" /></div>
                <div>
                    <label>Status</label>
                    <select v-model="filters.status" @change="apply">
                        <option value="">All</option>
                        <option value="connected">Connected</option>
                        <option value="pending">Pending</option>
                        <option value="disconnected">Disconnected</option>
                    </select>
                </div>
                <div class="flex items-end"><button class="btn btn-primary w-full" @click="apply">Apply</button></div>
            </div>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Store</th><th>Account</th><th>Status</th><th>Products</th><th>Collections</th><th>Blogs</th><th>Last Sync</th></tr></thead>
                    <tbody>
                        <tr v-for="store in props.stores.data" :key="store.id">
                            <td>{{ store.name }}<div class="text-xs text-zinc-500">{{ store.shop_domain }}</div></td>
                            <td>{{ store.account?.name }}</td>
                            <td><span class="badge" :class="`badge-${store.status}`">{{ store.status }}</span></td>
                            <td>{{ store.products_count }}</td>
                            <td>{{ store.collections_count }}</td>
                            <td>{{ store.blogs_count }}</td>
                            <td>{{ store.last_synced_at ? new Date(store.last_synced_at).toLocaleString() : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
