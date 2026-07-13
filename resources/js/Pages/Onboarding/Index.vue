<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InfoHint from '@/Components/InfoHint.vue';
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
const catalogCount = computed(() => (
    Number(props.primaryStore?.products_count ?? 0)
    + Number(props.primaryStore?.collections_count ?? 0)
    + Number(props.primaryStore?.pages_count ?? 0)
    + Number(props.primaryStore?.existing_blogs_count ?? 0)
));
const syncReady = computed(() => Boolean(props.checklist.catalog_synced));
const syncStatusLabel = computed(() => syncReady.value ? 'Catalog synced' : 'Sync required');

const formatPrice = (plan) => {
    if (!plan || Number(plan.monthly_price) <= 0) return 'Trial setup';
    return `$${Number(plan.monthly_price).toFixed(2)}/month`;
};
const trialLabel = (plan) => Number(plan?.trial_days || 0) > 0 ? `${Number(plan.trial_days)}-day trial` : null;

const formatLimit = (value, emptyLabel = 'Unlimited') => {
    if (value === null || value === undefined) return emptyLabel;
    return Number(value).toLocaleString();
};

const implementedFeatureLabels = {
    product_descriptions: 'Product content generation',
    collection_descriptions: 'Collection content generation',
    monthly_blog_generation: 'Topic ideas and blog writing',
    store_audit: 'Store Audit',
    basic_store_audit: 'Store Audit',
    seo_reports: 'AI Store Analysis',
    ai_visibility: 'AI Visibility',
    rank_tracking: 'Keyword Tracking',
    all_features: 'All available modules',
};

