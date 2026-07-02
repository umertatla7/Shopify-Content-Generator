<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowRight, BadgeCheck, CreditCard, RefreshCw, ShoppingBag, Sparkles } from 'lucide-vue-next';

const props = defineProps({
    plans: Array,
    currentPlanKey: String,
    currentSubscription: Object,
    primaryStore: Object,
    credits: Object,
    checklist: Object,
});

const visiblePlans = computed(() => props.plans.filter((plan) => Number(plan.monthly_price) > 0));
const currentPlan = computed(() => props.plans.find((plan) => plan.key === props.currentPlanKey) ?? props.plans[0] ?? null);

const formatPrice = (plan) => {
    if (!plan || Number(plan.monthly_price) <= 0) return 'Trial setup';
    return `$${Number(plan.monthly_price).toFixed(2)}/month`;
};
const trialLabel = (plan) => Number(plan?.trial_days || 0) > 0 ? `${Number(plan.trial_days)}-day trial` : null;

const formatLimit = (value, emptyLabel = 'Unlimited') => {
    if (value === null || value === undefined) return emptyLabel;
    return Number(value).toLocaleString();
};

const subscribe = (plan) => router.post(`/billing/plans/${plan.id}/subscribe`, {}, { preserveScroll: true });
const syncStore = () => {
    if (!props.primaryStore?.id) return;
    router.post(`/stores/${props.primaryStore.id}/sync`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Getting Started" />
    <AppLayout>
        <template #title>Getting Started</template>

        <div class="space-y-6">
            <section class="panel overflow-hidden">
                <div class="grid gap-6 p-5 lg:grid-cols-[1.2fr_0.8fr] lg:items-start">
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="grid size-12 shrink-0 place-items-center rounded-md bg-teal-50 text-teal-700">
                                <Sparkles class="size-6" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-zinc-950">Your Shopify workspace is ready</h2>
                                <p class="mt-1 text-sm text-zinc-600">
                                    We connected your store. The next step is syncing store data, then choosing the package that fits the amount of content, audit depth, and visibility tracking you want to unlock.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Store</div>
                                <div class="mt-2 font-semibold text-zinc-950">{{ props.primaryStore?.name ?? 'Waiting for store' }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ props.primaryStore?.shop_domain ?? 'No connected Shopify store yet.' }}</div>
                            </div>
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Plan</div>
                                <div class="mt-2 font-semibold text-zinc-950">{{ currentPlan?.name ?? 'Trial setup' }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ formatPrice(currentPlan) }}</div>
                            </div>
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Credits</div>
                                <div class="mt-2 font-semibold text-zinc-950">{{ props.credits.balance.toLocaleString() }}</div>
                                <div class="mt-1 text-sm text-zinc-500">Ready for first drafts and sync-assisted setup.</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4">
                        <div class="text-sm font-bold text-zinc-950">Setup checklist</div>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 bg-white p-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">Store connected</div>
                                    <div class="text-zinc-500">Shopify install and account creation completed.</div>
                                </div>
                                <span class="badge" :class="props.checklist.store_connected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ props.checklist.store_connected ? 'Done' : 'Pending' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 bg-white p-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">Catalog sync</div>
                                    <div class="text-zinc-500">Products, collections, pages, and blogs need one sync before content work.</div>
                                </div>
                                <span class="badge" :class="props.checklist.catalog_synced ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ props.checklist.catalog_synced ? 'Done' : 'Next step' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 bg-white p-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">Plan access</div>
                                    <div class="text-zinc-500">Plan changes go through Shopify billing, and paid tiers can include a free trial before charges begin.</div>
                                </div>
                                <span class="badge" :class="props.checklist.free_plan_active || props.checklist.has_paid_subscription ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-100 text-zinc-800'">
                                    {{ props.checklist.has_paid_subscription ? 'Paid plan active' : 'Plan ready' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1fr_1fr]">
                <div class="panel p-5">
                    <div class="flex items-start gap-3">
                        <div class="grid size-10 shrink-0 place-items-center rounded-md bg-teal-50 text-teal-700">
                            <ShoppingBag class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-zinc-950">1. Sync your Shopify store</h3>
                            <p class="mt-1 text-sm text-zinc-600">
                                Pull products, collections, pages, and existing blog content into GrowShopHigh so AI generation has real store context.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-md bg-zinc-100 p-3 text-center">
                            <div class="text-lg font-bold text-zinc-950">{{ props.primaryStore?.products_count ?? 0 }}</div>
                            <div class="text-xs text-zinc-500">Products</div>
                        </div>
                        <div class="rounded-md bg-zinc-100 p-3 text-center">
                            <div class="text-lg font-bold text-zinc-950">{{ props.primaryStore?.collections_count ?? 0 }}</div>
                            <div class="text-xs text-zinc-500">Collections</div>
                        </div>
                        <div class="rounded-md bg-zinc-100 p-3 text-center">
                            <div class="text-lg font-bold text-zinc-950">{{ props.primaryStore?.pages_count ?? 0 }}</div>
                            <div class="text-xs text-zinc-500">Pages</div>
                        </div>
                        <div class="rounded-md bg-zinc-100 p-3 text-center">
                            <div class="text-lg font-bold text-zinc-950">{{ props.primaryStore?.existing_blogs_count ?? 0 }}</div>
                            <div class="text-xs text-zinc-500">Blogs</div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <button class="btn btn-primary" type="button" :disabled="!props.primaryStore?.id" @click="syncStore">
                            <RefreshCw class="size-4" />
                            Sync store now
                        </button>
                        <Link href="/store-audit" class="btn btn-secondary">
                            Open store audit
                        </Link>
                    </div>
                </div>

                <div class="panel p-5">
                    <div class="flex items-start gap-3">
                        <div class="grid size-10 shrink-0 place-items-center rounded-md bg-indigo-50 text-indigo-700">
                            <CreditCard class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-zinc-950">2. Choose how you want to start</h3>
                            <p class="mt-1 text-sm text-zinc-600">
                                Every install can begin with a trial. Choose the package that matches the amount of content, reporting, and visibility tracking you want to unlock.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="plan in visiblePlans"
                            :key="plan.id"
                            class="flex flex-col gap-3 rounded-md border border-zinc-200 p-4 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <div class="font-semibold text-zinc-950">{{ plan.name }}</div>
                                    <span v-if="plan.key === props.currentPlanKey" class="badge bg-teal-100 text-teal-800">
                                        <BadgeCheck class="mr-1 size-3.5" />
                                        Current
                                    </span>
                                </div>
                                <div class="mt-1 text-sm text-zinc-500">{{ formatPrice(plan) }}</div>
                                <div class="mt-2 text-xs text-zinc-500">
                                    <span v-if="trialLabel(plan)">{{ trialLabel(plan) }}, </span>
                                    {{ formatLimit(plan.monthly_credit_allowance) }} credits,
                                    {{ formatLimit(plan.monthly_blog_limit) }} blogs/month,
                                    {{ formatLimit(plan.monthly_ai_visibility_report_limit) }} AI visibility reports
                                </div>
                            </div>

                            <button
                                class="btn shrink-0"
                                :class="plan.key === props.currentPlanKey ? 'btn-secondary' : 'btn-primary'"
                                type="button"
                                :disabled="plan.key === props.currentPlanKey"
                                @click="subscribe(plan)"
                            >
                                <CreditCard class="size-4" />
                                {{ plan.key === props.currentPlanKey ? 'Active now' : 'Start in Shopify' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-base font-bold text-zinc-950">3. Continue into the workspace</h3>
                        <p class="mt-1 text-sm text-zinc-600">
                            Once your first sync completes, head into the dashboard and start with store analysis, topics, AI Visibility, or product content.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <Link href="/billing" class="btn btn-secondary">
                            <CreditCard class="size-4" />
                            Open billing
                        </Link>
                        <Link href="/dashboard" class="btn btn-primary">
                            <ArrowRight class="size-4" />
                            Continue to dashboard
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
