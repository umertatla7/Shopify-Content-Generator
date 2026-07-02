<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { AlertTriangle, Brain, CheckCircle2, Gauge, ImageOff, MousePointerClick, Move, RefreshCw, Server, Timer, Trash2, X } from 'lucide-vue-next';

const props = defineProps({
    stores: Array,
    storeLimit: Number,
    storeCount: Number,
    canAddStore: Boolean,
    mode: {
        type: String,
        default: 'manage',
    },
});

const page = usePage();
const shopify = computed(() => page.props.shopify ?? {});
const isAuditMode = computed(() => props.mode === 'audit');
const pageTitle = computed(() => isAuditMode.value ? 'Store Audit' : 'Store');
const pageDescription = computed(() => isAuditMode.value
    ? 'Review store audit reports, performance, SEO findings, and content gaps for the connected store.'
    : 'Manage the connected Shopify store, run syncs, and keep catalog data up to date before using the SEO tools.');

const showConnectForm = ref(false);

const syncProgress = (store) => {
    const status = store.latest_sync_log?.status;

    if (status === 'completed') return 100;
    if (status === 'failed') return 100;
    if (status === 'running') return 65;
    if (status === 'pending') return 25;

    return store.last_synced_at ? 100 : 0;
};

const syncLabel = (store) => {
    const log = store.latest_sync_log;

    if (!log) return store.last_synced_at ? 'Synced' : 'Not synced yet';
    if (log.status === 'completed') {
        const counts = log.counts || {};
        return `Completed: ${counts.products ?? 0} products, ${counts.collections ?? 0} collections, ${counts.pages ?? 0} pages, ${counts.existing_blogs ?? 0} blogs`;
    }
    if (log.status === 'failed') return log.error_message || 'Sync failed';
    if (log.status === 'running') return 'Sync running';
    if (log.status === 'pending') return 'Sync pending';

    return log.status;
};

const form = useForm({
    name: '',
    shop_url: '',
    api_key: '',
    client_secret: '',
    admin_api_access_token: '',
    country: '',
    default_language: 'en',
    brand_tone: '',
});

const oauthForm = useForm({
    shop: '',
});

const submit = () => form.post('/stores', {
    preserveScroll: true,
    onSuccess: () => {
        form.reset('name', 'shop_url', 'api_key', 'client_secret', 'admin_api_access_token', 'country', 'brand_tone');
        showConnectForm.value = false;
    },
});

const connectWithShopify = () => oauthForm.get('/shopify/install/start', {
    preserveScroll: true,
});

const sync = (store) => router.post(`/stores/${store.id}/sync`, {}, { preserveScroll: true });
const analyze = (store) => router.post(`/stores/${store.id}/analysis`, {}, { preserveScroll: true });
const remove = (store) => {
    if (confirm(`Remove ${store.name}?`)) {
        router.delete(`/stores/${store.id}`, { preserveScroll: true });
    }
};

