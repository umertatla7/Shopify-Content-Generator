<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PlaceholderImage from '@/Components/PlaceholderImage.vue';
import {
    BookOpen,
    Bot,
    CheckCircle2,
    CircleAlert,
    Clock3,
    KeyRound,
    ExternalLink,
    FileQuestion,
    Gauge,
    Layers,
    Lightbulb,
    LoaderCircle,
    RefreshCw,
    Search,
    ShoppingBag,
    Sparkles,
    Target,
    TriangleAlert,
    XCircle,
} from 'lucide-vue-next';

const props = defineProps({
    stores: Array,
    selectedStoreId: Number,
    report: Object,
    technicalSignals: Array,
    brandPresence: Object,
    contentOpportunities: Array,
    reports: Array,
    trendHistory: Array,
    comparison: Object,
    trackedKeywords: Array,
    planUsage: Object,
});

const generating = ref(false);
const savingKeyword = ref(false);
const removingKeywordId = ref(null);
const selectedStoreId = ref(props.selectedStoreId ?? props.stores?.[0]?.id ?? '');
const promptFilters = ref({
    status: '',
    intent: '',
    entity: '',
});
const keywordForm = ref({
    keyword: '',
    shopify_store_id: props.selectedStoreId ?? props.stores?.[0]?.id ?? '',
    target_url: '',
    intent: '',
});

const report = computed(() => props.report);
const promptChecks = computed(() => report.value?.prompt_checks ?? []);
const brandPresence = computed(() => props.brandPresence ?? { score: null, signals: [], summary: null });
const contentOpportunities = computed(() => props.contentOpportunities ?? []);
const trendHistory = computed(() => props.trendHistory ?? []);

const availablePromptIntents = computed(() => [...new Set(promptChecks.value.map((prompt) => prompt.intent).filter(Boolean))]);
const availablePromptEntities = computed(() => [...new Set(promptChecks.value.map((prompt) => prompt.target_entity_type).filter(Boolean))]);
const filteredPromptChecks = computed(() => promptChecks.value.filter((prompt) => {
    if (promptFilters.value.status && prompt.status !== promptFilters.value.status) return false;
    if (promptFilters.value.intent && prompt.intent !== promptFilters.value.intent) return false;
    if (promptFilters.value.entity && prompt.target_entity_type !== promptFilters.value.entity) return false;

    return true;
}));
const weakestPrompts = computed(() => filteredPromptChecks.value.slice(0, 12));
const coveredPrompts = computed(() => filteredPromptChecks.value.filter((prompt) => prompt.status === 'covered').length);
const promptBreakdown = computed(() => ({
    covered: filteredPromptChecks.value.filter((prompt) => prompt.status === 'covered').length,
    partial: filteredPromptChecks.value.filter((prompt) => prompt.status === 'partial').length,
    missing: filteredPromptChecks.value.filter((prompt) => prompt.status === 'missing').length,
}));

const selectedStore = computed(() => props.stores.find((store) => store.id === Number(selectedStoreId.value)) ?? props.stores?.[0]);
const comparison = computed(() => props.comparison);
const trackedKeywords = computed(() => props.trackedKeywords ?? []);
const technicalSignals = computed(() => props.technicalSignals ?? []);
const planMetrics = computed(() => props.planUsage?.metrics ?? {});
const trackedKeywordMetric = computed(() => planMetrics.value.tracked_keywords ?? null);
const visibilityMetric = computed(() => planMetrics.value.ai_visibility_reports ?? null);
const productMetric = computed(() => planMetrics.value.product_descriptions ?? null);
const usageCards = computed(() => [
    ['AI visibility reports', visibilityMetric.value?.used ?? 0, visibilityMetric.value?.limit, Clock3],
    ['Tracked keywords', trackedKeywordMetric.value?.used ?? 0, trackedKeywordMetric.value?.limit, KeyRound],
    ['Product descriptions', productMetric.value?.used ?? 0, productMetric.value?.limit, Layers],
]);
const technicalSignalCounts = computed(() => ({
    critical: technicalSignals.value.filter((signal) => signal.status === 'critical').length,
    watch: technicalSignals.value.filter((signal) => signal.status === 'watch').length,
    healthy: technicalSignals.value.filter((signal) => signal.status === 'healthy').length,
}));
const trendChartWidth = 420;
const trendChartHeight = 160;

const scoreCards = computed(() => {
    if (!report.value) return [];

    return [
        ['AEO score', report.value.aeo_score, FileQuestion, 'Answer Engine Optimization'],
        ['GEO score', report.value.geo_score, Search, 'Generative Engine Optimization'],
        ['LLM readiness', report.value.llm_readiness_score, Bot, 'Knowledge and source clarity'],
        ['Prompt coverage', report.value.prompt_coverage_score, Target, 'AI-question coverage'],
    ];
});

