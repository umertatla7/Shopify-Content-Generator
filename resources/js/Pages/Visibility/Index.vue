<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    BookOpen,
    Bot,
    CheckCircle2,
    CircleAlert,
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
} from 'lucide-vue-next';

const props = defineProps({
    stores: Array,
    selectedStoreId: Number,
    report: Object,
    reports: Array,
});

const generating = ref(false);
const selectedStoreId = ref(props.selectedStoreId ?? props.stores?.[0]?.id ?? '');

const report = computed(() => props.report);
const promptChecks = computed(() => report.value?.prompt_checks ?? []);
const weakestPrompts = computed(() => promptChecks.value.slice(0, 12));
const coveredPrompts = computed(() => promptChecks.value.filter((prompt) => prompt.status === 'covered').length);

const selectedStore = computed(() => props.stores.find((store) => store.id === Number(selectedStoreId.value)) ?? props.stores?.[0]);

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

const formatDate = (date) => date ? new Date(date).toLocaleString() : 'Not generated';
const evidenceValue = (value) => Array.isArray(value) ? value.join(', ') : value;
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

                <section class="grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
                    <div class="panel">
                        <div class="panel-header">
                            <div>
                                <h2 class="text-sm font-bold text-zinc-950">AI prompt coverage</h2>
                                <p class="text-xs text-zinc-500">{{ coveredPrompts }} covered from {{ report.tracked_prompt_count }} tracked prompts</p>
                            </div>
                            <div class="flex gap-2 text-xs font-semibold">
                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-800">{{ report.covered_prompt_count }} covered</span>
                                <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-800">{{ report.partial_prompt_count }} partial</span>
                                <span class="rounded-full bg-rose-100 px-2 py-1 text-rose-800">{{ report.missing_prompt_count }} missing</span>
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

                    <div class="panel">
                        <div class="panel-header">
                            <h2 class="text-sm font-bold text-zinc-950">Recent reports</h2>
                        </div>
                        <div class="divide-y divide-zinc-100">
                            <div v-for="history in props.reports" :key="history.id" class="grid grid-cols-[1fr_auto] gap-3 p-4">
                                <div>
                                    <p class="text-sm font-semibold text-zinc-950">{{ formatDate(history.created_at) }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ history.tracked_prompt_count }} prompts tracked</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold" :class="scoreTextClass(history.overall_score)">{{ history.overall_score }}</p>
                                    <p class="text-xs text-zinc-500">AEO {{ history.aeo_score }} · GEO {{ history.geo_score }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h2 class="text-sm font-bold text-zinc-950">Prompt evidence</h2>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Prompt</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Entity</th>
                                    <th>Evidence</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <tr v-for="prompt in promptChecks" :key="prompt.id">
                                    <td class="max-w-md px-4 py-3">
                                        <p class="font-semibold text-zinc-950">{{ prompt.prompt }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ prompt.intent?.replaceAll('_', ' ') }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-bold capitalize" :class="promptStatusClass(prompt.status)">{{ prompt.status }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-bold">{{ prompt.score }}</td>
                                    <td class="px-4 py-3 text-zinc-600">{{ prompt.target_entity_label || '-' }}</td>
                                    <td class="max-w-sm px-4 py-3 text-xs text-zinc-500">
                                        <span v-for="(value, key) in (prompt.evidence ?? {})" :key="key" class="mr-2 inline-block">
                                            {{ key }}: {{ evidenceValue(value) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
