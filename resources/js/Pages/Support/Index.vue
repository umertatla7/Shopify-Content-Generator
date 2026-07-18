<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { LifeBuoy, MessageCircle, Send, Store, XCircle } from 'lucide-vue-next';

const props = defineProps({
    tickets: Object,
    selectedTicket: Object,
    stores: Array,
    filters: Object,
    modules: Array,
});

const ticketForm = useForm({
    subject: '',
    body: '',
    module: 'general',
    priority: 'normal',
    shopify_store_id: props.stores?.[0]?.id ?? '',
});

const replyForm = useForm({
    body: '',
});

const selected = computed(() => props.selectedTicket);
const statusLabel = (value) => String(value || 'open').replaceAll('_', ' ');
const formatDate = (value) => value ? new Date(value).toLocaleString() : '-';
const senderLabel = (message) => message.sender_type === 'admin' ? 'GrowShopHigh support' : (message.user?.name ?? 'You');

const createTicket = () => ticketForm.post('/help/tickets', {
    preserveScroll: true,
    onSuccess: () => ticketForm.reset('subject', 'body'),
});

const sendReply = () => {
    if (!selected.value) return;

    replyForm.post(`/help/tickets/${selected.value.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => replyForm.reset('body'),
    });
};

const closeTicket = () => {
    if (!selected.value) return;

    router.patch(`/help/tickets/${selected.value.id}/close`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Support" />
    <AppLayout>
        <template #title>Support</template>

        <div class="mb-5">
            <h2 class="text-2xl font-bold text-zinc-950">Support inbox</h2>
            <p class="mt-1 text-sm text-zinc-500">Send a question to GrowShopHigh support and keep the full conversation connected to this Shopify workspace.</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-[420px_1fr]">
            <div class="space-y-6">
                <section class="panel">
                    <div class="panel-header">
                        <div class="flex items-center gap-2">
                            <LifeBuoy class="size-4 text-teal-700" />
                            <h3 class="text-sm font-bold text-zinc-950">New support request</h3>
                        </div>
                    </div>
                    <form class="panel-body space-y-4" @submit.prevent="createTicket">
                        <div>
                            <label>Subject</label>
                            <input v-model="ticketForm.subject" placeholder="Example: Product sync is not updating" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label>Module</label>
                                <select v-model="ticketForm.module">
                                    <option v-for="module in props.modules" :key="module" :value="module">{{ statusLabel(module) }}</option>
                                </select>
                            </div>
                            <div>
                                <label>Priority</label>
                                <select v-model="ticketForm.priority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label>Store</label>
                            <select v-model="ticketForm.shopify_store_id">
                                <option value="">Current workspace</option>
                                <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }} · {{ store.shop_domain }}</option>
                            </select>
                        </div>
                        <div>
                            <label>Message</label>
                            <textarea v-model="ticketForm.body" rows="6" placeholder="Tell us what happened, what page you were on, and what you expected to happen."></textarea>
                        </div>
                        <button class="btn btn-primary w-full" :disabled="ticketForm.processing">
                            <Send class="size-4" />
                            Send request
                        </button>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h3 class="text-sm font-bold text-zinc-950">Your requests</h3>
                    </div>
                    <div class="divide-y divide-zinc-100">
                        <Link
                            v-for="ticket in props.tickets.data"
                            :key="ticket.id"
                            :href="`/help?ticket=${ticket.id}`"
                            class="block px-4 py-3 transition hover:bg-zinc-50"
                            :class="selected?.id === ticket.id ? 'bg-teal-50' : ''"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-zinc-950">{{ ticket.subject }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ ticket.store?.name ?? 'Workspace' }} · {{ ticket.messages_count }} message{{ ticket.messages_count === 1 ? '' : 's' }}</p>
                                </div>
                                <span class="badge" :class="ticket.status === 'closed' ? 'badge-failed' : 'badge-success'">{{ statusLabel(ticket.status) }}</span>
                            </div>
                        </Link>
                        <div v-if="!props.tickets.data.length" class="px-4 py-8 text-sm text-zinc-500">No support requests yet.</div>
                    </div>
                </section>
            </div>

            <section class="panel min-h-[620px]">
                <template v-if="selected">
                    <div class="panel-header flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-bold text-zinc-950">{{ selected.subject }}</h3>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ statusLabel(selected.status) }} · {{ statusLabel(selected.module) }} · Last message {{ formatDate(selected.last_message_at) }}
                            </p>
                        </div>
                        <button v-if="selected.status !== 'closed'" type="button" class="btn btn-secondary" @click="closeTicket">
                            <XCircle class="size-4" />
                            Close
                        </button>
                    </div>

                    <div class="panel-body space-y-4">
                        <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-600">
                            <div class="flex items-center gap-2 font-semibold text-zinc-800"><Store class="size-4" /> Store context</div>
                            <p class="mt-1">{{ selected.store?.name ?? 'Workspace' }} · {{ selected.store?.shop_domain ?? 'No connected store selected' }}</p>
                        </div>

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

                        <form v-if="selected.status !== 'closed'" class="space-y-3 border-t border-zinc-100 pt-4" @submit.prevent="sendReply">
                            <label>Reply</label>
                            <textarea v-model="replyForm.body" rows="4" placeholder="Add more information or reply to support."></textarea>
                            <button class="btn btn-primary" :disabled="replyForm.processing">
                                <MessageCircle class="size-4" />
                                Send reply
                            </button>
                        </form>
                        <div v-else class="rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-500">This request is closed. Create a new support request if you need more help.</div>
                    </div>
                </template>
                <div v-else class="grid min-h-[520px] place-items-center p-8 text-center">
                    <div>
                        <LifeBuoy class="mx-auto size-10 text-zinc-400" />
                        <h3 class="mt-4 text-lg font-bold text-zinc-950">Select a request</h3>
                        <p class="mt-2 max-w-md text-sm text-zinc-500">Choose an existing request from the left, or create a new one if you need help.</p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