const planHighlights = (plan) => {
    const labels = (plan?.features ?? [])
        .map((feature) => implementedFeatureLabels[feature])
        .filter(Boolean);

    return [...new Set(labels)].slice(0, 5);
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
                <div class="grid gap-6 p-5 xl:grid-cols-[1.35fr_0.65fr]">
                    <div class="space-y-5">
                        <div class="flex items-start gap-3">
                            <div class="grid size-12 shrink-0 place-items-center rounded-md bg-teal-50 text-teal-700">
                                <Sparkles class="size-6" />
                            </div>
                            <div class="min-w-0">
                                <div class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">
                                    <BadgeCheck class="size-4" />
                                    Shopify install completed
                                </div>
                                <h2 class="mt-3 text-2xl font-bold text-zinc-950">Sync your store to unlock the workspace</h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600">
                                    Your store is connected. Pull your catalog into GrowShopHigh first so products, collections, blogs, and AI workflows all start with real Shopify data.
                                </p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-teal-200 bg-teal-50/70 p-4 sm:p-5">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="badge" :class="syncReady ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                            {{ syncStatusLabel }}
                                        </span>
                                        <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Step 1 of 3</span>
                                    </div>
                                    <h3 class="mt-3 text-xl font-bold text-zinc-950">Sync your Shopify catalog now</h3>
                                    <p class="mt-2 text-sm leading-6 text-zinc-600">
                                        This first sync imports products, collections, pages, and existing blog content. Until that completes, the rest of the workspace stays locked so you do not land in empty screens.
                                    </p>
                                </div>

                                <div class="flex shrink-0 flex-wrap gap-3">
                                    <button class="btn btn-primary min-w-44 justify-center" type="button" :disabled="!props.primaryStore?.id" @click="syncStore">
                                        <RefreshCw class="size-4" />
                                        {{ syncReady ? 'Sync again' : 'Sync store now' }}
                                    </button>
                                    <Link href="/billing" class="btn btn-secondary min-w-40 justify-center">
                                        <CreditCard class="size-4" />
                                        View plans
                                    </Link>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-4">
                                <div class="rounded-xl border border-white/70 bg-white p-3 text-center shadow-sm">
                                    <div class="text-xl font-bold text-zinc-950">{{ props.primaryStore?.products_count ?? 0 }}</div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Products</div>
                                </div>
                                <div class="rounded-xl border border-white/70 bg-white p-3 text-center shadow-sm">
                                    <div class="text-xl font-bold text-zinc-950">{{ props.primaryStore?.collections_count ?? 0 }}</div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Collections</div>
                                </div>
                                <div class="rounded-xl border border-white/70 bg-white p-3 text-center shadow-sm">
                                    <div class="text-xl font-bold text-zinc-950">{{ props.primaryStore?.pages_count ?? 0 }}</div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Pages</div>
                                </div>
                                <div class="rounded-xl border border-white/70 bg-white p-3 text-center shadow-sm">
                                    <div class="text-xl font-bold text-zinc-950">{{ props.primaryStore?.existing_blogs_count ?? 0 }}</div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Blogs</div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 lg:grid-cols-3">
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Store</div>
                                <div class="mt-2 text-lg font-semibold text-zinc-950">{{ props.primaryStore?.name ?? 'Waiting for store' }}</div>
                                <div class="mt-1 break-words text-sm text-zinc-500">{{ props.primaryStore?.shop_domain ?? 'No connected Shopify store yet.' }}</div>
                            </div>
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Plan</div>
                                <div class="mt-2 text-lg font-semibold text-zinc-950">{{ currentPlan?.name ?? 'Trial setup' }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ formatPrice(currentPlan) }}</div>
                            </div>
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Credits</div>
                                <div class="mt-2 text-lg font-semibold text-zinc-950">{{ props.credits.balance.toLocaleString() }}</div>
                                <div class="mt-1 text-sm text-zinc-500">Ready for your first content generation run.</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-base font-bold text-zinc-950">Setup checklist</div>
                            <span class="badge bg-white text-zinc-700">{{ syncReady ? 'Ready to continue' : 'Sync required' }}</span>
                        </div>

                        <div class="mt-4 space-y-3 text-sm">
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-zinc-950">1. Store connected</div>
                                        <div class="mt-1 text-zinc-500">Shopify install and workspace creation completed.</div>
                                    </div>
                                    <span class="badge bg-emerald-100 text-emerald-800">Done</span>
                                </div>
                            </div>

                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-zinc-950">2. Catalog sync</div>
                                        <div class="mt-1 text-zinc-500">Import your live catalog so content, audit, and visibility modules can work from real data.</div>
                                    </div>
                                    <span class="badge" :class="syncReady ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                        {{ syncReady ? 'Done' : 'Next step' }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-zinc-950">3. Pick your plan</div>
                                        <div class="mt-1 text-zinc-500">Billing goes through Shopify. Start with trial access, then move into the package that matches your usage.</div>
                                    </div>
                                    <span class="badge" :class="props.checklist.has_paid_subscription ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-100 text-zinc-700'">
                                        {{ props.checklist.has_paid_subscription ? 'Paid active' : 'Available now' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-dashed border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-600">
                            <span class="font-semibold text-zinc-950">{{ catalogCount }}</span>
                            total synced records detected so far across products, collections, pages, and blogs.
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                <div class="panel p-5">
                    <div class="flex items-start gap-3">
                        <div class="grid size-10 shrink-0 place-items-center rounded-md bg-indigo-50 text-indigo-700">
                            <CreditCard class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-zinc-950">Choose your starting plan</h3>
                                <InfoHint text="Each package uses Shopify-managed billing. Pick the level that matches how much content and reporting you want after the first sync." />
                            </div>
                            <p class="mt-1 text-sm text-zinc-600">
                                You can review plans now, but the workspace stays focused on sync until your store data lands here.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <div
                            v-for="plan in visiblePlans"
                            :key="plan.id"
                            class="rounded-xl border border-zinc-200 p-4"
                            :class="plan.key === props.currentPlanKey ? 'ring-2 ring-teal-200' : ''"
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
                                <div class="mt-2 text-xs leading-5 text-zinc-500">
                                    <span v-if="trialLabel(plan)">{{ trialLabel(plan) }}, </span>
                                    {{ formatLimit(plan.monthly_credit_allowance) }} credits,
                                    {{ formatLimit(plan.monthly_blog_limit) }} blogs/month,
                                    {{ formatLimit(plan.monthly_topic_limit, '0') }} topics/month
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl bg-zinc-50 p-3 text-sm text-zinc-600">
                                <div class="flex items-center justify-between gap-3">
                                    <span>Products / month</span>
                                    <span class="font-semibold text-zinc-950">{{ formatLimit(plan.product_description_limit, '0') }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <span>Collections / month</span>
                                    <span class="font-semibold text-zinc-950">{{ formatLimit(plan.collection_description_limit, '0') }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <span>Store audits / month</span>
                                    <span class="font-semibold text-zinc-950">{{ formatLimit(plan.monthly_seo_report_limit, '0') }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <span>AI visibility refreshes</span>
                                    <span class="font-semibold text-zinc-950">{{ formatLimit(plan.monthly_ai_visibility_report_limit, '0') }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <span>Tracked keywords</span>
                                    <span class="font-semibold text-zinc-950">{{ formatLimit(plan.tracked_keyword_limit) }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <span>Max blog words</span>
                                    <span class="font-semibold text-zinc-950">{{ formatLimit(plan.max_blog_word_count, '1500') }}</span>
                                </div>
                            </div>

                            <div v-if="planHighlights(plan).length" class="mt-4 space-y-2 text-sm text-zinc-600">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Includes</div>
                                <div
                                    v-for="feature in planHighlights(plan)"
                                    :key="feature"
                                    class="rounded-lg border border-zinc-200 bg-white px-3 py-2"
                                >
                                    {{ feature }}
                                </div>
                            </div>

                            <button
                                class="btn mt-4 w-full"
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

                <div class="panel p-5">
                    <div class="flex items-start gap-3">
                        <div class="grid size-10 shrink-0 place-items-center rounded-md bg-zinc-100 text-zinc-700">
                            <ArrowRight class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-zinc-950">What unlocks after sync</h3>
                            <p class="mt-1 text-sm text-zinc-600">
                                Once the first sync completes, the sidebar opens up and you can move straight into content generation, store audit, and AI visibility workflows.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                            <div class="font-semibold text-zinc-950">Content workspace</div>
                            <p class="mt-1 text-sm text-zinc-600">Products, collections, topics, blogs, and schedules use synced Shopify content as their baseline.</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                            <div class="font-semibold text-zinc-950">Growth modules</div>
                            <p class="mt-1 text-sm text-zinc-600">Store audit, keyword tracking, and AI visibility become useful once there is real catalog and page data to evaluate.</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <Link href="/stores" class="btn btn-secondary">
                            <ShoppingBag class="size-4" />
                            Open store settings
                        </Link>
                        <Link href="/dashboard" class="btn btn-primary" :class="{ 'pointer-events-none opacity-50': !syncReady }">
                            <ArrowRight class="size-4" />
                            Continue to dashboard
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