const analysisResponse = (store) => store.latest_analysis?.response ?? {};
const listItems = (value) => {
    if (Array.isArray(value)) return value.filter(Boolean);
    if (value && typeof value === 'object') return Object.values(value).filter(Boolean);
    return value ? [value] : [];
};
const reportSection = (store, key) => analysisResponse(store)[key] ?? {};
const reportScore = (store, key) => reportSection(store, key).score ?? '-';
const performanceReport = (store) => analysisResponse(store).performance_report ?? {};
const devicePerformance = (store, device = 'mobile') => {
    const report = performanceReport(store);
    return report?.[device] ?? report ?? {};
};
const speedScore = (store, device = 'mobile') => {
    const deviceReport = devicePerformance(store, device);
    const score = deviceReport.score ?? (device === 'mobile' ? analysisResponse(store).core_web_vitals?.performance_score : null);
    return score === null || score === undefined || score === '' ? null : Number(score);
};
const boundedScore = (score) => Math.max(0, Math.min(100, Number(score ?? 0)));
const scoreColor = (score) => {
    if (score === null || score === undefined) return '#71717a';
    if (Number(score) >= 90) return '#16a34a';
    if (Number(score) >= 50) return '#d97706';
    return '#dc2626';
};
const scoreRingStyle = (score) => {
    const value = boundedScore(score);
    const color = scoreColor(score);
    return { background: `conic-gradient(${color} ${value * 3.6}deg, #e4e4e7 0deg)` };
};
const statusLabel = (status) => ({
    good: 'Good',
    needs_improvement: 'Needs improvement',
    needs_attention: 'Needs improvement',
    needs_lab_test: 'Estimate',
    poor: 'Poor',
    fast: 'Good',
    average: 'Needs improvement',
    slow: 'Poor',
}[String(status || '').toLowerCase()] ?? 'Not measured');
const statusBadgeClass = (status) => {
    const normalized = String(status || '').toLowerCase();
    if (['good', 'fast'].includes(normalized)) return 'bg-emerald-100 text-emerald-800';
    if (['poor', 'slow'].includes(normalized)) return 'bg-rose-100 text-rose-800';
    if (['needs_improvement', 'needs_attention', 'average'].includes(normalized)) return 'bg-amber-100 text-amber-800';
    return 'bg-zinc-100 text-zinc-700';
};
const formatMs = (value) => {
    const number = Number(value);
    if (!Number.isFinite(number)) return 'Not measured';
    if (number >= 1000) return `${(number / 1000).toFixed(2)}s`;
    return `${Math.round(number)}ms`;
};
const formatCls = (value) => {
    const number = Number(value);
    return Number.isFinite(number) ? number.toFixed(3) : 'Not measured';
};
const metricValueLabel = (value, formatter, estimated = false) => {
    const formatted = formatter(value);
    if (formatted === 'Not measured') return 'Not measured';
    return estimated ? `${formatted} est.` : formatted;
};
const speedMetricCards = (store, device = 'mobile') => {
    const performance = devicePerformance(store, device);
    const cwv = performance.core_web_vitals ?? (device === 'mobile' ? analysisResponse(store).core_web_vitals ?? {} : {});
    const metrics = performance.metrics ?? {};
    const statuses = performance.metric_statuses ?? {};
    const homepageMetrics = reportSection(store, 'homepage_report').metrics ?? {};
    const isEstimate = performance.source !== 'pagespeed_insights';

    return [
        {
            label: 'Performance',
            value: speedScore(store, device) === null ? 'Not measured' : speedScore(store, device),
            unit: '/100',
            status: performance.status,
            icon: Gauge,
        },
        {
            label: 'LCP',
            value: metricValueLabel(metrics.largest_contentful_paint_ms, formatMs, isEstimate),
            status: statuses.largest_contentful_paint ?? cwv.lcp_risk,
            icon: Timer,
        },
        {
            label: 'INP / TBT',
            value: metricValueLabel(metrics.interaction_to_next_paint_ms ?? metrics.total_blocking_time_ms, formatMs, isEstimate),
            status: statuses.interaction_to_next_paint ?? statuses.total_blocking_time ?? cwv.inp_risk,
            icon: MousePointerClick,
        },
        {
            label: 'CLS',
            value: metricValueLabel(metrics.cumulative_layout_shift, formatCls, isEstimate),
            status: statuses.cumulative_layout_shift ?? cwv.cls_risk,
            icon: Move,
        },
        {
            label: 'TTFB',
            value: metricValueLabel(metrics.server_response_time_ms ?? homepageMetrics.response_time_ms, formatMs, isEstimate),
            status: statuses.server_response_time ?? cwv.ttfb_risk,
            icon: Server,
        },
        {
            label: 'Images',
            value: metricText(homepageMetrics.images_missing_dimensions ?? analysisResponse(store).technical_audit?.image_without_dimensions_count),
            unit: 'missing size',
            status: (homepageMetrics.images_missing_dimensions ?? 0) > 0 ? 'needs_improvement' : 'good',
            icon: ImageOff,
        },
    ];
};
const reportItems = (store, key, field, fallback = []) => {
    const items = listItems(reportSection(store, key)[field]);

    return items.length ? items : listItems(fallback);
};
const metricText = (value, suffix = '') => (value === null || value === undefined || value === '' ? '-' : `${value}${suffix}`);
const formatBytes = (bytes) => {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value <= 0) return '-';
    if (value >= 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`;

    return `${Math.round(value / 1024)} KB`;
};
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout>
        <template #title>{{ pageTitle }}</template>

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-zinc-950">{{ pageTitle }}</h2>
                    <p class="text-sm text-zinc-500">{{ pageDescription }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="!isAuditMode" class="btn btn-primary" type="button" @click="showConnectForm = !showConnectForm">
                        <span>{{ showConnectForm ? 'Close store form' : 'Connect store' }}</span>
                    </button>
                    <Link v-if="isAuditMode" href="/stores" class="btn btn-secondary">
                        Store center
                    </Link>
                    <Link v-else href="/store-audit" class="btn btn-secondary">
                        Store audit
                    </Link>
                    <Link href="/billing" class="btn btn-secondary">
                        View billing
                    </Link>
                </div>
            </div>

            <section v-if="showConnectForm" class="panel max-w-2xl">
                <div class="panel-header">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-950">Development store connection</h2>
                        <p class="text-sm text-zinc-500">
                            {{ shopify.public_app_api_key ? 'Public app key is configured. This fallback form is only for development and migration work.' : 'Manual credentials are still being used while we move toward managed Shopify install.' }}
                        </p>
                    </div>
                    <button class="btn btn-secondary" type="button" @click="showConnectForm = false">
                        <X class="size-4" />
                        Close
                    </button>
                </div>
                <div class="panel-body space-y-6">
                    <form class="space-y-4 rounded-lg border border-teal-200 bg-teal-50 p-4" @submit.prevent="connectWithShopify">
                        <div class="flex items-start gap-3">
                            <div class="grid size-9 shrink-0 place-items-center rounded-md bg-white text-teal-700 shadow-sm">
                                <CheckCircle2 class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-zinc-950">Connect through Shopify install</h3>
                                <p class="mt-1 text-sm text-zinc-600">Best option for today’s testing. Shopify will grant the token and send us back with the store connected.</p>
                            </div>
                        </div>
                        <div>
                            <label>Shopify store domain</label>
                            <input v-model="oauthForm.shop" placeholder="your-store.myshopify.com" />
                            <p class="mt-1 text-xs text-zinc-500">Use the development store or test store where this app is being installed.</p>
                            <p v-if="oauthForm.errors.shop" class="mt-1 text-xs text-rose-700">{{ oauthForm.errors.shop }}</p>
                        </div>
                        <button class="btn btn-primary w-full" :disabled="oauthForm.processing">
                            Connect with Shopify OAuth
                        </button>
                    </form>

                    <div class="flex items-center gap-3 text-xs font-semibold uppercase tracking-wide text-zinc-400">
                        <span class="h-px flex-1 bg-zinc-200" />
                        Manual fallback
                        <span class="h-px flex-1 bg-zinc-200" />
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label>Store name</label>
                        <input v-model="form.name" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-700">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label>Shopify store URL</label>
                        <input v-model="form.shop_url" placeholder="your-store.myshopify.com" />
                        <p v-if="form.errors.shop_url" class="mt-1 text-xs text-rose-700">{{ form.errors.shop_url }}</p>
                    </div>
                    <div>
                        <label>Client ID</label>
                        <input v-model="form.api_key" placeholder="From Shopify Dev Dashboard" />
                        <p class="mt-1 text-xs text-zinc-500">The portal uses this with the Client Secret to generate and refresh Shopify Admin API tokens.</p>
                        <p v-if="form.errors.api_key" class="mt-1 text-xs text-rose-700">{{ form.errors.api_key }}</p>
                    </div>
                    <div>
                        <label>Client Secret</label>
                        <input v-model="form.client_secret" type="password" placeholder="Starts with shpss_" />
                        <p class="mt-1 text-xs text-zinc-500">Stored encrypted. Do not use the app automation token here.</p>
                        <p v-if="form.errors.client_secret" class="mt-1 text-xs text-rose-700">{{ form.errors.client_secret }}</p>
                    </div>
                    <div>
                        <label>Admin API access token</label>
                        <input v-model="form.admin_api_access_token" type="password" placeholder="Optional: shpat_ or shpua_" />
                        <p class="mt-1 text-xs text-zinc-500">Optional fallback. Leave blank when using Client ID and Client Secret.</p>
                        <p v-if="form.errors.admin_api_access_token" class="mt-1 text-xs text-rose-700">{{ form.errors.admin_api_access_token }}</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label>Region/country</label>
                            <input v-model="form.country" />
                        </div>
                        <div>
                            <label>Default language</label>
                            <input v-model="form.default_language" />
                        </div>
                    </div>
                    <div>
                        <label>Brand tone</label>
                        <input v-model="form.brand_tone" />
                    </div>
                    <button class="btn btn-primary w-full" :disabled="form.processing">Connect store</button>
                    </form>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">{{ isAuditMode ? 'Store audit data' : 'Connected store' }}</h2>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Store</th>
                                <th>Status</th>
                                <th>Products</th>
                                <th>Collections</th>
                                <th>Pages</th>
                                <th>Knowledge</th>
                                <th>Blogs</th>
                                <th>Last sync</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="store in props.stores" :key="store.id">
                            <tr>
                                <td>
                                    <div class="font-semibold text-zinc-950">{{ store.name }}</div>
                                    <div class="text-xs text-zinc-500">{{ store.shop_domain }}</div>
                                    <div v-if="store.validation_error" class="mt-1 max-w-xs whitespace-normal text-xs text-rose-700">{{ store.validation_error }}</div>
                                </td>
                                <td><span class="badge" :class="`badge-${store.status}`">{{ store.status }}</span></td>
                                <td>{{ store.products_count }}</td>
                                <td>{{ store.collections_count }}</td>
                                <td>{{ store.pages_count }}</td>
                                <td>
                                    <span class="badge" :class="`badge-${store.knowledge_base?.status || 'pending'}`">{{ store.knowledge_base?.status || 'pending' }}</span>
                                </td>
                                <td>{{ store.blogs_count }}</td>
                                <td>
                                    <div>
                                        <div class="mb-1 flex items-center justify-between gap-2 text-xs">
                                            <span :class="store.latest_sync_log?.status === 'failed' ? 'text-rose-700' : 'text-zinc-500'">{{ syncLabel(store) }}</span>
                                            <span class="font-semibold text-zinc-700">{{ syncProgress(store) }}%</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="store.latest_sync_log?.status === 'failed' ? 'bg-rose-600' : 'bg-teal-700'"
                                                :style="{ width: `${syncProgress(store)}%` }"
                                            />
                                        </div>
                                        <div class="mt-1 text-xs text-zinc-400">{{ store.last_synced_at ? new Date(store.last_synced_at).toLocaleString() : '-' }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        <button class="btn btn-secondary" title="Sync" @click="sync(store)"><RefreshCw class="size-4" /></button>
                                        <button class="btn btn-secondary" title="Analyze" @click="analyze(store)"><Brain class="size-4" /></button>
                                        <Link class="btn btn-secondary" title="Knowledge base" :href="`/stores/${store.id}/knowledge-base`">Knowledge</Link>
                                        <button class="btn btn-danger" title="Delete" @click="remove(store)"><Trash2 class="size-4" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="isAuditMode && store.latest_analysis">
                                <td colspan="9" class="!whitespace-normal bg-zinc-50">
                                    <div class="space-y-4 rounded-md border border-zinc-200 bg-white p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-sm font-bold text-zinc-950">Store audit report</h3>
                                                    <span class="badge" :class="`badge-${store.latest_analysis.status}`">{{ store.latest_analysis.status }}</span>
                                                </div>
                                                <p class="mt-1 max-w-3xl text-xs text-zinc-500">
                                                    {{ store.latest_analysis.niche || analysisResponse(store).niche || 'Store niche pending' }}
                                                    <span v-if="analysisResponse(store).target_audience"> - {{ analysisResponse(store).target_audience }}</span>
                                                </p>
                                            </div>
                                            <div class="grid grid-cols-4 gap-2 text-center text-xs">
                                                <div class="rounded-md bg-zinc-50 px-3 py-2">
                                                    <div class="font-bold text-zinc-950">{{ reportScore(store, 'homepage_report') }}</div>
                                                    <div class="text-zinc-500">Home</div>
                                                </div>
                                                <div class="rounded-md bg-zinc-50 px-3 py-2">
                                                    <div class="font-bold text-zinc-950">{{ reportScore(store, 'product_page_report') }}</div>
                                                    <div class="text-zinc-500">Products</div>
                                                </div>
                                                <div class="rounded-md bg-zinc-50 px-3 py-2">
                                                    <div class="font-bold text-zinc-950">{{ reportScore(store, 'collection_report') }}</div>
                                                    <div class="text-zinc-500">Collections</div>
                                                </div>
                                                <div class="rounded-md bg-zinc-50 px-3 py-2">
                                                    <div class="font-bold text-zinc-950">{{ reportScore(store, 'blog_report') }}</div>
                                                    <div class="text-zinc-500">Blogs</div>
                                                </div>
                                            </div>
                                        </div>

                                        <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h4 class="text-base font-bold text-zinc-950">Speed and Core Web Vitals</h4>
                                                        <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-700">
                                                            Mobile and desktop
                                                        </span>
                                                        <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-700">
                                                            {{ performanceReport(store).source_label || 'Crawl estimate' }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-2 max-w-4xl text-sm text-zinc-600">
                                                        {{ performanceReport(store).note || 'Mobile and desktop are measured separately. Estimated values are shown when PageSpeed data is unavailable.' }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="grid gap-4 xl:grid-cols-2">
                                                <div
                                                    v-for="device in ['mobile', 'desktop']"
                                                    :key="device"
                                                    class="rounded-lg border border-zinc-200 bg-zinc-50 p-4"
                                                >
                                                    <div class="mb-4 flex flex-wrap items-center gap-4">
                                                        <div class="grid size-28 place-items-center rounded-full p-2 shadow-sm" :style="scoreRingStyle(speedScore(store, device))">
                                                            <div class="grid size-20 place-items-center rounded-full bg-white text-center">
                                                                <div>
                                                                    <div class="text-2xl font-bold" :style="{ color: scoreColor(speedScore(store, device)) }">{{ speedScore(store, device) ?? '-' }}</div>
                                                                    <div class="text-[10px] font-semibold uppercase text-zinc-500">{{ device }}</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <h5 class="text-sm font-bold capitalize text-zinc-950">{{ device }} performance</h5>
                                                                <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusBadgeClass(devicePerformance(store, device).status)">
                                                                    {{ statusLabel(devicePerformance(store, device).status) }}
                                                                </span>
                                                                <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-zinc-700">
                                                                    {{ devicePerformance(store, device).source_label || 'Crawl estimate' }}
                                                                </span>
                                                            </div>
                                                            <p class="mt-1 text-xs text-zinc-500">{{ devicePerformance(store, device).note }}</p>
                                                        </div>
                                                    </div>

                                                    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                                                        <div
                                                            v-for="metric in speedMetricCards(store, device)"
                                                            :key="`${device}-${metric.label}`"
                                                            class="min-w-0 rounded-md border border-zinc-200 bg-white p-3"
                                                        >
                                                            <div class="flex items-center justify-between gap-2">
                                                                <span class="text-xs font-semibold uppercase text-zinc-500">{{ metric.label }}</span>
                                                                <component :is="metric.icon" class="size-4 text-zinc-500" />
                                                            </div>
                                                            <div class="mt-2 flex items-end gap-1">
                                                                <span class="text-lg font-bold text-zinc-950">{{ metric.value }}</span>
                                                                <span v-if="metric.unit" class="pb-1 text-xs text-zinc-500">{{ metric.unit }}</span>
                                                            </div>
                                                            <span class="mt-2 inline-flex rounded-full px-2 py-1 text-[11px] font-semibold" :class="statusBadgeClass(metric.status)">
                                                                {{ statusLabel(metric.status) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                                <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3">
                                                    <div class="mb-2 flex items-center gap-2 text-xs font-bold uppercase text-zinc-500">
                                                        <AlertTriangle class="size-4 text-amber-700" />
                                                        Speed issues found
                                                    </div>
                                                    <ul class="space-y-1 text-sm text-zinc-700">
                                                        <li v-for="item in listItems(analysisResponse(store).speed_issues).slice(0, 5)" :key="item" class="break-words">- {{ item }}</li>
                                                    </ul>
                                                </div>
                                                <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3">
                                                    <div class="mb-2 flex items-center gap-2 text-xs font-bold uppercase text-zinc-500">
                                                        <CheckCircle2 class="size-4 text-emerald-700" />
                                                        Priority actions
                                                    </div>
                                                    <ul class="space-y-1 text-sm text-zinc-700">
                                                        <li v-for="item in listItems(analysisResponse(store).priority_actions).slice(0, 6)" :key="item" class="break-words">- {{ item }}</li>
                                                    </ul>
                                                    <p v-if="store.latest_analysis.error_message" class="mt-2 text-xs text-amber-700">{{ store.latest_analysis.error_message }}</p>
                                                </div>
                                            </div>
                                        </section>

                                        <div class="grid gap-3 xl:grid-cols-2">
                                            <div class="rounded-md border border-zinc-200 p-3">
                                                <h4 class="text-xs font-bold uppercase text-zinc-500">Homepage issues</h4>
                                                <div class="mt-2 grid gap-2 text-xs sm:grid-cols-3">
                                                    <div><span class="text-zinc-500">HTTP</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'homepage_report').status ?? analysisResponse(store).technical_audit?.status) }}</div></div>
                                                    <div><span class="text-zinc-500">Response</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'homepage_report').metrics?.response_time_ms ?? analysisResponse(store).technical_audit?.response_time_ms, 'ms') }}</div></div>
                                                    <div><span class="text-zinc-500">HTML</span><div class="font-semibold text-zinc-950">{{ formatBytes(reportSection(store, 'homepage_report').metrics?.html_bytes ?? analysisResponse(store).technical_audit?.html_bytes) }}</div></div>
                                                    <div><span class="text-zinc-500">H1 count</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'homepage_report').metrics?.h1_count ?? analysisResponse(store).technical_audit?.h1_count) }}</div></div>
                                                    <div><span class="text-zinc-500">Scripts</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'homepage_report').metrics?.script_count ?? analysisResponse(store).technical_audit?.script_count) }}</div></div>
                                                    <div><span class="text-zinc-500">Images no alt</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'homepage_report').metrics?.images_missing_alt ?? analysisResponse(store).technical_audit?.image_without_alt_count) }}</div></div>
                                                </div>
                                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-zinc-500">Found</div>
                                                        <ul class="mt-1 space-y-1 text-xs text-zinc-700">
                                                            <li v-for="item in reportItems(store, 'homepage_report', 'issues', analysisResponse(store).seo_opportunities).slice(0, 5)" :key="item">- {{ item }}</li>
                                                        </ul>
                                                    </div>
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-zinc-500">Recommendations</div>
                                                        <ul class="mt-1 space-y-1 text-xs text-zinc-700">
                                                            <li v-for="item in reportItems(store, 'homepage_report', 'recommendations', analysisResponse(store).seo_opportunities).slice(0, 5)" :key="item">- {{ item }}</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-md border border-zinc-200 p-3">
                                                <h4 class="text-xs font-bold uppercase text-zinc-500">Product page issues</h4>
                                                <div class="mt-2 grid gap-2 text-xs sm:grid-cols-3">
                                                    <div><span class="text-zinc-500">Products</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'product_page_report').total_products ?? analysisResponse(store).product_audit?.total_products) }}</div></div>
                                                    <div><span class="text-zinc-500">Missing copy</span><div class="font-semibold text-zinc-950">{{ metricText(analysisResponse(store).product_audit?.missing_descriptions) }}</div></div>
                                                    <div><span class="text-zinc-500">Missing SEO</span><div class="font-semibold text-zinc-950">{{ metricText(analysisResponse(store).product_audit?.missing_seo_fields) }}</div></div>
                                                </div>
                                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-zinc-500">Found</div>
                                                        <ul class="mt-1 space-y-1 text-xs text-zinc-700">
                                                            <li v-for="item in reportItems(store, 'product_page_report', 'issues', analysisResponse(store).seo_report?.products_missing_descriptions).slice(0, 5)" :key="item">- {{ item }}</li>
                                                        </ul>
                                                    </div>
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-zinc-500">Recommendations</div>
                                                        <ul class="mt-1 space-y-1 text-xs text-zinc-700">
                                                            <li v-for="item in reportItems(store, 'product_page_report', 'recommendations', analysisResponse(store).seo_opportunities).slice(0, 5)" :key="item">- {{ item }}</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div v-if="listItems(reportSection(store, 'product_page_report').sample_products_missing_descriptions).length" class="mt-3 text-xs text-zinc-500">
                                                    Missing descriptions: {{ listItems(reportSection(store, 'product_page_report').sample_products_missing_descriptions).slice(0, 5).join(', ') }}
                                                </div>
                                            </div>

                                            <div class="rounded-md border border-zinc-200 p-3">
                                                <h4 class="text-xs font-bold uppercase text-zinc-500">Collections and content gaps</h4>
                                                <div class="mt-2 grid gap-2 text-xs sm:grid-cols-3">
                                                    <div><span class="text-zinc-500">Collections</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'collection_report').total_collections) }}</div></div>
                                                    <div><span class="text-zinc-500">Missing copy</span><div class="font-semibold text-zinc-950">{{ listItems(reportSection(store, 'collection_report').sample_collections_missing_descriptions).length }}</div></div>
                                                    <div><span class="text-zinc-500">Content gaps</span><div class="font-semibold text-zinc-950">{{ listItems(analysisResponse(store).content_gaps).length }}</div></div>
                                                </div>
                                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-zinc-500">Found</div>
                                                        <ul class="mt-1 space-y-1 text-xs text-zinc-700">
                                                            <li v-for="item in reportItems(store, 'collection_report', 'issues', analysisResponse(store).seo_report?.collections_missing_descriptions).slice(0, 4)" :key="item">- {{ item }}</li>
                                                            <li v-for="item in listItems(analysisResponse(store).content_gaps).slice(0, 3)" :key="item">- {{ item }}</li>
                                                        </ul>
                                                    </div>
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-zinc-500">Recommendations</div>
                                                        <ul class="mt-1 space-y-1 text-xs text-zinc-700">
                                                            <li v-for="item in reportItems(store, 'collection_report', 'recommendations', analysisResponse(store).suggested_blog_categories).slice(0, 5)" :key="item">- {{ item }}</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-md border border-zinc-200 p-3">
                                                <h4 class="text-xs font-bold uppercase text-zinc-500">Blog and AEO report</h4>
                                                <div class="mt-2 grid gap-2 text-xs sm:grid-cols-4">
                                                    <div><span class="text-zinc-500">Shopify blogs</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'blog_report').synced_shopify_articles ?? analysisResponse(store).blog_audit?.synced_shopify_articles) }}</div></div>
                                                    <div><span class="text-zinc-500">Portal blogs</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'blog_report').portal_blogs ?? analysisResponse(store).blog_audit?.portal_blogs) }}</div></div>
                                                    <div><span class="text-zinc-500">Published</span><div class="font-semibold text-zinc-950">{{ metricText(reportSection(store, 'blog_report').published_portal_blogs ?? analysisResponse(store).blog_audit?.published_portal_blogs) }}</div></div>
                                                    <div><span class="text-zinc-500">AEO score</span><div class="font-semibold text-zinc-950">{{ metricText(analysisResponse(store).aeo_report?.score) }}</div></div>
                                                </div>
                                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-zinc-500">Found</div>
                                                        <ul class="mt-1 space-y-1 text-xs text-zinc-700">
                                                            <li v-for="item in reportItems(store, 'blog_report', 'issues', analysisResponse(store).content_gaps).slice(0, 5)" :key="item">- {{ item }}</li>
                                                        </ul>
                                                    </div>
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-zinc-500">Recommendations</div>
                                                        <ul class="mt-1 space-y-1 text-xs text-zinc-700">
                                                            <li v-for="item in reportItems(store, 'blog_report', 'recommendations', analysisResponse(store).suggested_blog_categories).slice(0, 4)" :key="item">- {{ item }}</li>
                                                            <li v-for="item in listItems(analysisResponse(store).aeo_report?.recommendations).slice(0, 3)" :key="item">- {{ item }}</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </td>
                            </tr>
                            </template>
                            <tr v-if="!props.stores.length">
                                <td colspan="9" class="text-zinc-500">No stores connected.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