const sourceCards = computed(() => {
    const snapshot = report.value?.source_snapshot ?? {};

    return [
        ['Products', `${snapshot.products?.with_descriptions ?? 0}/${snapshot.products?.total ?? 0}`, 'Useful descriptions', Layers],
        ['Collections', `${snapshot.collections?.with_descriptions ?? 0}/${snapshot.collections?.total ?? 0}`, 'AEO category copy', BookOpen],
        ['Blogs', snapshot.blogs?.published_portal ?? 0, 'Published portal blogs', Lightbulb],
        ['Answer pages', snapshot.pages?.answer_pages ?? 0, 'FAQ, policy, trust pages', CheckCircle2],
    ];
});
const platformDefinitions = [
    { key: 'chatgpt', name: 'ChatGPT', imageName: 'chatgpt.png', imageSrc: '/images/platforms/chatgpt.png', iconClass: 'border-emerald-200 bg-emerald-50 text-emerald-700', fallbackLabel: 'CG' },
    { key: 'claude', name: 'Claude', imageName: 'claude.png', imageSrc: '/images/platforms/claude.png', iconClass: 'border-amber-200 bg-amber-50 text-amber-700', fallbackLabel: 'CL' },
    { key: 'gemini', name: 'Gemini', imageName: 'gemini.png', imageSrc: '/images/platforms/gemini.png', iconClass: 'border-violet-200 bg-violet-50 text-violet-700', fallbackLabel: 'GM' },
    { key: 'perplexity', name: 'Perplexity', imageName: 'perplexity.png', imageSrc: '/images/platforms/perplexity.png', iconClass: 'border-sky-200 bg-sky-50 text-sky-700', fallbackLabel: 'PX' },
];
const platformReadiness = computed(() => {
    if (!report.value) return [];

    const snapshot = report.value.source_snapshot ?? {};
    const products = snapshot.products ?? {};
    const collections = snapshot.collections ?? {};
    const pages = snapshot.pages ?? {};
    const blogs = snapshot.blogs ?? {};

    const productCoverage = (products.with_descriptions ?? 0) >= Math.max(3, Math.ceil((products.total ?? 0) * 0.4));
    const collectionCoverage = (collections.with_descriptions ?? 0) >= Math.max(1, Math.ceil((collections.total ?? 0) * 0.35));
    const answerPagesReady = (pages.answer_pages ?? 0) >= 2;
    const blogCoverage = (blogs.with_faqs ?? 0) >= 1 || (blogs.published_portal ?? 0) >= 2;
    const promptCoverageReady = (report.value.prompt_coverage_score ?? 0) >= 60;
    const brandReady = (brandPresence.value.score ?? 0) >= 60;
    const technicalReady = (report.value.schema_readiness_score ?? 0) >= 60;
    const sourceDepthReady = (report.value.content_depth_score ?? 0) >= 60;

    return [
        {
            key: 'chatgpt',
            name: 'ChatGPT',
            ready: promptCoverageReady && answerPagesReady && brandReady,
            checks: [
                { label: 'Answer-page coverage', passed: answerPagesReady },
                { label: 'Brand clarity', passed: brandReady },
                { label: 'Prompt coverage', passed: promptCoverageReady },
            ],
        },
        {
            key: 'gemini',
            name: 'Gemini',
            ready: technicalReady && sourceDepthReady && collectionCoverage,
            checks: [
                { label: 'Technical readiness', passed: technicalReady },
                { label: 'Collection source depth', passed: collectionCoverage },
                { label: 'Content depth', passed: sourceDepthReady },
            ],
        },
        {
            key: 'perplexity',
            name: 'Perplexity',
            ready: blogCoverage && answerPagesReady && promptCoverageReady,
            checks: [
                { label: 'Citable blog content', passed: blogCoverage },
                { label: 'Policy / trust pages', passed: answerPagesReady },
                { label: 'Prompt coverage', passed: promptCoverageReady },
            ],
        },
        {
            key: 'claude',
            name: 'Claude',
            ready: brandReady && productCoverage && sourceDepthReady,
            checks: [
                { label: 'Brand trust signals', passed: brandReady },
                { label: 'Product detail coverage', passed: productCoverage },
                { label: 'Source depth', passed: sourceDepthReady },
            ],
        },
    ].map((platform) => ({
        ...platform,
        ...platformDefinitions.find((item) => item.key === platform.key),
    }));
});

const scoreLabel = (score) => {
    if (score >= 75) return 'Strong';
    if (score >= 50) return 'Developing';
    return 'Needs work';
};

const scoreTextClass = (score) => {
    if (score >= 75) return 'text-emerald-700';
    if (score >= 50) return 'text-amber-700';
    return 'text-rose-700';
};

const scorePillClass = (score) => {
    if (score >= 75) return 'bg-emerald-100 text-emerald-800';
    if (score >= 50) return 'bg-amber-100 text-amber-800';
    return 'bg-rose-100 text-rose-800';
};

