<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Edit3 } from 'lucide-vue-next';

const props = defineProps({
    users: Object,
    filters: Object,
});

const filters = reactive({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
});

const apply = () => router.get('/admin/users', filters, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Admin Users" />
    <AppLayout>
        <template #title>Users</template>

        <section class="panel mb-6">
            <div class="panel-header">
                <h2 class="text-sm font-bold text-zinc-950">User Directory</h2>
            </div>
            <div class="panel-body grid gap-3 md:grid-cols-3">
                <div>
                    <label>Search</label>
                    <input v-model="filters.search" @keydown.enter="apply" />
                </div>
                <div>
                    <label>Global role</label>
                    <select v-model="filters.role" @change="apply">
                        <option value="">All</option>
                        <option value="user">User</option>
                        <option value="manager">Manager</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button class="btn btn-primary w-full" @click="apply">Apply</button>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Current Account</th><th>Accounts</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="user in props.users.data" :key="user.id">
                            <td class="font-semibold text-zinc-950">{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>{{ user.current_account?.name ?? '-' }}</td>
                            <td>{{ user.accounts_count }}</td>
                            <td>{{ user.global_role }}</td>
                            <td>{{ new Date(user.created_at).toLocaleDateString() }}</td>
                            <td><Link class="btn btn-secondary" :href="`/admin/users/${user.id}/edit`"><Edit3 class="size-4" />Edit</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
