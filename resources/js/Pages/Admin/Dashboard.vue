<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Activity, Building2, Calculator, DollarSign, FileText, Send, ShoppingBag, Sparkles, TrendingUp, Users } from 'lucide-vue-next';

const props = defineProps({
    stats: Object,
    aiPricing: Object,
    aiCostSummary: Object,
    accountCosts: Array,
    storeCosts: Array,
    userCosts: Array,
    generationTypeCosts: Array,
    planFormula: Object,
    blogStatusCounts: Array,
    accounts: Array,
    users: Array,
    activity: Array,
});

const cards = [
    ['Accounts', 'accounts', Building2, 'text-teal-700'],
    ['Users', 'users', Users, 'text-sky-700'],
    ['Stores', 'stores', ShoppingBag, 'text-amber-700'],
    ['Blogs', 'blogs', FileText, 'text-zinc-800'],
    ['AI generations', 'ai_generations', Sparkles, 'text-indigo-700'],
    ['Publishes', 'shopify_publishes', Send, 'text-emerald-700'],
];

const money = (value) => `$${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`;
const number = (value) => Number(value || 0).toLocaleString();
const percent = (value) => value === null || value === undefined ? '-' : `${Number(value).toFixed(1)}%`;
const titleCase = (value) => String(value || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Admin Dashboard" />
    <AppLayout>
        <template #title>Admin Dashboard</template>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="[label, key, Icon, color] in cards" :key="key" class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-zinc-500">{{ label }}</p>
                        <p class="mt-2 text-3xl font-bold text-zinc-950">{{ props.stats[key] ?? 0 }}</p>
                    </div>
                    <div class="grid size-10 place-items-center rounded-md bg-zinc-100" :class="color">
                        <component :is="Icon" class="size-5" />
                    </div>
                </div>
            </div>
        </div>

        <section class="panel mt-6">
            <div class="panel-header">
                <div>
                    <h2 class="text-sm font-bold text-zinc-950">AI Cost Control</h2>
                    <p class="text-xs text-zinc-500">
                        Current model: {{ props.aiPricing.model }} · input {{ money(props.aiPricing.input_per_million) }}/1M · cached {{ money(props.aiPricing.cached_input_per_million) }}/1M · output {{ money(props.aiPricing.output_per_million) }}/1M
                    </p>
                </div>
                <DollarSign class="size-4 text-emerald-700" />
            </div>
            <div class="panel-body grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-md border border-zinc-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-zinc-500">AI cost this month</p>
                        <DollarSign class="size-4 text-emerald-700" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-zinc-950">{{ money(props.aiCostSummary.current_month.estimated_cost) }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ number(props.aiCostSummary.current_month.total_tokens) }} tokens · {{ number(props.aiCostSummary.current_month.generations) }} generations</p>
                </div>
                <div class="rounded-md border border-zinc-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-zinc-500">AI cost all time</p>
                        <TrendingUp class="size-4 text-sky-700" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-zinc-950">{{ money(props.aiCostSummary.all_time.estimated_cost) }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ number(props.aiCostSummary.all_time.total_tokens) }} tokens · {{ number(props.aiCostSummary.all_time.generations) }} generations</p>
                </div>
                <div class="rounded-md border border-zinc-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-zinc-500">Input / output tokens</p>
                        <Sparkles class="size-4 text-indigo-700" />
                    </div>
                    <p class="mt-2 text-xl font-bold text-zinc-950">{{ number(props.aiCostSummary.current_month.input_tokens) }} / {{ number(props.aiCostSummary.current_month.output_tokens) }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ number(props.aiCostSummary.current_month.cached_input_tokens) }} cached input tokens this month</p>
                </div>
                <div class="rounded-md border border-zinc-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-zinc-500">Formula example</p>
                        <Calculator class="size-4 text-amber-700" />
                    </div>
                    <p class="mt-2 text-xl font-bold text-zinc-950">{{ money(props.planFormula.target_price) }} plan</p>
                    <p class="mt-1 text-xs text-zinc-500">
                        {{ props.planFormula.blogs }} blogs · {{ props.planFormula.products }} products · {{ props.planFormula.collections }} collections
                    </p>
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_1fr]">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-950">Plan Pricing Formula</h2>
                        <p class="text-xs text-zinc-500">Uses average actual generation cost when available, otherwise configured estimates.</p>
                    </div>
                    <Calculator class="size-4 text-zinc-500" />
                </div>
                <div class="panel-body space-y-4">
                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-md bg-zinc-100 p-3">
                            <div class="text-xs font-semibold text-zinc-500">Blog unit cost</div>
                            <div class="mt-1 text-lg font-bold text-zinc-950">{{ money(props.planFormula.unit_costs.blog) }}</div>
                        </div>
                        <div class="rounded-md bg-zinc-100 p-3">
                            <div class="text-xs font-semibold text-zinc-500">Product desc.</div>
                            <div class="mt-1 text-lg font-bold text-zinc-950">{{ money(props.planFormula.unit_costs.product_description) }}</div>
                        </div>
                        <div class="rounded-md bg-zinc-100 p-3">
                            <div class="text-xs font-semibold text-zinc-500">Collection desc.</div>
                            <div class="mt-1 text-lg font-bold text-zinc-950">{{ money(props.planFormula.unit_costs.collection_description) }}</div>
                        </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="text-xs font-semibold uppercase text-zinc-500">Estimated raw AI cost</div>
                            <div class="mt-1 text-2xl font-bold text-zinc-950">{{ money(props.planFormula.estimated_raw_ai_cost) }}</div>
                        </div>
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="text-xs font-semibold uppercase text-zinc-500">With {{ props.planFormula.safety_multiplier }}x buffer</div>
                            <div class="mt-1 text-2xl font-bold text-zinc-950">{{ money(props.planFormula.estimated_buffered_ai_cost) }}</div>
                        </div>
                    </div>
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                        <div class="grid gap-3 md:grid-cols-3">
                            <div>
                                <div class="text-xs font-semibold uppercase text-emerald-800">Profit at {{ money(props.planFormula.target_price) }}</div>
                                <div class="mt-1 text-xl font-bold text-zinc-950">{{ money(props.planFormula.estimated_profit_at_target_price) }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase text-emerald-800">Margin</div>
                                <div class="mt-1 text-xl font-bold text-zinc-950">{{ percent(props.planFormula.estimated_margin_at_target_price) }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase text-emerald-800">Price for {{ props.planFormula.target_margin }}% margin</div>
                                <div class="mt-1 text-xl font-bold text-zinc-950">{{ money(props.planFormula.suggested_price_for_target_margin) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Generation Cost by Type</h2>
                    <Sparkles class="size-4 text-zinc-500" />
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Type</th><th>Runs</th><th>Avg tokens</th><th>Avg cost</th><th>Total cost</th></tr></thead>
                        <tbody>
                            <tr v-for="row in props.generationTypeCosts" :key="row.type">
                                <td class="capitalize">{{ titleCase(row.type) }}</td>
                                <td>{{ number(row.generations) }}</td>
                                <td>{{ number(row.average_tokens) }}</td>
                                <td>{{ money(row.average_cost) }}</td>
                                <td class="font-semibold text-zinc-950">{{ money(row.estimated_cost) }}</td>
                            </tr>
                            <tr v-if="!props.generationTypeCosts.length"><td colspan="5" class="text-zinc-500">No AI usage recorded yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="panel">
                <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">Customer AI Cost This Month</h2></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Customer</th><th>Plan</th><th>Cost</th><th>Revenue</th><th>Margin</th></tr></thead>
                        <tbody>
                            <tr v-for="row in props.accountCosts" :key="row.id">
                                <td><Link :href="`/admin/accounts/${row.id}`" class="font-semibold text-zinc-950 hover:text-teal-700">{{ row.name }}</Link><div class="text-xs text-zinc-500">{{ number(row.total_tokens) }} tokens</div></td>
                                <td>{{ row.plan_key }}</td>
                                <td>{{ money(row.estimated_cost) }}</td>
                                <td>{{ money(row.monthly_revenue) }}</td>
                                <td>{{ percent(row.gross_margin) }}</td>
                            </tr>
                            <tr v-if="!props.accountCosts.length"><td colspan="5" class="text-zinc-500">No customer AI usage this month.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">Store AI Cost This Month</h2></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Store</th><th>Customer</th><th>Tokens</th><th>Cost</th></tr></thead>
                        <tbody>
                            <tr v-for="row in props.storeCosts" :key="row.id">
                                <td class="font-semibold text-zinc-950">{{ row.name }}</td>
                                <td>{{ row.account }}</td>
                                <td>{{ number(row.total_tokens) }}</td>
                                <td>{{ money(row.estimated_cost) }}</td>
                            </tr>
                            <tr v-if="!props.storeCosts.length"><td colspan="4" class="text-zinc-500">No store AI usage this month.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">User AI Cost This Month</h2></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>User</th><th>Account</th><th>Tokens</th><th>Cost</th></tr></thead>
                        <tbody>
                            <tr v-for="row in props.userCosts" :key="row.id">
                                <td><span class="font-semibold text-zinc-950">{{ row.name }}</span><div class="text-xs text-zinc-500">{{ row.email }}</div></td>
                                <td>{{ row.account }}</td>
                                <td>{{ number(row.total_tokens) }}</td>
                                <td>{{ money(row.estimated_cost) }}</td>
                            </tr>
                            <tr v-if="!props.userCosts.length"><td colspan="4" class="text-zinc-500">No user AI usage this month.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_1fr]">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Recent Accounts</h2>
                    <Link href="/admin/accounts" class="btn btn-secondary">Open</Link>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Account</th><th>Plan</th><th>Users</th><th>Stores</th><th>Blogs</th></tr></thead>
                        <tbody>
                            <tr v-for="account in props.accounts" :key="account.id">
                                <td><Link :href="`/admin/accounts/${account.id}`" class="font-semibold text-zinc-950 hover:text-teal-700">{{ account.name }}</Link></td>
                                <td>{{ account.plan_key }}</td>
                                <td>{{ account.users_count }}</td>
                                <td>{{ account.stores_count }}</td>
                                <td>{{ account.blogs_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Blog Status</h2>
                    <FileText class="size-4 text-zinc-500" />
                </div>
                <div class="panel-body space-y-3">
                    <div v-for="row in props.blogStatusCounts" :key="row.status" class="flex items-center justify-between rounded-md border border-zinc-200 p-3">
                        <span class="badge" :class="`badge-${row.status}`">{{ row.status.replace('_', ' ') }}</span>
                        <span class="text-sm font-bold text-zinc-950">{{ row.total }}</span>
                    </div>
                    <p v-if="!props.blogStatusCounts.length" class="text-sm text-zinc-500">No blog activity yet.</p>
                </div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Recent Users</h2>
                    <Link href="/admin/users" class="btn btn-secondary">Manage</Link>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>User</th><th>Account</th><th>Role</th><th>Joined</th></tr></thead>
                        <tbody>
                            <tr v-for="user in props.users" :key="user.id">
                                <td><Link :href="`/admin/users/${user.id}/edit`" class="font-semibold text-zinc-950 hover:text-teal-700">{{ user.name }}</Link><div class="text-xs text-zinc-500">{{ user.email }}</div></td>
                                <td>{{ user.current_account?.name ?? '-' }}</td>
                                <td>{{ user.global_role }}</td>
                                <td>{{ new Date(user.created_at).toLocaleDateString() }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Recent Activity</h2>
                    <Activity class="size-4 text-zinc-500" />
                </div>
                <div class="panel-body space-y-3">
                    <div v-for="item in props.activity" :key="item.id" class="rounded-md border border-zinc-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-zinc-950">{{ item.action }}</span>
                            <span class="text-xs text-zinc-500">{{ new Date(item.created_at).toLocaleString() }}</span>
                        </div>
                        <p class="mt-1 text-sm text-zinc-600">{{ item.description ?? '-' }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ item.user?.email ?? 'System' }} · {{ item.account?.name ?? 'Platform' }}</p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