const promptStatusClass = (status) => ({
    covered: 'bg-emerald-100 text-emerald-800',
    partial: 'bg-amber-100 text-amber-800',
    missing: 'bg-rose-100 text-rose-800',
}[status] ?? 'bg-zinc-100 text-zinc-700');
const technicalStatusClass = (status) => ({
    healthy: 'bg-emerald-100 text-emerald-800',
    watch: 'bg-amber-100 text-amber-800',
    critical: 'bg-rose-100 text-rose-800',
}[status] ?? 'bg-zinc-100 text-zinc-700');
const priorityClass = (priority) => ({
    high: 'bg-rose-100 text-rose-800',
    medium: 'bg-amber-100 text-amber-800',
    low: 'bg-emerald-100 text-emerald-800',
}[priority] ?? 'bg-zinc-100 text-zinc-700');

const scoreRingStyle = (score) => {
    const color = score >= 75 ? '#059669' : score >= 50 ? '#d97706' : '#e11d48';

    return {
        background: `conic-gradient(${color} ${score * 3.6}deg, #e5e7eb 0deg)`,
    };
};

const changeStore = () => {
    router.get('/ai-visibility', { store_id: selectedStoreId.value }, { preserveState: true, preserveScroll: true });
};

const generateReport = () => {
    if (!selectedStoreId.value) return;

    generating.value = true;
    router.post('/ai-visibility/reports', {
        shopify_store_id: selectedStoreId.value,
    }, {
        preserveScroll: true,
        onFinish: () => generating.value = false,
    });
};

const saveKeyword = () => {
    savingKeyword.value = true;
    router.post('/tracked-keywords', {
        keyword: keywordForm.value.keyword,
        shopify_store_id: keywordForm.value.shopify_store_id || null,
        target_url: keywordForm.value.target_url || null,
        intent: keywordForm.value.intent || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            keywordForm.value.keyword = '';
            keywordForm.value.target_url = '';
            keywordForm.value.intent = '';
        },
        onFinish: () => savingKeyword.value = false,
    });
};

const removeKeyword = (keyword) => {
    removingKeywordId.value = keyword.id;
    router.delete(`/tracked-keywords/${keyword.id}`, {
        preserveScroll: true,
        onFinish: () => removingKeywordId.value = null,
    });
};

const formatDate = (date) => date ? new Date(date).toLocaleString() : 'Not generated';
const evidenceValue = (value) => Array.isArray(value) ? value.join(', ') : value;
const deltaClass = (value) => value > 0 ? 'text-emerald-700' : value < 0 ? 'text-rose-700' : 'text-zinc-500';
const deltaPrefix = (value) => value > 0 ? '+' : '';
const positionLabel = (snapshot) => snapshot?.position ? `Avg ${snapshot.position}` : 'No rank yet';
const entityTypeLabel = (value) => {
    if (!value) return 'Any entity';
    if (value === 'product_type') return 'Product type';
    if (value === 'shopify_page') return 'Store page';
    if (value.endsWith('ShopifyCollection')) return 'Collection';
    if (value.endsWith('ShopifyStore')) return 'Store';
    if (value.endsWith('Blog')) return 'Blog';

    return value.split('\\').pop();
};
const promptNumber = (prompt, keys) => {
    for (const key of keys) {
        const value = Number(prompt?.evidence?.[key]);

        if (Number.isFinite(value) && value > 0) {
            return value;
        }
    }

    return 0;
};
const promptHasSource = (prompt) => Boolean(prompt?.recommended_source_url || prompt?.evidence?.source_url);
const promptPlatformPasses = (prompt, platformKey) => {
    const score = Number(prompt?.score ?? 0);
    const status = String(prompt?.status ?? '').toLowerCase();
    const intent = String(prompt?.intent ?? '').toLowerCase();
    const evidence = prompt?.evidence ?? {};
    const hasSource = promptHasSource(prompt);
    const descriptionWords = promptNumber(prompt, ['description_words']);
    const pageWords = promptNumber(prompt, ['page_words']);
    const bodyWords = promptNumber(prompt, ['body_words']);
    const brandSummaryWords = promptNumber(prompt, ['brand_summary_words']);
    const brandProfileWords = promptNumber(prompt, ['brand_profile_words', 'audience_profile_words']);
    const relatedBlogs = promptNumber(prompt, ['related_blogs']);
    const linkCount = promptNumber(prompt, ['link_count']);
    const faqCount = promptNumber(prompt, ['faq_count']);
    const answerIntentPage = Boolean(evidence.answer_intent_page);
    const hasFaq = Boolean(evidence.has_faq) || faqCount > 0;
    const contentDepth = Math.max(descriptionWords, pageWords, bodyWords, brandSummaryWords, brandProfileWords);
    const intentMatchesBrand = ['brand_overview', 'brand_differentiation', 'brand_policy_clarity', 'brand_audience_fit', 'brand_trust'].includes(intent);
    const intentMatchesBuying = ['buying_guide', 'product_education', 'commercial_answer'].includes(intent);

    if (status === 'missing' || score < 45) {
        return false;
    }

    return {
        chatgpt: score >= 60 && (intentMatchesBrand || intentMatchesBuying || answerIntentPage) && (hasSource || contentDepth >= 80),
        gemini: score >= 60 && contentDepth >= 80 && (hasSource || descriptionWords >= 100 || pageWords >= 140),
        perplexity: score >= 60 && hasSource && (hasFaq || linkCount > 0 || relatedBlogs > 0 || answerIntentPage),
        claude: score >= 60 && (contentDepth >= 80 || intentMatchesBrand) && status !== 'missing',
    }[platformKey] ?? false;
};
const trendPoints = (key) => {
    if (!trendHistory.value.length) return '';

    return trendHistory.value.map((point, index) => {
        const x = trendHistory.value.length === 1
            ? trendChartWidth / 2
            : (index / (trendHistory.value.length - 1)) * trendChartWidth;
        const y = trendChartHeight - (((point[key] ?? 0) / 100) * trendChartHeight);

        return `${x},${y}`;
    }).join(' ');
};
</script>

