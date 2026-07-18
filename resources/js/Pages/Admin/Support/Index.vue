<script setup>
import { reactive, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Building2, MessageCircle, Send, Store, TicketCheck } from 'lucide-vue-next';

const props = defineProps({
    tickets: Object,
    selectedTicket: Object,
    filters: Object,
    summary: Object,
    accounts: Array,
    stores: Array,
    modules: Array,
    statuses: Array,
});

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    module: props.filters.module ?? '',
    account_id: props.filters.account_id ?? '',
    shopify_store_id: props.filters.shopify_store_id ?? '',
});

const replyForm = useForm({
    body: '',
    status: 'waiting_customer',
});

const updateForm = useForm({
    status: props.selectedTicket?.status ?? 'open',
    priority: props.selectedTicket?.priority ?? 'normal',
    module: props.selectedTicket?.module ?? 'general',
});

const selected = computed(() => props.selectedTicket);
const statusLabel = (value) => String(value || 'open').replaceAll('_', ' ');
const formatDate = (value) => value ? new Date(value).toLocaleString() : '-';
const senderLabel = (message) => message.sender_type === 'admin' ? (message.user?.name ?? 'Admin') : (message.user?.name ?? 'Customer');
const apply = () => router.get('/admin/support', filters, { preserveState: true, preserveScroll: true });

watch(selected, (ticket) => {
    updateForm.status = ticket?.status ?? 'open';
    updateForm.priority = ticket?.priority ?? 'normal';
    updateForm.module = ticket?.module ?? 'general';
});

