<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BadgeCheck, CheckCircle2, Clock3, CreditCard, ExternalLink, FileBadge2, LifeBuoy, RefreshCw, ScrollText, ShieldCheck, Sparkles, Store } from 'lucide-vue-next';

const props = defineProps({
    plans: Array,
    currentPlanKey: String,
    currentSubscription: Object,
    primaryStore: Object,
    billingReadiness: Object,
    reviewAssets: Object,
});

const page = usePage();
const isEmbedded = computed(() => Boolean(page.props.shopify?.embedded));
const visiblePlans = computed(() => props.plans.filter((plan) => Number(plan.monthly_price) > 0));
const currentSubscriptionStatus = computed(() => props.currentSubscription?.status ?? 'trial_available');
const currentPlan = computed(() => props.plans.find((plan) => plan.key === props.currentPlanKey) ?? props.plans[0] ?? null);
const canCancel = computed(() => ['active', 'trialing', 'pending'].includes(currentSubscriptionStatus.value) && Number(props.currentSubscription?.amount ?? 0) > 0);
const pendingConfirmationUrl = computed(() => props.currentSubscription?.status === 'pending' ? props.currentSubscription?.confirmation_url : null);

const formatPrice = (plan) => {
    if (!plan || Number(plan.monthly_price) <= 0) return 'Trial setup';
    return `$${Number(plan.monthly_price).toFixed(2)}/month`;
};
const trialLabel = (plan) => Number(plan?.trial_days || 0) > 0 ? `${Number(plan.trial_days)}-day trial` : null;

const limitLabel = (value, unlimitedLabel = 'Unlimited') => {
    if (value === null || value === undefined) return unlimitedLabel;
    if (Number(value) === 0) return 'Not included';
    return Number(value).toLocaleString();
};

const formatDate = (value) => {
    if (!value) return 'Not available';

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(value));
};

const subscriptionState = computed(() => {
    const subscription = props.currentSubscription;

    if (!subscription) {
        return {
            tone: 'bg-zinc-50 text-zinc-700 border-zinc-200',
            title: 'No paid subscription on record',
            body: 'This workspace is currently on the free fallback state until a Shopify-approved trial or paid plan is confirmed.',
        };
    }

    if (subscription.status === 'pending') {
        return {
            tone: 'bg-amber-50 text-amber-900 border-amber-200',
            title: 'Waiting for Shopify approval',
            body: 'The subscription request was created, but the merchant still needs to approve it inside Shopify billing.',
        };
    }

    if (subscription.status === 'trialing') {
        return {
            tone: 'bg-teal-50 text-teal-900 border-teal-200',
            title: 'Trial is active',
            body: `The trial is running now${subscription.trial_ends_at ? ` and is expected to end on ${formatDate(subscription.trial_ends_at)}` : ''}.`,
        };
    }

    if (subscription.status === 'active') {
        return {
            tone: 'bg-emerald-50 text-emerald-900 border-emerald-200',
            title: 'Subscription is active',
            body: subscription.current_period_ends_at
                ? `The current billing period is expected to renew on ${formatDate(subscription.current_period_ends_at)}.`
                : 'Shopify shows this subscription as active.',
        };
    }

    return {
        tone: 'bg-zinc-50 text-zinc-700 border-zinc-200',
        title: 'Subscription not active',
        body: 'No active paid subscription is currently attached to this workspace.',
    };
});

