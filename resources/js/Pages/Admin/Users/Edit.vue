<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Save } from 'lucide-vue-next';

const props = defineProps({
    managedUser: Object,
    accounts: Array,
    roles: Array,
    accountRoles: Array,
    permissions: Array,
    memberships: Array,
});

const form = useForm({
    name: props.managedUser.name,
    email: props.managedUser.email,
    global_role: props.managedUser.global_role,
    current_account_id: props.managedUser.current_account_id ?? '',
    memberships: props.memberships.map((membership) => ({
        id: membership.id,
        account_id: membership.account_id,
        account_name: membership.account?.name ?? 'Unknown account',
        role_id: membership.role_id ?? '',
        status: membership.status,
        permissions: membership.permissions ?? [],
    })),
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
                    <h2 class="text-sm font-bold text-zinc-950">Memberships and access</h2>
                </div>
                <div class="panel-body space-y-4">
                    <div v-for="membership in form.memberships" :key="membership.id" class="rounded-md border border-zinc-200 p-4">
                        <div class="mb-4">
                            <div class="font-semibold text-zinc-950">{{ membership.account_name }}</div>
                            <div class="text-xs text-zinc-500">Membership ID {{ membership.id }}</div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label>Account role</label>
                                <select v-model="membership.role_id">
                                    <option value="">No role</option>
                                    <option v-for="role in props.accountRoles" :key="role.id" :value="role.id">{{ role.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label>Status</label>
                                <select v-model="membership.status">
                                    <option value="invited">Invited</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label>Direct permissions</label>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                <label v-for="permission in props.permissions" :key="permission.id" class="flex items-start gap-2 rounded-md border border-zinc-200 p-2 text-sm font-normal text-zinc-700">
                                    <input v-model="membership.permissions" :value="permission.name" type="checkbox" class="mt-0.5 size-4 rounded border-zinc-300 p-0" />
                                    <span>
                                        <span class="block font-medium text-zinc-950">{{ permission.label }}</span>
                                        <span class="text-xs text-zinc-500">{{ permission.name }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <p v-if="!form.memberships.length" class="text-sm text-zinc-500">No account memberships.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