const sendReply = () => {
    if (!selected.value) return;

    replyForm.post(`/admin/support/${selected.value.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => replyForm.reset('body'),
    });
};

const updateTicket = () => {
    if (!selected.value) return;

    updateForm.patch(`/admin/support/${selected.value.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Admin Support" />
    <AppLayout>
        <template #title>Support Inbox</template>

        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-zinc-950">Support inbox</h2>
                <p class="mt-1 text-sm text-zinc-500">View merchant requests with account, store, plan and conversation context.</p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-md border border-zinc-200 bg-white px-4 py-3 text-center">
                    <p class="text-xl font-bold text-zinc-950">{{ props.summary.waiting_admin }}</p>
                    <p class="text-xs text-zinc-500">Need reply</p>
                </div>
                <div class="rounded-md border border-zinc-200 bg-white px-4 py-3 text-center">
                    <p class="text-xl font-bold text-zinc-950">{{ props.summary.open }}</p>
                    <p class="text-xs text-zinc-500">Open</p>
                </div>
                <div class="rounded-md border border-zinc-200 bg-white px-4 py-3 text-center">
                    <p class="text-xl font-bold text-zinc-950">{{ props.summary.closed }}</p>
                    <p class="text-xs text-zinc-500">Closed</p>
                </div>
            </div>
        </div>

        <section class="panel mb-6">
            <div class="panel-body grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div>
                    <label>Search</label>
                    <input v-model="filters.search" placeholder="Subject, customer, store" @keydown.enter="apply" />
                </div>
                <div>
                    <label>Status</label>
                    <select v-model="filters.status" @change="apply">
                        <option value="">All</option>
                        <option v-for="status in props.statuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                    </select>
                </div>
                <div>
                    <label>Module</label>
                    <select v-model="filters.module" @change="apply">
                        <option value="">All</option>
                        <option v-for="module in props.modules" :key="module" :value="module">{{ statusLabel(module) }}</option>
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
                <div class="flex items-end"><button class="btn btn-primary w-full" @click="apply">Apply</button></div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[430px_1fr]">
            <section class="panel">
                <div class="panel-header">
                    <h3 class="text-sm font-bold text-zinc-950">Tickets</h3>
                </div>
                <div class="divide-y divide-zinc-100">
                    <Link
                        v-for="ticket in props.tickets.data"
                        :key="ticket.id"
                        :href="`/admin/support?ticket=${ticket.id}`"
                        class="block px-4 py-3 transition hover:bg-zinc-50"
                        :class="selected?.id === ticket.id ? 'bg-teal-50' : ''"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-zinc-950">{{ ticket.subject }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ ticket.account?.name ?? 'Unknown customer' }} · {{ ticket.store?.shop_domain ?? 'No store' }}</p>
                                <p class="mt-1 text-xs text-zinc-400">Last message {{ formatDate(ticket.last_message_at) }}</p>
                            </div>
                            <span class="badge" :class="ticket.status === 'waiting_admin' ? 'badge-failed' : 'badge-success'">{{ statusLabel(ticket.status) }}</span>
                        </div>
                    </Link>
                    <div v-if="!props.tickets.data.length" class="px-4 py-8 text-sm text-zinc-500">No support requests found.</div>
                </div>
            </section>

            <section class="panel min-h-[680px]">
                <template v-if="selected">
                    <div class="panel-header flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-bold text-zinc-950">{{ selected.subject }}</h3>
                            <p class="mt-1 text-xs text-zinc-500">Opened {{ formatDate(selected.created_at) }} · {{ selected.messages?.length ?? 0 }} messages</p>
                        </div>
                        <Link v-if="selected.account" :href="`/admin/accounts/${selected.account.id}`" class="btn btn-secondary">
                            <Building2 class="size-4" />
                            Customer detail
                        </Link>
                    </div>

                    <div class="panel-body space-y-5">
                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3">
                                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-zinc-500"><Building2 class="size-4" /> Customer</div>
                                <p class="mt-2 font-semibold text-zinc-950">{{ selected.account?.name ?? '-' }}</p>
                                <p class="text-xs text-zinc-500">{{ selected.account?.plan_key ?? '-' }} · {{ selected.account?.billing_email ?? selected.opened_by?.email ?? '-' }}</p>
                            </div>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3">
                                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-zinc-500"><Store class="size-4" /> Store</div>
                                <p class="mt-2 font-semibold text-zinc-950">{{ selected.store?.name ?? '-' }}</p>
                                <p class="text-xs text-zinc-500">{{ selected.store?.shop_domain ?? '-' }} · {{ selected.store?.status ?? '-' }}</p>
                            </div>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3">
                                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-zinc-500"><TicketCheck class="size-4" /> Ticket</div>
                                <p class="mt-2 font-semibold text-zinc-950">{{ statusLabel(selected.status) }}</p>
                                <p class="text-xs text-zinc-500">{{ statusLabel(selected.priority) }} · {{ statusLabel(selected.module) }}</p>
                            </div>
                        </div>

                        <form class="grid gap-3 rounded-md border border-zinc-200 p-4 md:grid-cols-4" @submit.prevent="updateTicket">
                            <div>
                                <label>Status</label>
                                <select v-model="updateForm.status">
                                    <option v-for="status in props.statuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                                </select>
                            </div>
                            <div>
                                <label>Priority</label>
                                <select v-model="updateForm.priority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div>
                                <label>Module</label>
                                <select v-model="updateForm.module">
                                    <option v-for="module in props.modules" :key="module" :value="module">{{ statusLabel(module) }}</option>
                                </select>
                            </div>
                            <div class="flex items-end"><button class="btn btn-secondary w-full" :disabled="updateForm.processing">Update</button></div>
                        </form>

                        <div class="space-y-3">
                            <div
                                v-for="message in selected.messages"
                                :key="message.id"
                                class="rounded-md border p-4"
                                :class="message.sender_type === 'admin' ? 'border-teal-100 bg-teal-50' : 'border-zinc-200 bg-white'"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-bold text-zinc-950">{{ senderLabel(message) }}</p>
                                    <p class="text-xs text-zinc-500">{{ formatDate(message.created_at) }}</p>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-zinc-700">{{ message.body }}</p>
                            </div>
                        </div>

                        <form class="space-y-3 border-t border-zinc-100 pt-4" @submit.prevent="sendReply">
                            <label>Admin reply</label>
                            <textarea v-model="replyForm.body" rows="5" placeholder="Write a helpful reply. The customer will receive this inside the app and by email."></textarea>
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-56">
                                    <label>After sending</label>
                                    <select v-model="replyForm.status">
                                        <option value="waiting_customer">Wait for customer</option>
                                        <option value="open">Keep open</option>
                                        <option value="closed">Close ticket</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary" :disabled="replyForm.processing">
                                    <Send class="size-4" />
                                    Send reply
                                </button>
                            </div>
                        </form>
                    </div>
                </template>
                <div v-else class="grid min-h-[520px] place-items-center p-8 text-center">
                    <div>
                        <MessageCircle class="mx-auto size-10 text-zinc-400" />
                        <h3 class="mt-4 text-lg font-bold text-zinc-950">Select a support ticket</h3>
                        <p class="mt-2 max-w-md text-sm text-zinc-500">Choose a ticket from the inbox to view customer details, store context and the full conversation.</p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
