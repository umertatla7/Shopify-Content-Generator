<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BadgeCheck, CheckCircle2, CreditCard, ExternalLink, RefreshCw, ShieldCheck, Store } from 'lucide-vue-next';

const props = defineProps({
    plans: Array,
    currentPlanKey: String,
    currentSubscription: Object,
    primaryStore: Object,
    billingReadiness: Object,
});

const page = usePage();
const isEmbedded = computed(() => Boolean(page.props.shopify?.embedded));
const currentSubscriptionStatus = computed(() => props.currentSubscription?.status ?? 'free');
const currentPlan = computed(() => props.plans.find((plan) => plan.key === props.currentPlanKey) ?? props.plans[0] ?? null);
const canCancel = computed(() => ['active', 'trialing', 'pending'].includes(currentSubscriptionStatus.value) && props.currentPlanKey !== 'free');

const formatPrice = (plan) => {
    if (!plan || Number(plan.monthly_price) <= 0) return 'Free';
    return `$${Number(plan.monthly_price).toFixed(2)}/month`;
};

const limitLabel = (value, unlimitedLabel = 'Unlimited') => {
    if (value === null || value === undefined) return unlimitedLabel;
    if (Number(value) === 0) return 'Not included';
    return Number(value).toLocaleString();
};

const subscribe = (plan) => router.post(`/billing/plans/${plan.id}/subscribe`, {}, { preserveScroll: true });
const syncBilling = () => router.post('/billing/sync', {}, { preserveScroll: true });
const cancelSubscription = () => {
    if (confirm('Cancel the current Shopify subscription and move this account back to Free?')) {
        router.post('/billing/cancel', {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Billing" />
    <AppLayout>
        <template #title>Billing</template>

        <div class="space-y-6">
            <section class="panel overflow-hidden">
                <div class="grid gap-4 p-4 lg:grid-cols-[1.35fr_1fr]">
                    <div class="space-y-3">
                        <div class="flex items-start gap-3">
                            <div class="grid size-11 shrink-0 place-items-center rounded-md bg-teal-50 text-teal-700">
                                <CreditCard class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-zinc-950">Public-app billing foundation</h2>
                                <p class="mt-1 text-sm text-zinc-600">
                                    This account is now wired for Shopify-managed subscription approval, so plan changes can move through Shopify billing instead of manual back-office changes.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Current plan</div>
                                <div class="mt-2 text-base font-bold text-zinc-950">{{ currentPlan?.name ?? 'Free' }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ formatPrice(currentPlan) }}</div>
                            </div>
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Subscription</div>
                                <div class="mt-2 text-base font-bold capitalize text-zinc-950">{{ currentSubscriptionStatus.replace('_', ' ') }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ props.currentSubscription?.is_test ? 'Test mode' : 'Live billing' }}</div>
                            </div>
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Connected store</div>
                                <div class="mt-2 text-base font-bold text-zinc-950">{{ props.primaryStore?.name ?? 'Not connected' }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ props.primaryStore?.shop_domain ?? 'Billing needs a validated store install.' }}</div>
                            </div>
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Launch surface</div>
                                <div class="mt-2 text-base font-bold text-zinc-950">{{ isEmbedded ? 'Embedded admin' : 'External web app' }}</div>
                                <div class="mt-1 text-sm text-zinc-500">We are polishing this toward Shopify App Home.</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-zinc-950">Readiness checks</h3>
                                <p class="mt-1 text-sm text-zinc-500">The pieces we need before App Store submission.</p>
                            </div>
                            <ShieldCheck class="size-5 text-teal-700" />
                        </div>

                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex items-start justify-between gap-3 rounded-md border border-zinc-200 bg-white p-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">Shopify billing flow</div>
                                    <div class="text-zinc-500">Plan approval is routed through Shopify.</div>
                                </div>
                                <span class="badge bg-emerald-100 text-emerald-800">Ready</span>
                            </div>
                            <div class="flex items-start justify-between gap-3 rounded-md border border-zinc-200 bg-white p-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">Connected store</div>
                                    <div class="text-zinc-500">Needed to create and sync live subscriptions.</div>
                                </div>
                                <span class="badge" :class="props.billingReadiness.has_connected_store ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ props.billingReadiness.has_connected_store ? 'Ready' : 'Pending' }}
                                </span>
                            </div>
                            <div class="flex items-start justify-between gap-3 rounded-md border border-zinc-200 bg-white p-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">Public app key</div>
                                    <div class="text-zinc-500">Needed for a fully embedded Shopify App Home experience.</div>
                                </div>
                                <span class="badge" :class="props.billingReadiness.has_public_app_key ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ props.billingReadiness.has_public_app_key ? 'Configured' : 'Pending' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button class="btn btn-secondary" type="button" @click="syncBilling">
                                <RefreshCw class="size-4" />
                                Sync billing
                            </button>
                            <button v-if="canCancel" class="btn btn-secondary" type="button" @click="cancelSubscription">
                                <ExternalLink class="size-4" />
                                Cancel subscription
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="!props.billingReadiness.has_connected_store" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Billing can be configured now, but approving a paid plan needs one validated Shopify store connection first.
            </div>

            <div v-if="props.billingReadiness.manual_connection_mode" class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Manual store credentials are still enabled for development. For the public app path, we’ll replace this with managed Shopify install and OAuth.
            </div>

            <section class="grid gap-4 xl:grid-cols-3">
                <article
                    v-for="plan in props.plans"
                    :key="plan.id"
                    class="panel overflow-hidden"
                    :class="plan.key === props.currentPlanKey ? 'ring-2 ring-teal-200' : ''"
                >
                    <div class="border-b border-zinc-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-lg font-bold text-zinc-950">{{ plan.name }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ formatPrice(plan) }}</div>
                            </div>
                            <span v-if="plan.key === props.currentPlanKey" class="badge bg-teal-100 text-teal-800">
                                <BadgeCheck class="mr-1 size-3.5" />
                                Current
                            </span>
                        </div>
                    </div>

                    <div class="space-y-4 p-4">
                        <div class="grid gap-2 text-sm text-zinc-700">
                            <div class="flex items-center justify-between gap-3">
                                <span>Credits</span>
                                <span class="font-semibold text-zinc-950">{{ limitLabel(plan.monthly_credit_allowance) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Product descriptions</span>
                                <span class="font-semibold text-zinc-950">{{ limitLabel(plan.product_description_limit) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Blogs / month</span>
                                <span class="font-semibold text-zinc-950">{{ limitLabel(plan.monthly_blog_limit) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>SEO reports</span>
                                <span class="font-semibold text-zinc-950">{{ limitLabel(plan.monthly_seo_report_limit) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>AI visibility reports</span>
                                <span class="font-semibold text-zinc-950">{{ limitLabel(plan.monthly_ai_visibility_report_limit) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Tracked keywords</span>
                                <span class="font-semibold text-zinc-950">{{ limitLabel(plan.tracked_keyword_limit) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Image optimization</span>
                                <span class="font-semibold text-zinc-950">{{ limitLabel(plan.monthly_image_optimization_limit) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Image alt text</span>
                                <span class="font-semibold text-zinc-950">{{ limitLabel(plan.monthly_image_alt_text_limit) }}</span>
                            </div>
                        </div>

                        <div class="rounded-md bg-zinc-50 p-3 text-sm text-zinc-600">
                            <div class="flex items-start gap-2">
                                <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-teal-700" />
                                <span>
                                    {{ plan.key === 'free' ? 'Best for install friction reduction and first-value onboarding.' : 'Uses Shopify approval flow so merchants see charges inside Shopify billing.' }}
                                </span>
                            </div>
                        </div>

                        <button
                            class="btn w-full"
                            :class="plan.key === props.currentPlanKey ? 'btn-secondary' : 'btn-primary'"
                            type="button"
                            :disabled="plan.key === props.currentPlanKey"
                            @click="subscribe(plan)"
                        >
                            <Store class="size-4" />
                            {{ plan.key === props.currentPlanKey ? 'Current plan' : (plan.monthly_price > 0 ? 'Choose plan in Shopify' : 'Move to Free') }}
                        </button>
                    </div>
                </article>
            </section>

            <section v-if="props.currentSubscription" class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-950">Local subscription record</h2>
                        <p class="mt-1 text-sm text-zinc-500">This mirrors what we last synced from Shopify for this account.</p>
                    </div>
                </div>

                <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs font-semibold uppercase text-zinc-500">Plan</div>
                        <div class="mt-2 font-semibold text-zinc-950">{{ props.currentSubscription.plan?.name ?? 'Unknown' }}</div>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs font-semibold uppercase text-zinc-500">Status</div>
                        <div class="mt-2 font-semibold capitalize text-zinc-950">{{ props.currentSubscription.status.replace('_', ' ') }}</div>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs font-semibold uppercase text-zinc-500">Billing source</div>
                        <div class="mt-2 font-semibold text-zinc-950">{{ props.currentSubscription.store?.shop_domain ?? props.primaryStore?.shop_domain ?? 'Shopify' }}</div>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs font-semibold uppercase text-zinc-500">Mode</div>
                        <div class="mt-2 font-semibold text-zinc-950">{{ props.currentSubscription.is_test ? 'Test charge' : 'Live charge' }}</div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
