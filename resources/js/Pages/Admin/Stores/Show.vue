<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { AlertTriangle, Coins, Cpu, DollarSign, RefreshCw, Send, Sparkles } from 'lucide-vue-next';

const props = defineProps({
    store: Object,
    aiCostSummary: Object,
    creditsUsed: Object,
    recentFailures: Object,
    activity: Array,
    recentGenerations: Array,
});

const money = (value) => `$${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`;
const number = (value) => Number(value || 0).toLocaleString();
const badgeClass = (status) => `badge-${status || 'pending'}`;
const titleCase = (value) => String(value || '').replaceAll('_', ' ');
</script>

<template>
    <Head :title="props.store.name" />
    <AppLayout>
        <template #title>Store Detail</template>

        <div class="mb-4">
            <Link href="/admin/stores" class="text-sm font-semibold text-teal-700">Back to stores</Link>
            <h2 class="mt-1 text-2xl font-bold text-zinc-950">{{ props.store.name }}</h2>
            <p class="text-sm text-zinc-500">{{ props.store.shop_domain }} · {{ props.store.account?.name }} · {{ props.store.account?.plan_key }}</p>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Plan</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-950">{{ props.store.account?.plan_key || '-' }}</p>
                        <p class="mt-1 text-xs text-zinc-500">Account credits {{ number(props.store.account?.credit_balance) }}/{{ number(props.store.account?.monthly_credit_allowance) }}</p>
                    </div>
                    <Coins class="size-5 text-amber-700" />
                </div>
            </div>
            <div class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Credits used</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-950">{{ number(props.creditsUsed.current_month) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ number(props.creditsUsed.all_time) }} all time</p>
                    </div>
                    <Coins class="size-5 text-teal-700" />
                </div>
            </div>
            <div class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">AI cost</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-950">{{ money(props.aiCostSummary.current_month.estimated_cost) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ money(props.aiCostSummary.all_time.estimated_cost) }} all time</p>
                    </div>
                    <DollarSign class="size-5 text-emerald-700" />
                </div>
            </div>
            <div class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Store health</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-950">{{ props.store.status }}</p>
                        <p class="mt-1 text-xs text-zinc-500">Synced {{ props.store.last_synced_at ? new Date(props.store.last_synced_at).toLocaleString() : 'never' }}</p>
                    </div>
                    <Sparkles class="size-5 text-indigo-700" />
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
            <section class="panel">
                <div class="panel-header">
                    <h3 class="text-sm font-bold text-zinc-950">Store summary</h3>
                </div>
                <div class="panel-body grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-md border border-zinc-200 p-3"><div class="text-xs text-zinc-500">Products</div><div class="mt-1 text-lg font-bold text-zinc-950">{{ number(props.store.products_count) }}</div></div>
                    <div class="rounded-md border border-zinc-200 p-3"><div class="text-xs text-zinc-500">Collections</div><div class="mt-1 text-lg font-bold text-zinc-950">{{ number(props.store.collections_count) }}</div></div>
                    <div class="rounded-md border border-zinc-200 p-3"><div class="text-xs text-zinc-500">Pages</div><div class="mt-1 text-lg font-bold text-zinc-950">{{ number(props.store.pages_count) }}</div></div>
                    <div class="rounded-md border border-zinc-200 p-3"><div class="text-xs text-zinc-500">Portal blogs</div><div class="mt-1 text-lg font-bold text-zinc-950">{{ number(props.store.blogs_count) }}</div></div>
                    <div class="rounded-md border border-zinc-200 p-3"><div class="text-xs text-zinc-500">Shopify blogs</div><div class="mt-1 text-lg font-bold text-zinc-950">{{ number(props.store.existing_blogs_count) }}</div></div>
                    <div class="rounded-md border border-zinc-200 p-3"><div class="text-xs text-zinc-500">Visibility reports</div><div class="mt-1 text-lg font-bold text-zinc-950">{{ number(props.store.visibility_reports_count) }}</div></div>
                    <div class="rounded-md border border-zinc-200 p-3"><div class="text-xs text-zinc-500">Latest sync</div><div class="mt-1 text-sm font-semibold text-zinc-950">{{ props.store.latest_sync_log?.status || '-' }}</div></div>
                    <div class="rounded-md border border-zinc-200 p-3"><div class="text-xs text-zinc-500">Latest analysis</div><div class="mt-1 text-sm font-semibold text-zinc-950">{{ props.store.latest_analysis?.status || '-' }}</div></div>
                    <div class="rounded-md border border-zinc-200 p-3"><div class="text-xs text-zinc-500">Latest AI visibility</div><div class="mt-1 text-sm font-semibold text-zinc-950">{{ props.store.latest_visibility_report?.overall_score ?? '-' }}</div></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h3 class="text-sm font-bold text-zinc-950">Credential status</h3>
                </div>
                <div class="panel-body space-y-3">
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs text-zinc-500">Scopes</div>
                        <div class="mt-1 text-sm text-zinc-700">{{ props.store.credential?.scopes?.join(', ') || '-' }}</div>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs text-zinc-500">Credential updated</div>
                        <div class="mt-1 text-sm text-zinc-700">{{ props.store.credential?.updated_at ? new Date(props.store.credential.updated_at).toLocaleString() : '-' }}</div>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs text-zinc-500">Token expiry</div>
                        <div class="mt-1 text-sm text-zinc-700">{{ props.store.credential?.expires_at ? new Date(props.store.credential.expires_at).toLocaleString() : 'No expiry recorded' }}</div>
                    </div>
                </div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Sync failures</h3><RefreshCw class="size-4 text-zinc-500" /></div>
                <div class="panel-body space-y-3">
                    <div v-for="item in props.recentFailures.sync" :key="`sync-${item.id}`" class="rounded-md border border-zinc-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="badge" :class="badgeClass(item.status)">{{ item.status }}</span>
                            <span class="text-xs text-zinc-500">{{ item.created_at ? new Date(item.created_at).toLocaleString() : '-' }}</span>
                        </div>
                        <p class="mt-2 text-sm text-zinc-700">{{ item.error_message || 'No error captured.' }}</p>
                    </div>
                    <div v-if="!props.recentFailures.sync.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No recent sync failures.</div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Publish failures</h3><Send class="size-4 text-zinc-500" /></div>
                <div class="panel-body space-y-3">
                    <div v-for="item in props.recentFailures.publish" :key="`publish-${item.id}`" class="rounded-md border border-zinc-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="badge" :class="badgeClass(item.status)">{{ item.status }}</span>
                            <span class="text-xs text-zinc-500">{{ item.created_at ? new Date(item.created_at).toLocaleString() : '-' }}</span>
                        </div>
                        <div class="mt-2 text-sm font-semibold text-zinc-950">{{ item.blog?.title || 'Unknown blog' }}</div>
                        <p class="mt-1 text-sm text-zinc-700">{{ item.error_message || 'No error captured.' }}</p>
                    </div>
                    <div v-if="!props.recentFailures.publish.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No recent publish failures.</div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Analyze failures</h3><AlertTriangle class="size-4 text-zinc-500" /></div>
                <div class="panel-body space-y-3">
                    <div v-for="item in props.recentFailures.analysis" :key="`analysis-${item.id}`" class="rounded-md border border-zinc-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="badge" :class="badgeClass(item.status)">{{ item.status }}</span>
                            <span class="text-xs text-zinc-500">{{ item.created_at ? new Date(item.created_at).toLocaleString() : '-' }}</span>
                        </div>
                        <p class="mt-2 text-sm text-zinc-700">{{ item.error_message || 'No error captured.' }}</p>
                    </div>
                    <div v-if="!props.recentFailures.analysis.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No recent analysis failures.</div>
                </div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_1fr]">
            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Recent AI generations</h3><Cpu class="size-4 text-zinc-500" /></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>When</th><th>Type</th><th>User</th><th>Tokens</th><th>Cost</th></tr></thead>
                        <tbody>
                            <tr v-for="row in props.recentGenerations" :key="row.id">
                                <td>{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</td>
                                <td>{{ titleCase(row.type) }}</td>
                                <td>{{ row.user?.email || 'System' }}</td>
                                <td>{{ number((row.token_usage?.prompt_tokens || 0) + (row.token_usage?.completion_tokens || 0) + (row.token_usage?.cached_prompt_tokens || 0)) }}</td>
                                <td>{{ money(row.cost) }}</td>
                            </tr>
                            <tr v-if="!props.recentGenerations.length"><td colspan="5" class="text-zinc-500">No AI generation records for this store yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Credit usage</h3><Coins class="size-4 text-zinc-500" /></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>When</th><th>User</th><th>Credits</th><th>Action</th></tr></thead>
                        <tbody>
                            <tr v-for="row in props.creditsUsed.recent" :key="row.id">
                                <td>{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</td>
                                <td>{{ row.user?.email || 'System' }}</td>
                                <td>{{ number(row.quantity) }}</td>
                                <td>{{ row.metadata?.action || '-' }}</td>
                            </tr>
                            <tr v-if="!props.creditsUsed.recent.length"><td colspan="4" class="text-zinc-500">No credit usage recorded for this store yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="panel mt-6">
            <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Activity timeline</h3></div>
            <div class="panel-body space-y-3">
                <div v-for="item in props.activity" :key="item.id" class="rounded-md border border-zinc-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-semibold text-zinc-950">{{ item.action }}</div>
                        <div class="text-xs text-zinc-500">{{ item.created_at ? new Date(item.created_at).toLocaleString() : '-' }}</div>
                    </div>
                    <p class="mt-1 text-sm text-zinc-600">{{ item.description || '-' }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ item.user?.email || 'System' }} · {{ item.account?.name || 'Platform' }}</p>
                </div>
                <div v-if="!props.activity.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No activity recorded for this store yet.</div>
            </div>
        </section>
    </AppLayout>
</template>
