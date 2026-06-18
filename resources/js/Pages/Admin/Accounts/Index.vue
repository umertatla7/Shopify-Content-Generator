<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    accounts: Object,
    filters: Object,
});

const filters = reactive({ search: props.filters.search ?? '' });
const apply = () => router.get('/admin/accounts', filters, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Admin Accounts" />
    <AppLayout>
        <template #title>Accounts</template>

        <section class="panel mb-6">
            <div class="panel-header flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-zinc-950">Customer Accounts</h2>
                <Link href="/admin/accounts/create" class="btn btn-primary">Create Customer</Link>
            </div>
            <div class="panel-body grid gap-3 md:grid-cols-[1fr_160px]">
                <div>
                    <label>Search</label>
                    <input v-model="filters.search" @keydown.enter="apply" />
                </div>
                <div class="flex items-end">
                    <button class="btn btn-primary w-full" @click="apply">Apply</button>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Account</th><th>Owner</th><th>Plan</th><th>Status</th><th>Credits</th><th>Users</th><th>Stores</th><th>Blogs</th><th>AI</th></tr></thead>
                    <tbody>
                        <tr v-for="account in props.accounts.data" :key="account.id">
                            <td><Link :href="`/admin/accounts/${account.id}`" class="font-semibold text-zinc-950 hover:text-teal-700">{{ account.name }}</Link><div class="text-xs text-zinc-500">{{ account.slug }}</div></td>
                            <td>{{ account.owner?.email ?? '-' }}</td>
                            <td>{{ account.plan_key }}</td>
                            <td><span class="badge" :class="`badge-${account.status || 'active'}`">{{ account.status || 'active' }}</span></td>
                            <td>{{ Number(account.credit_balance ?? 0).toLocaleString() }}</td>
                            <td>{{ account.users_count }}</td>
                            <td>{{ account.stores_count }}</td>
                            <td>{{ account.blogs_count }}</td>
                            <td>{{ account.ai_generations_count }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
