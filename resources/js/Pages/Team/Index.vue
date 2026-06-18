<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MailPlus } from 'lucide-vue-next';

defineProps({
    account: Object,
    members: Array,
    invitationPlaceholder: Boolean,
});
</script>

<template>
    <Head title="Team" />
    <AppLayout>
        <template #title>Team</template>

        <div class="grid gap-6 xl:grid-cols-[360px_1fr]">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Invite member</h2>
                    <MailPlus class="size-4 text-teal-700" />
                </div>
                <div class="panel-body space-y-4">
                    <div>
                        <label>Email</label>
                        <input disabled placeholder="member@example.com" />
                    </div>
                    <div>
                        <label>Role</label>
                        <select disabled>
                            <option>Team Member</option>
                        </select>
                    </div>
                    <button class="btn btn-secondary w-full" disabled>Invitation flow pending</button>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">{{ account.name }}</h2>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in members" :key="member.id">
                                <td>{{ member.name }}</td>
                                <td>{{ member.email }}</td>
                                <td><span class="badge badge-connected">{{ member.pivot?.status ?? 'active' }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