const subscribe = (plan) => router.post(`/billing/plans/${plan.id}/subscribe`, {}, { preserveScroll: true });
const syncBilling = () => router.post('/billing/sync', {}, { preserveScroll: true });
const cancelSubscription = () => {
    if (confirm('Cancel the current Shopify subscription?')) {
        router.post('/billing/cancel', {}, { preserveScroll: true });
    }
};
const resumeApproval = () => {
    if (pendingConfirmationUrl.value) {
        window.location.href = pendingConfirmationUrl.value;
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
                            <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-700">
                                <CreditCard class="size-5" />
                            </div>
                            <div>
                                <div class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">
                                    <Sparkles class="size-4" />
                                    Shopify-managed billing
                                </div>
                                <h2 class="mt-3 text-lg font-bold text-zinc-950">Plans, trials, and subscription control</h2>
                                <p class="mt-1 text-sm text-zinc-600">
                                    Merchants should be able to choose a plan, start a trial, upgrade, downgrade, and cancel inside Shopify without needing support.
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

                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
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
                            <div class="flex items-start justify-between gap-3 rounded-md border border-zinc-200 bg-white p-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">Paid plans configured</div>
                                    <div class="text-zinc-500">At least one billable plan should have a real monthly price and Shopify billing handle.</div>
                                </div>
                                <span class="badge" :class="props.billingReadiness.has_paid_plan_config && !props.billingReadiness.misconfigured_paid_plans?.length ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ props.billingReadiness.has_paid_plan_config && !props.billingReadiness.misconfigured_paid_plans?.length ? 'Ready' : 'Pending' }}
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
                Billing can be configured now, but starting a package trial needs one validated Shopify store connection first.
            </div>

            <div v-if="props.billingReadiness.manual_connection_mode" class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Manual store credentials are still enabled for development. For the public app path, we’ll replace this with managed Shopify install and OAuth.
            </div>

            <div v-if="!props.billingReadiness.has_paid_plan_config" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                No paid public plans are configured yet. Add a real monthly price to at least one active plan before submission.
            </div>

            <div v-if="props.billingReadiness.misconfigured_paid_plans?.length" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                These paid plans are missing a Shopify billing handle: {{ props.billingReadiness.misconfigured_paid_plans.join(', ') }}.
            </div>

            <section class="rounded-md border p-4" :class="subscriptionState.tone">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="grid size-10 shrink-0 place-items-center rounded-full bg-white/80">
                            <Clock3 class="size-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-bold">{{ subscriptionState.title }}</h2>
                            <p class="mt-1 text-sm opacity-90">{{ subscriptionState.body }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button v-if="pendingConfirmationUrl" class="btn btn-primary" type="button" @click="resumeApproval">
                            <ExternalLink class="size-4" />
                            Finish approval
                        </button>
                        <button class="btn btn-secondary" type="button" @click="syncBilling">
                            <RefreshCw class="size-4" />
                            Refresh state
                        </button>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                <article class="panel overflow-hidden">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-sm font-bold text-zinc-950">What you need to do in Shopify</h2>
                            <p class="mt-1 text-sm text-zinc-500">This is the merchant billing path we want before App Store submission.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 p-4 md:grid-cols-2">
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="text-xs font-semibold uppercase text-zinc-500">Step 1</div>
                            <div class="mt-2 font-semibold text-zinc-950">Keep Shopify-managed billing</div>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">
                                Charges should always be approved inside Shopify. Our app already uses Shopify subscription approval instead of a custom checkout form.
                            </p>
                        </div>
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="text-xs font-semibold uppercase text-zinc-500">Step 2</div>
                            <div class="mt-2 font-semibold text-zinc-950">Match paid plan handles</div>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">
                                In Admin Plans, every paid tier needs a stable Shopify billing handle so Shopify subscriptions can sync back to the correct local plan.
                            </p>
                        </div>
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="text-xs font-semibold uppercase text-zinc-500">Step 3</div>
                            <div class="mt-2 font-semibold text-zinc-950">Test with development stores</div>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">
                                Validate trial start, upgrade, downgrade, cancellation, uninstall, reinstall, and billing sync while test mode is still enabled.
                            </p>
                        </div>
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="text-xs font-semibold uppercase text-zinc-500">Step 4</div>
                            <div class="mt-2 font-semibold text-zinc-950">Switch production to live billing</div>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">
                                After the flow is stable, set <code>SHOPIFY_BILLING_TEST_MODE=false</code> on live before submission or real merchant launch.
                            </p>
                        </div>
                    </div>
                </article>

                <article class="panel overflow-hidden">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-sm font-bold text-zinc-950">Review assets</h2>
                            <p class="mt-1 text-sm text-zinc-500">These links are useful for Shopify review and merchant trust.</p>
                        </div>
                    </div>

                    <div class="space-y-3 p-4">
                        <a :href="props.reviewAssets.billing_guide_url" target="_blank" class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 p-3 hover:bg-zinc-50">
                            <div class="flex items-center gap-3">
                                <FileBadge2 class="size-4 text-teal-700" />
                                <div>
                                    <div class="font-semibold text-zinc-950">Billing guide</div>
                                    <div class="text-sm text-zinc-500">Internal setup checklist for Shopify billing.</div>
                                </div>
                            </div>
                            <ExternalLink class="size-4 text-zinc-400" />
                        </a>
                        <a :href="props.reviewAssets.review_guide_url" target="_blank" class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 p-3 hover:bg-zinc-50">
                            <div class="flex items-center gap-3">
                                <ScrollText class="size-4 text-teal-700" />
                                <div>
                                    <div class="font-semibold text-zinc-950">Review guide</div>
                                    <div class="text-sm text-zinc-500">Walkthrough for Shopify reviewers and QA.</div>
                                </div>
                            </div>
                            <ExternalLink class="size-4 text-zinc-400" />
                        </a>
                        <a :href="props.reviewAssets.support_url" target="_blank" class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 p-3 hover:bg-zinc-50">
                            <div class="flex items-center gap-3">
                                <LifeBuoy class="size-4 text-teal-700" />
                                <div>
                                    <div class="font-semibold text-zinc-950">Support page</div>
                                    <div class="text-sm text-zinc-500">{{ props.reviewAssets.support_email || 'Set APP_REVIEW_SUPPORT_EMAIL in .env' }}</div>
                                </div>
                            </div>
                            <ExternalLink class="size-4 text-zinc-400" />
                        </a>
                        <a :href="props.reviewAssets.privacy_policy_url" target="_blank" class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 p-3 hover:bg-zinc-50">
                            <div class="flex items-center gap-3">
                                <ShieldCheck class="size-4 text-teal-700" />
                                <div>
                                    <div class="font-semibold text-zinc-950">Privacy policy</div>
                                    <div class="text-sm text-zinc-500">Public merchant-facing policy page.</div>
                                </div>
                            </div>
                            <ExternalLink class="size-4 text-zinc-400" />
                        </a>
                        <a :href="props.reviewAssets.terms_of_service_url" target="_blank" class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 p-3 hover:bg-zinc-50">
                            <div class="flex items-center gap-3">
                                <ScrollText class="size-4 text-teal-700" />
                                <div>
                                    <div class="font-semibold text-zinc-950">Terms of service</div>
                                    <div class="text-sm text-zinc-500">{{ props.reviewAssets.legal_email || 'Set APP_REVIEW_LEGAL_EMAIL in .env' }}</div>
                                </div>
                            </div>
                            <ExternalLink class="size-4 text-zinc-400" />
                        </a>
                    </div>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <article
                    v-for="plan in visiblePlans"
                    :key="plan.id"
                    class="panel overflow-hidden"
                    :class="plan.key === props.currentPlanKey ? 'ring-2 ring-teal-200' : ''"
                >
                    <div class="border-b border-zinc-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-lg font-bold text-zinc-950">{{ plan.name }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ formatPrice(plan) }}</div>
                                <div v-if="trialLabel(plan)" class="mt-2 inline-flex rounded-full bg-teal-50 px-2 py-1 text-xs font-semibold text-teal-800">
                                    {{ trialLabel(plan) }}
                                </div>
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
                                    Uses Shopify approval flow so merchants can start the trial and manage charges inside Shopify billing.
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
                            {{ plan.key === props.currentPlanKey ? 'Current plan' : 'Start trial in Shopify' }}
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
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs font-semibold uppercase text-zinc-500">Trial ends</div>
                        <div class="mt-2 font-semibold text-zinc-950">{{ formatDate(props.currentSubscription.trial_ends_at) }}</div>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs font-semibold uppercase text-zinc-500">Current period end</div>
                        <div class="mt-2 font-semibold text-zinc-950">{{ formatDate(props.currentSubscription.current_period_ends_at) }}</div>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs font-semibold uppercase text-zinc-500">Started from</div>
                        <div class="mt-2 break-all font-semibold text-zinc-950">{{ props.currentSubscription.return_url ?? 'Shopify billing' }}</div>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs font-semibold uppercase text-zinc-500">Approval link</div>
                        <div class="mt-2 font-semibold text-zinc-950">{{ props.currentSubscription.confirmation_url ? 'Available' : 'Not needed' }}</div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