<template>
    <Head title="AI Visibility" />
    <AppLayout>
        <template #title>AI Visibility</template>

        <div class="space-y-5">
            <section class="panel overflow-hidden">
                <div class="panel-body grid gap-5 xl:grid-cols-[1.3fr_.7fr]">
                    <div>
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">
                            <Bot class="size-4" />
                            Phase 3 · AEO / GEO visibility
                        </div>
                        <h2 class="text-xl font-bold text-zinc-950">AI-search readiness for answer engines and generative results</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600">
                            The report scores how well synced store content can answer buyer questions across Google-style answers, AI overviews, and generative search surfaces.
                        </p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                            <div>
                                <label>Store</label>
                                <select v-model="selectedStoreId" class="mt-1" @change="changeStore">
                                    <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="button" class="btn btn-primary w-full sm:w-auto" :disabled="generating || !selectedStoreId" @click="generateReport">
                                    <LoaderCircle v-if="generating" class="size-4 animate-spin" />
                                    <RefreshCw v-else class="size-4" />
                                    Generate report
                                </button>
                            </div>
                        </div>
                        <p v-if="selectedStore" class="mt-3 text-xs text-zinc-500">
                            {{ selectedStore.products_count }} products · {{ selectedStore.collections_count }} collections · {{ selectedStore.pages_count }} pages · {{ selectedStore.blogs_count }} blogs
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <div v-for="[label, used, limit, Icon] in usageCards" :key="label" class="panel p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ label }}</p>
                            <p class="mt-2 text-2xl font-bold text-zinc-950">{{ used }}<span class="text-base font-semibold text-zinc-400">/{{ limit ?? 'Unlimited' }}</span></p>
                        </div>
                        <div class="grid size-10 place-items-center rounded-md bg-zinc-100 text-zinc-700">
                            <component :is="Icon" class="size-5" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500">{{ props.planUsage?.plan?.name || 'Current' }} plan</p>
                </div>
            </section>

            <section v-if="!props.stores.length" class="panel p-8 text-center">
                <ShoppingBag class="mx-auto size-8 text-zinc-400" />
                <h2 class="mt-3 text-base font-bold text-zinc-950">No store connected</h2>
                <p class="mt-1 text-sm text-zinc-500">Connect and sync a Shopify store before running AI visibility reports.</p>
            </section>

            <section v-else-if="!report" class="panel p-8 text-center">
                <Sparkles class="mx-auto size-8 text-teal-700" />
                <h2 class="mt-3 text-base font-bold text-zinc-950">No visibility report yet</h2>
                <p class="mt-1 text-sm text-zinc-500">Generate the first AEO/GEO report for the selected store.</p>
            </section>

            <template v-else>
                <section class="panel overflow-hidden">
                    <div class="grid gap-6 p-5 xl:grid-cols-[340px_1fr]">
                        <div class="flex items-center gap-5">
                            <div class="grid size-36 shrink-0 place-items-center rounded-full p-3" :style="scoreRingStyle(report.overall_score)">
                                <div class="grid size-full place-items-center rounded-full bg-white">
                                    <div class="text-center">
                                        <div class="text-4xl font-bold text-zinc-950">{{ report.overall_score }}</div>
                                        <div class="text-xs font-semibold uppercase text-zinc-500">Overall</div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="scorePillClass(report.overall_score)">{{ scoreLabel(report.overall_score) }}</span>
                                <h2 class="mt-3 text-lg font-bold text-zinc-950">{{ report.store?.name }}</h2>
                                <p class="mt-1 text-sm leading-6 text-zinc-600">{{ report.summary }}</p>
                                <p class="mt-2 text-xs text-zinc-500">Generated {{ formatDate(report.created_at) }}</p>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div v-for="[label, score, Icon, description] in scoreCards" :key="label" class="rounded-lg border border-zinc-200 bg-white p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ label }}</p>
                                        <p class="mt-2 text-3xl font-bold" :class="scoreTextClass(score)">{{ score }}</p>
                                    </div>
                                    <div class="grid size-9 place-items-center rounded-md bg-zinc-100 text-zinc-700">
                                        <component :is="Icon" class="size-5" />
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-zinc-500">{{ description }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="comparison" class="grid gap-4 md:grid-cols-4">
                    <div class="panel p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Overall change</p>
                        <p class="mt-2 text-2xl font-bold" :class="deltaClass(comparison.overall_score_delta)">{{ deltaPrefix(comparison.overall_score_delta) }}{{ comparison.overall_score_delta }}</p>
                    </div>
                    <div class="panel p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">AEO change</p>
                        <p class="mt-2 text-2xl font-bold" :class="deltaClass(comparison.aeo_score_delta)">{{ deltaPrefix(comparison.aeo_score_delta) }}{{ comparison.aeo_score_delta }}</p>
                    </div>
                    <div class="panel p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">GEO change</p>
                        <p class="mt-2 text-2xl font-bold" :class="deltaClass(comparison.geo_score_delta)">{{ deltaPrefix(comparison.geo_score_delta) }}{{ comparison.geo_score_delta }}</p>
                    </div>
                    <div class="panel p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Prompt coverage</p>
                        <p class="mt-2 text-2xl font-bold" :class="deltaClass(comparison.prompt_coverage_delta)">{{ deltaPrefix(comparison.prompt_coverage_delta) }}{{ comparison.prompt_coverage_delta }}</p>
                    </div>
                </section>

                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div v-for="[label, value, description, Icon] in sourceCards" :key="label" class="panel p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ label }}</p>
                                <p class="mt-2 text-2xl font-bold text-zinc-950">{{ value }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ description }}</p>
                            </div>
                            <div class="grid size-10 place-items-center rounded-md bg-teal-50 text-teal-800">
                                <component :is="Icon" class="size-5" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-sm font-bold text-zinc-950">Platform readiness</h2>
                            <p class="text-xs text-zinc-500">A quick pass on whether current store content meets the baseline signals these AI platforms tend to rely on.</p>
                        </div>
                    </div>
                    <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-4">
                        <article v-for="platform in platformReadiness" :key="platform.name" class="rounded-lg border border-zinc-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <PlaceholderImage
                                        :src="platform.imageSrc"
                                        :alt="platform.imageName"
                                        :fallback-label="platform.fallbackLabel"
                                        :wrapper-class="`grid h-10 w-10 place-items-center rounded-xl border ${platform.iconClass}`"
                                        image-class="h-5 w-5 object-contain"
                                    />
                                    <h3 class="text-sm font-bold text-zinc-950">{{ platform.name }}</h3>
                                </div>
                                <span class="rounded-full px-2 py-1 text-xs font-bold" :class="platform.ready ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">
                                    {{ platform.ready ? 'Ready' : 'Needs work' }}
                                </span>
                            </div>
                            <div class="mt-4 space-y-2">
                                <div v-for="check in platform.checks" :key="check.label" class="flex items-center gap-2 text-sm">
                                    <CheckCircle2 v-if="check.passed" class="size-4 shrink-0 text-emerald-600" />
                                    <XCircle v-else class="size-4 shrink-0 text-rose-600" />
                                    <span class="text-zinc-700">{{ check.label }}</span>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-sm font-bold text-zinc-950">Prompt evidence</h2>
                            <p class="text-xs text-zinc-500">Filter prompt gaps by status, intent, and entity type, then review platform-by-platform fit for each prompt.</p>
                        </div>
                    </div>
                    <div class="panel-body border-b border-zinc-200">
                        <div class="grid gap-3 md:grid-cols-3">
                            <div>
                                <label>Status</label>
                                <select v-model="promptFilters.status">
                                    <option value="">All statuses</option>
                                    <option value="missing">Missing</option>
                                    <option value="partial">Partial</option>
                                    <option value="covered">Covered</option>
                                </select>
                            </div>
                            <div>
                                <label>Intent</label>
                                <select v-model="promptFilters.intent">
                                    <option value="">All intents</option>
                                    <option v-for="intent in availablePromptIntents" :key="intent" :value="intent">{{ intent.replaceAll('_', ' ') }}</option>
                                </select>
                            </div>
                            <div>
                                <label>Entity type</label>
                                <select v-model="promptFilters.entity">
                                    <option value="">All entity types</option>
                                    <option v-for="entity in availablePromptEntities" :key="entity" :value="entity">{{ entityTypeLabel(entity) }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Prompt</th>
                                    <th v-for="platform in platformDefinitions" :key="platform.key" class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <PlaceholderImage
                                                :src="platform.imageSrc"
                                                :alt="platform.imageName"
                                                :fallback-label="platform.fallbackLabel"
                                                :wrapper-class="`grid h-7 w-7 place-items-center rounded-lg border ${platform.iconClass}`"
                                                image-class="h-4 w-4 object-contain"
                                            />
                                            <span>{{ platform.name }}</span>
                                        </div>
                                    </th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Evidence</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <tr v-for="prompt in filteredPromptChecks" :key="prompt.id">
                                    <td class="max-w-md px-4 py-3">
                                        <p class="font-semibold text-zinc-950">{{ prompt.prompt }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ prompt.intent?.replaceAll('_', ' ') }}</p>
                                        <p class="mt-1 text-xs text-zinc-400">{{ prompt.target_entity_label || '-' }}</p>
                                    </td>
                                    <td v-for="platform in platformDefinitions" :key="`${prompt.id}-${platform.key}`" class="px-4 py-3 text-center">
                                        <div class="flex justify-center">
                                            <CheckCircle2 v-if="promptPlatformPasses(prompt, platform.key)" class="size-5 text-emerald-600" />
                                            <XCircle v-else class="size-5 text-rose-600" />
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-bold capitalize" :class="promptStatusClass(prompt.status)">{{ prompt.status }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-bold">{{ prompt.score }}</td>
                                    <td class="max-w-sm px-4 py-3 text-xs text-zinc-500 text-wrap-anywhere">
                                        <span v-for="(value, key) in (prompt.evidence ?? {})" :key="key" class="mr-2 inline-block">
                                            {{ key }}: {{ evidenceValue(value) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!filteredPromptChecks.length">
                                    <td :colspan="platformDefinitions.length + 4" class="px-4 py-8 text-center text-zinc-500">No prompts match the current filters.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-sm font-bold text-zinc-950">Technical signals</h2>
                            <p class="text-xs text-zinc-500">These are the site and content signals most likely to affect AI visibility for a Shopify merchant.</p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold">
                            <span class="rounded-full bg-rose-100 px-2 py-1 text-rose-800">{{ technicalSignalCounts.critical }} critical</span>
                            <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-800">{{ technicalSignalCounts.watch }} watch</span>
                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-800">{{ technicalSignalCounts.healthy }} healthy</span>
                        </div>
                    </div>
                    <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="signal in technicalSignals" :key="signal.label" class="rounded-lg border border-zinc-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-zinc-950">{{ signal.label }}</h3>
                                    <p class="mt-2 text-2xl font-bold" :class="scoreTextClass(signal.score)">{{ signal.score }}</p>
                                </div>
                                <span class="rounded-full px-2 py-1 text-xs font-bold capitalize" :class="technicalStatusClass(signal.status)">
                                    {{ signal.status }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm font-semibold text-zinc-800">{{ signal.summary }}</p>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">{{ signal.detail }}</p>
                            <p class="mt-3 text-sm leading-6 text-zinc-700">{{ signal.action }}</p>
                        </article>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-sm font-bold text-zinc-950">Tracked keywords for AI visibility</h2>
                            <p class="text-xs text-zinc-500">Add the commercial and answer-style keywords you want to monitor across content work and Search Console syncs.</p>
                        </div>
                    </div>
                    <div class="panel-body border-b border-zinc-200">
                        <div class="grid gap-3 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto]">
                            <div>
                                <label>Keyword</label>
                                <input v-model="keywordForm.keyword" placeholder="best moonstone rings" />
                            </div>
                            <div>
                                <label>Store</label>
                                <select v-model="keywordForm.shopify_store_id">
                                    <option value="">All stores</option>
                                    <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label>Intent</label>
                                <input v-model="keywordForm.intent" placeholder="commercial, faq, buying guide" />
                            </div>
                            <div>
                                <label>Target URL</label>
                                <input v-model="keywordForm.target_url" placeholder="https://..." />
                            </div>
                            <div class="flex items-end">
                                <button type="button" class="btn btn-primary w-full" :disabled="savingKeyword || !keywordForm.keyword" @click="saveKeyword">
                                    <LoaderCircle v-if="savingKeyword" class="size-4 animate-spin" />
                                    <KeyRound v-else class="size-4" />
                                    Add
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="divide-y divide-zinc-100">
                        <div v-for="keyword in trackedKeywords" :key="keyword.id" class="grid gap-3 p-4 lg:grid-cols-[1.4fr_.8fr_.8fr_.8fr_auto]">
                            <div>
                                <p class="text-sm font-semibold text-zinc-950">{{ keyword.keyword }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ keyword.intent || 'No intent set' }}</p>
                                <a v-if="keyword.target_url" :href="keyword.target_url" target="_blank" rel="noreferrer" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-teal-700 hover:text-teal-900">
                                    Target URL
                                    <ExternalLink class="size-3" />
                                </a>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Latest position</p>
                                <p class="mt-1 text-sm font-semibold text-zinc-950">{{ positionLabel(keyword.latest_snapshot) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Impressions</p>
                                <p class="mt-1 text-sm font-semibold text-zinc-950">{{ keyword.latest_snapshot?.impressions ?? 0 }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Last checked</p>
                                <p class="mt-1 text-sm text-zinc-600">{{ keyword.latest_snapshot?.date ? formatDate(keyword.latest_snapshot.date) : 'Not synced' }}</p>
                            </div>
                            <div class="flex items-start justify-end">
                                <button type="button" class="btn btn-secondary" :disabled="removingKeywordId === keyword.id" @click="removeKeyword(keyword)">
                                    <LoaderCircle v-if="removingKeywordId === keyword.id" class="size-4 animate-spin" />
                                    <CircleAlert v-else class="size-4" />
                                    Remove
                                </button>
                            </div>
                        </div>
                        <div v-if="!trackedKeywords.length" class="p-4 text-sm text-zinc-500">No tracked keywords added yet.</div>
                    </div>
                </section>

                <section class="grid gap-5 xl:grid-cols-[.95fr_1.05fr]">
                    <section class="panel">
                        <div class="panel-header">
                            <div>
                                <h2 class="text-sm font-bold text-zinc-950">Brand presence</h2>
                                <p class="text-xs text-zinc-500">{{ brandPresence.summary }}</p>
                            </div>
                            <div v-if="brandPresence.score !== null" class="text-right">
                                <p class="text-2xl font-bold" :class="scoreTextClass(brandPresence.score)">{{ brandPresence.score }}</p>
                                <p class="text-xs text-zinc-500">Branded prompt score</p>
                            </div>
                        </div>
                        <div class="grid gap-3 p-4">
                            <article v-for="signal in brandPresence.signals" :key="signal.id" class="rounded-lg border border-zinc-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-zinc-950">{{ signal.prompt }}</p>
                                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ signal.intent?.replaceAll('_', ' ') }}</p>
                                    </div>
                                    <span class="rounded-full px-2 py-1 text-xs font-bold capitalize" :class="promptStatusClass(signal.status)">{{ signal.status }}</span>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-zinc-600">{{ signal.recommendation }}</p>
                                <a v-if="signal.source_url" :href="signal.source_url" target="_blank" rel="noreferrer" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-teal-700 hover:text-teal-900">
                                    Supporting source
                                    <ExternalLink class="size-3" />
                                </a>
                            </article>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <div>
                                <h2 class="text-sm font-bold text-zinc-950">Content opportunities</h2>
                                <p class="text-xs text-zinc-500">Actionable next steps generated from weak prompts and technical gaps.</p>
                            </div>
                        </div>
                        <div class="grid gap-3 p-4">
                            <article v-for="item in contentOpportunities" :key="`${item.source}-${item.title}`" class="rounded-lg border border-zinc-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-bold text-zinc-950">{{ item.title }}</h3>
                                        <p class="mt-1 text-xs text-zinc-500">{{ item.target_label || 'General opportunity' }}</p>
                                    </div>
                                    <span class="rounded-full px-2 py-1 text-xs font-bold capitalize" :class="priorityClass(item.priority)">{{ item.priority }}</span>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-zinc-600">{{ item.detail }}</p>
                                <a v-if="item.source_url" :href="item.source_url" target="_blank" rel="noreferrer" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-teal-700 hover:text-teal-900">
                                    Review source
                                    <ExternalLink class="size-3" />
                                </a>
                            </article>
                            <div v-if="!contentOpportunities.length" class="text-sm text-zinc-500">No content opportunities generated yet.</div>
                        </div>
                    </section>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-sm font-bold text-zinc-950">Trend history</h2>
                            <p class="text-xs text-zinc-500">Track whether overall AI visibility and prompt coverage are moving in the right direction.</p>
                        </div>
                    </div>
                    <div class="grid gap-5 p-4 xl:grid-cols-[1.2fr_.8fr]">
                        <div class="rounded-lg border border-zinc-200 p-4">
                            <svg :viewBox="`0 0 ${trendChartWidth} ${trendChartHeight}`" class="h-48 w-full overflow-visible">
                                <line
                                    v-for="line in [0, 25, 50, 75, 100]"
                                    :key="line"
                                    x1="0"
                                    :y1="trendChartHeight - ((line / 100) * trendChartHeight)"
                                    :x2="trendChartWidth"
                                    :y2="trendChartHeight - ((line / 100) * trendChartHeight)"
                                    stroke="#e4e4e7"
                                    stroke-width="1"
                                />
                                <polyline
                                    :points="trendPoints('overall_score')"
                                    fill="none"
                                    stroke="#0f766e"
                                    stroke-width="4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <polyline
                                    :points="trendPoints('prompt_coverage_score')"
                                    fill="none"
                                    stroke="#2563eb"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-dasharray="8 8"
                                />
                            </svg>
                            <div class="mt-3 flex flex-wrap gap-4 text-xs font-semibold">
                                <span class="inline-flex items-center gap-2 text-zinc-700"><span class="inline-block h-1.5 w-8 rounded-full bg-teal-700" /> Overall</span>
                                <span class="inline-flex items-center gap-2 text-zinc-700"><span class="inline-block h-1.5 w-8 rounded-full bg-blue-600" /> Prompt coverage</span>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div v-for="point in trendHistory" :key="point.id" class="rounded-lg border border-zinc-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-zinc-950">{{ point.label }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ formatDate(point.created_at) }}</p>
                                    </div>
                                    <p class="text-lg font-bold" :class="scoreTextClass(point.overall_score)">{{ point.overall_score }}</p>
                                </div>
                                <p class="mt-3 text-sm text-zinc-600">AEO {{ point.aeo_score }} · GEO {{ point.geo_score }} · Prompt coverage {{ point.prompt_coverage_score }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
                    <div class="panel">
                        <div class="panel-header">
                            <div>
                                <h2 class="text-sm font-bold text-zinc-950">AI prompt coverage</h2>
                                <p class="text-xs text-zinc-500">{{ coveredPrompts }} covered from {{ filteredPromptChecks.length }} filtered prompts</p>
                            </div>
                            <div class="flex gap-2 text-xs font-semibold">
                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-800">{{ promptBreakdown.covered }} covered</span>
                                <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-800">{{ promptBreakdown.partial }} partial</span>
                                <span class="rounded-full bg-rose-100 px-2 py-1 text-rose-800">{{ promptBreakdown.missing }} missing</span>
                            </div>
                        </div>
                        <div class="grid gap-3 p-4 lg:grid-cols-2">
                            <article v-for="prompt in weakestPrompts" :key="prompt.id" class="rounded-lg border border-zinc-200 p-4">
                                <div class="mb-3 flex items-start justify-between gap-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-bold capitalize" :class="promptStatusClass(prompt.status)">{{ prompt.status }}</span>
                                    <span class="text-sm font-bold text-zinc-950">{{ prompt.score }}/100</span>
                                </div>
                                <h3 class="text-sm font-bold leading-6 text-zinc-950">{{ prompt.prompt }}</h3>
                                <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ prompt.intent?.replaceAll('_', ' ') }}</p>
                                <p class="mt-3 text-sm leading-6 text-zinc-600">{{ prompt.recommendation }}</p>
                                <a v-if="prompt.recommended_source_url" :href="prompt.recommended_source_url" target="_blank" rel="noreferrer" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-teal-700 hover:text-teal-900">
                                    Source page
                                    <ExternalLink class="size-3" />
                                </a>
                            </article>
                        </div>
                    </div>

                    <aside class="space-y-5">
                        <section class="panel">
                            <div class="panel-header">
                                <h2 class="text-sm font-bold text-zinc-950">Priority actions</h2>
                            </div>
                            <div class="panel-body">
                                <ul class="space-y-3">
                                    <li v-for="item in report.recommendations" :key="item" class="flex gap-3 text-sm leading-6 text-zinc-700">
                                        <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-teal-700" />
                                        <span>{{ item }}</span>
                                    </li>
                                </ul>
                            </div>
                        </section>

                        <section class="panel">
                            <div class="panel-header">
                                <h2 class="text-sm font-bold text-zinc-950">Content gaps</h2>
                            </div>
                            <div class="panel-body">
                                <ul class="space-y-3">
                                    <li v-for="gap in report.content_gaps" :key="gap" class="flex gap-3 text-sm leading-6 text-zinc-700">
                                        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-700" />
                                        <span>{{ gap }}</span>
                                    </li>
                                </ul>
                            </div>
                        </section>
                    </aside>
                </section>

                <section class="grid gap-5 xl:grid-cols-[1fr_.8fr]">
                    <div class="panel">
                        <div class="panel-header">
                            <h2 class="text-sm font-bold text-zinc-950">Readiness findings</h2>
                        </div>
                        <div class="grid gap-3 p-4 md:grid-cols-2">
                            <div v-for="finding in report.findings" :key="finding.label" class="rounded-lg border border-zinc-200 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-bold text-zinc-950">{{ finding.label }}</h3>
                                    <span class="text-sm font-bold" :class="scoreTextClass(finding.score)">{{ finding.score }}</span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-zinc-600">{{ finding.detail }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
