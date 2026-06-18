<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Save } from 'lucide-vue-next';

const props = defineProps({
    managedUser: Object,
    accounts: Array,
    roles: Array,
});

const form = useForm({
    name: props.managedUser.name,
    email: props.managedUser.email,
    global_role: props.managedUser.global_role,
    current_account_id: props.managedUser.current_account_id ?? '',
});

const save = () => form.patch(`/admin/users/${props.managedUser.id}`);
</script>

<template>
    <Head :title="`Edit ${props.managedUser.name}`" />
    <AppLayout>
        <template #title>Edit User</template>

        <div class="mb-4">
            <Link href="/admin/users" class="text-sm font-semibold text-teal-700">Back to users</Link>
        </div>

        <div class="grid gap-6 xl:grid-cols-[440px_1fr]">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Profile</h2>
                    <button class="btn btn-primary" :disabled="form.processing" @click="save"><Save class="size-4" />Save</button>
                </div>
                <div class="panel-body space-y-4">
                    <div>
                        <label>Name</label>
                        <input v-model="form.name" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-700">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label>Email</label>
                        <input v-model="form.email" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-700">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label>Global role</label>
                        <select v-model="form.global_role">
                            <option v-for="role in props.roles" :key="role" :value="role">{{ role }}</option>
                        </select>
                    </div>
                    <div>
                        <label>Current account</label>
                        <select v-model="form.current_account_id">
                            <option value="">None</option>
                            <option v-for="account in props.accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Memberships</h2>
                </div>
                <div class="panel-body space-y-3">
                    <div v-for="account in props.managedUser.accounts" :key="account.id" class="rounded-md border border-zinc-200 p-3">
                        <div class="font-semibold text-zinc-950">{{ account.name }}</div>
                        <div class="text-xs text-zinc-500">Account ID {{ account.id }}</div>
                    </div>
                    <p v-if="!props.managedUser.accounts.length" class="text-sm text-zinc-500">No account memberships.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
