<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InfoHint from '@/Components/InfoHint.vue';
import { Check, FilePlus2, Filter, LoaderCircle, Sparkles, X } from 'lucide-vue-next';

const props = defineProps({
    stores: Array,
    credits: Object,
    topicCreditCost: Number,
    collections: Array,
    latestGeneration: Object,
    topics: Object,
    approvedTopics: Object,
    rejectedTopics: Object,
});

const regionOptions = {
    'United States': {
        California: ['Los Angeles', 'San Francisco', 'San Diego'],
        Florida: ['Miami', 'Orlando', 'Tampa'],
        NewYork: ['New York City', 'Buffalo', 'Rochester'],
        Texas: ['Houston', 'Dallas', 'Austin'],
    },
    Pakistan: {
        Punjab: ['Lahore', 'Faisalabad', 'Rawalpindi'],
        Sindh: ['Karachi', 'Hyderabad', 'Sukkur'],
        Islamabad: ['Islamabad'],
        KPK: ['Peshawar', 'Abbottabad'],
    },
    Canada: {
        Ontario: ['Toronto', 'Ottawa', 'Mississauga'],
        Quebec: ['Montreal', 'Quebec City'],
        BritishColumbia: ['Vancouver', 'Victoria'],
    },
    'United Kingdom': {
        England: ['London', 'Manchester', 'Birmingham'],
        Scotland: ['Edinburgh', 'Glasgow'],
        Wales: ['Cardiff', 'Swansea'],
    },
    Australia: {
        NSW: ['Sydney', 'Newcastle'],
        Victoria: ['Melbourne', 'Geelong'],
        Queensland: ['Brisbane', 'Gold Coast'],
    },
    UAE: {
        Dubai: ['Dubai'],
        AbuDhabi: ['Abu Dhabi'],
        Sharjah: ['Sharjah'],
    },
    India: {
        Maharashtra: ['Mumbai', 'Pune'],
        Delhi: ['New Delhi'],
        Karnataka: ['Bengaluru', 'Mysuru'],
    },
};

const toneOptions = [
    'Luxury',
    'Professional',
    'Friendly',
    'Educational',
    'Conversational',
    'Persuasive',
    'Premium',
    'Minimal',
];

const intentOptions = [
    { value: 'informational', label: 'Informational' },
    { value: 'commercial', label: 'Commercial' },
    { value: 'transactional', label: 'Transactional' },
    { value: 'navigational', label: 'Navigational' },
    { value: 'comparison', label: 'Comparison' },
    { value: 'buying_guide', label: 'Buying Guide' },
    { value: 'how_to', label: 'How-to' },
    { value: 'problem_solution', label: 'Problem/Solution' },
    { value: 'local_seo', label: 'Local SEO' },
    { value: 'seasonal', label: 'Seasonal' },
    { value: 'faq_answer_engine', label: 'FAQ / Answer Engine' },
    { value: 'product_education', label: 'Product Education' },
];

const countryCodeMap = {
    US: 'United States',
    PK: 'Pakistan',
    CA: 'Canada',
    GB: 'United Kingdom',
    UK: 'United Kingdom',
    AU: 'Australia',
    AE: 'UAE',
    IN: 'India',
};

const selectedStoreId = computed(() => props.stores[0]?.id ?? '');
const selectedTopics = ref([]);
const topicFilter = ref('waiting');
const showGenerateForm = ref(false);
const form = useForm({
    store_id: selectedStoreId.value,
    count: 5,
    target_country: '',
    target_state: '',
    target_city: '',
    target_language: 'en',
    tone: [],
    seo_focus: '',
    product_category: '',
    collection_ids: [],
    intent: 'informational',
    timezone: '',
});
const draftForm = useForm({
    topic_ids: [],
});

const normalizeCountry = (country) => countryCodeMap[String(country || '').toUpperCase()] || country || '';
const selectedStore = computed(() => props.stores.find((store) => String(store.id) === String(form.store_id)));
const states = computed(() => Object.keys(regionOptions[form.target_country] ?? {}));
const cities = computed(() => regionOptions[form.target_country]?.[form.target_state] ?? []);
const storeCollections = computed(() => props.collections.filter((collection) => String(collection.shopify_store_id) === String(form.store_id)));
const generationCreditCost = computed(() => Math.max(1, Number(form.count || 1)) * props.topicCreditCost);
const hasEnoughCredits = computed(() => generationCreditCost.value <= props.credits.balance);
const recommendedTopicLimit = computed(() => {
    const store = selectedStore.value;
    if (!store) return 3;

    const productCount = Number(store.products_count ?? 0);
    const collectionCount = form.collection_ids.length || Number(store.collections_count ?? 1);

    return Math.max(3, Math.min(25, Math.ceil(Math.sqrt(Math.max(1, productCount)) * 2) + Math.max(0, collectionCount - 1)));
});
const exceedsRecommendedTopicLimit = computed(() => Number(form.count || 0) > recommendedTopicLimit.value);
const waitingTopics = computed(() => props.topics.data ?? []);
const approvedTopicCards = computed(() => props.approvedTopics.data ?? []);
const rejectedTopicCards = computed(() => props.rejectedTopics.data ?? []);
const allTopicCards = computed(() => [
    ...waitingTopics.value,
    ...approvedTopicCards.value,
    ...rejectedTopicCards.value,
]);
const topicFilterOptions = computed(() => [
    { key: 'waiting', label: 'Ideas to review', count: waitingTopics.value.length },
    { key: 'approved', label: 'Ready to write', count: approvedTopicCards.value.length },
    { key: 'rejected', label: 'Skipped', count: rejectedTopicCards.value.length },
    { key: 'all', label: 'All', count: allTopicCards.value.length },
]);
const visibleTopics = computed(() => {
    if (topicFilter.value === 'approved') return approvedTopicCards.value;
    if (topicFilter.value === 'rejected') return rejectedTopicCards.value;
    if (topicFilter.value === 'all') return allTopicCards.value;

    return waitingTopics.value;
});
const isNewBatch = (topic) => Boolean(props.latestGeneration?.id) && Number(topic.ai_generation_id) === Number(props.latestGeneration.id);

const generationStatus = computed(() => {
    if (form.processing) return 'running';
    return props.latestGeneration?.status ?? null;
});

const generationProgress = computed(() => {
    if (form.processing) return 65;
    if (generationStatus.value === 'completed') return 100;
    if (generationStatus.value === 'failed') return 100;
    if (generationStatus.value === 'running') return 65;

    return 0;
});

watch(() => form.target_country, () => {
    form.target_state = '';
    form.target_city = '';
});

watch(() => form.target_state, () => {
    form.target_city = '';
});

watch(() => form.store_id, () => {
    form.collection_ids = [];
    const store = selectedStore.value;

    if (!store) return;

    form.target_country = normalizeCountry(store.country);
    form.target_language = store.primary_locale || store.default_language || form.target_language || 'en';
    form.timezone = store.timezone || form.timezone || '';
}, { immediate: true });

const generate = () => {
    if (!form.store_id) return;
    form.post(`/stores/${form.store_id}/topics`, {
        preserveScroll: true,
        onSuccess: () => {
            showGenerateForm.value = false;
            topicFilter.value = 'waiting';
        },
        onError: () => {
            showGenerateForm.value = true;
        },
    });
};

const approve = (topic, generateBlog = false) => router.post(`/topics/${topic.id}/approve`, { generate_blog: generateBlog }, { preserveScroll: true });
const reject = (topic) => router.post(`/topics/${topic.id}/reject`, {}, {
    preserveScroll: true,
    onSuccess: () => {
        selectedTopics.value = selectedTopics.value.filter((id) => id !== topic.id);
    },
});
const generateBlog = (topic) => router.post(`/topics/${topic.id}/generate-blog`, {}, { preserveScroll: true });
const generateSelectedBlogs = () => {
    draftForm.topic_ids = selectedTopics.value;
    draftForm.post('/topics/generate-selected-blogs', {
        preserveScroll: true,
        onSuccess: () => {
            selectedTopics.value = [];
            draftForm.reset();
        },
    });
};

const topicStatusLabel = (status) => ({
    waiting: 'Needs review',
    approved: 'Ready to write',
    rejected: 'Skipped',
}[status] ?? status);

const openExistingBlog = (topic) => {
    const blogId = topic.blogs?.[0]?.id;

    if (blogId) {
        router.get(`/blogs/${blogId}/edit`);
    }
};
</script>

<template>
    <Head title="Topics" />
    <AppLayout>
        <template #title>Blog Topics</template>

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-zinc-950">Topic ideas</h2>
                        <InfoHint text="Generate blog ideas from your Shopify catalog, save the good ones, then open the blog editor to write the full article." />
                    </div>
                    <p class="text-sm text-zinc-500">Generate ideas, keep the best ones, then open the blog editor to write the full article.</p>
                </div>
                <button class="btn btn-primary" type="button" @click="showGenerateForm = !showGenerateForm">
                    <X v-if="showGenerateForm" class="size-4" />
                    <Sparkles v-else class="size-4" />
                    {{ showGenerateForm ? 'Close topic form' : 'Generate New Topic' }}
                </button>
            </div>

            <section v-if="showGenerateForm" class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-950">Generate Topic</h2>
                        <p class="text-xs text-zinc-500">Use store knowledge, selected collections, region, intent, and tone to create topic ideas.</p>
                    </div>
                </div>
                <form class="panel-body space-y-5" @submit.prevent="generate">
                    <div class="grid gap-4 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <label>Store</label>
                            <select v-model="form.store_id">
                                <option value="" disabled>Select store</option>
                                <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label>Topics</label>
                            <input v-model="form.count" type="number" min="1" max="25" />
                            <p class="mt-1 text-xs" :class="exceedsRecommendedTopicLimit ? 'font-semibold text-rose-700' : 'text-zinc-500'">
                                Recommended max for this store data: {{ recommendedTopicLimit }} topics.
                            </p>
                        </div>
                        <div>
                            <label>Intent</label>
                            <select v-model="form.intent">
                                <option v-for="intent in intentOptions" :key="intent.value" :value="intent.value">{{ intent.label }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-5">
                        <div>
                            <label>Country</label>
                            <select v-model="form.target_country">
                                <option value="">Any country</option>
                                <option v-for="country in Object.keys(regionOptions)" :key="country" :value="country">{{ country }}</option>
                            </select>
                        </div>
                        <div>
                            <label>State/province</label>
                            <select v-model="form.target_state" :disabled="!states.length">
                                <option value="">Any state</option>
                                <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                            </select>
                        </div>
                        <div>
                            <label>City</label>
                            <select v-model="form.target_city" :disabled="!cities.length">
                                <option value="">Any city</option>
                                <option v-for="city in cities" :key="city" :value="city">{{ city }}</option>
                            </select>
                        </div>
                        <div>
                            <label>Language</label>
                            <input v-model="form.target_language" />
                        </div>
                        <div>
                            <label>Timezone</label>
                            <input v-model="form.timezone" />
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <div>
                            <label>Blog tone</label>
                            <select v-model="form.tone" multiple class="min-h-36">
                                <option v-for="tone in toneOptions" :key="tone" :value="tone">{{ tone }}</option>
                            </select>
                            <p class="mt-1 text-xs text-zinc-500">Hold Command/Ctrl to select multiple tones.</p>
                        </div>
                        <div>
                            <label>Collections</label>
                            <select v-model="form.collection_ids" multiple class="min-h-36">
                                <option v-for="collection in storeCollections" :key="collection.id" :value="collection.id">{{ collection.title }}</option>
                            </select>
                            <p class="mt-1 text-xs text-zinc-500">Select one or more synced Shopify collections.</p>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label>SEO focus</label>
                                <input v-model="form.seo_focus" />
                            </div>
                            <div>
                                <label>Product category/focus</label>
                                <input v-model="form.product_category" placeholder="Optional manual focus" />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
                        <div v-if="form.processing || generationStatus" class="rounded-md border border-zinc-200 bg-zinc-50 p-3">
                            <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                <div class="flex items-center gap-2 font-semibold text-zinc-800">
                                    <LoaderCircle v-if="form.processing || generationStatus === 'running'" class="size-4 animate-spin text-teal-700" />
                                    <Sparkles v-else class="size-4 text-teal-700" />
                                    <span>{{ form.processing ? 'Generating topic ideas' : `Latest generation: ${generationStatus}` }}</span>
                                </div>
                                <span class="text-xs font-semibold text-zinc-600">{{ generationProgress }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-200">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="generationStatus === 'failed' ? 'bg-rose-600' : 'bg-teal-700'"
                                    :style="{ width: `${generationProgress}%` }"
                                />
                            </div>
                            <p v-if="props.latestGeneration?.error_message" class="mt-2 text-xs text-rose-700">{{ props.latestGeneration.error_message }}</p>
                        </div>
                        <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm" :class="{ 'lg:col-start-2': !(form.processing || generationStatus) }">
                            <div class="flex justify-between gap-3"><span class="text-zinc-500">Credits remaining</span><span class="font-semibold text-zinc-950">{{ props.credits.balance.toLocaleString() }}</span></div>
                            <div class="mt-1 flex justify-between gap-3"><span class="text-zinc-500">This generation</span><span class="font-semibold text-zinc-950">{{ generationCreditCost }} credits</span></div>
                            <p v-if="!hasEnoughCredits" class="mt-2 text-xs font-semibold text-rose-700">Not enough credits for this topic batch.</p>
                            <p v-if="exceedsRecommendedTopicLimit" class="mt-2 text-xs font-semibold text-rose-700">Lower the topic count or select/sync more product data to avoid repeated or weak ideas.</p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button class="btn btn-primary min-w-48" :disabled="form.processing || !form.store_id || !hasEnoughCredits || exceedsRecommendedTopicLimit">
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                            <Sparkles v-else class="size-4" />
                            Generate Topic
                        </button>
                    </div>
                </form>
            </section>

            <section class="space-y-4">
                <div class="panel p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-bold text-zinc-950">Topic review</h2>
                                <InfoHint text="Step 1: review new ideas. Step 2: move the keepers into Ready to write. Step 3: click Write blog to open the blog editor." />
                            </div>
                            <p class="text-xs text-zinc-500">Step 1 review ideas. Step 2 move good ones to Ready to write. Step 3 write the full blog.</p>
                        </div>
                        <button class="btn btn-primary" type="button" :disabled="!selectedTopics.length || draftForm.processing" @click="generateSelectedBlogs">
                            <LoaderCircle v-if="draftForm.processing" class="size-4 animate-spin" />
                            <FilePlus2 v-else class="size-4" />
                            Write selected blogs
                        </button>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <div class="mr-1 inline-flex items-center gap-2 text-xs font-semibold uppercase text-zinc-500">
                            <Filter class="size-4" />
                            Filter
                        </div>
                        <button
                            v-for="option in topicFilterOptions"
                            :key="option.key"
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm font-semibold transition"
                            :class="topicFilter === option.key ? 'border-teal-700 bg-teal-700 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50'"
                            @click="topicFilter = option.key"
                        >
                            {{ option.label }}
                            <span class="ml-1 opacity-75">{{ option.count }}</span>
                        </button>
                    </div>
                    <div v-if="draftForm.processing" class="mt-3">
                        <div class="mb-1 flex justify-between text-xs text-zinc-600"><span>Writing selected blogs</span><span>65%</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100"><div class="h-full w-[65%] rounded-full bg-teal-700" /></div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="topic in visibleTopics" :key="`${topic.status}-${topic.id}`" class="panel">
                        <div class="panel-body">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <label class="mb-2 flex items-start gap-2 normal-case tracking-normal text-zinc-950">
                                        <input
                                            v-if="topic.status !== 'rejected'"
                                            v-model="selectedTopics"
                                            type="checkbox"
                                            :value="topic.id"
                                            class="mt-1 size-4 rounded border-zinc-300 p-0"
                                        />
                                        <span class="font-bold leading-6">{{ topic.title }}</span>
                                    </label>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                        <span>{{ topic.store?.name }} · {{ topic.search_intent ?? 'intent pending' }}</span>
                                        <span v-if="isNewBatch(topic)" class="badge bg-teal-100 text-teal-800">New batch</span>
                                        <span v-if="topic.blogs_count" class="badge bg-indigo-100 text-indigo-800">Blog ready</span>
                                    </div>
                                </div>
                                <span class="badge shrink-0" :class="`badge-${topic.status}`">{{ topicStatusLabel(topic.status) }}</span>
                            </div>
                            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-zinc-500">Primary keyword</dt>
                                    <dd class="mt-1 text-zinc-800">{{ topic.primary_keyword ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-zinc-500">SEO/AEO score</dt>
                                    <dd class="mt-1 text-zinc-800">{{ topic.opportunity_score ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-zinc-500">Article size</dt>
                                    <dd class="mt-1 text-zinc-800">{{ topic.estimated_article_size ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-zinc-500">Suggested category</dt>
                                    <dd class="mt-1 text-zinc-800">{{ topic.response?.suggested_category || topic.product_category || '-' }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-semibold uppercase text-zinc-500">Outline</dt>
                                    <dd class="mt-1 whitespace-normal text-zinc-800">{{ (topic.suggested_outline ?? []).join(' · ') || '-' }}</dd>
                                </div>
                            </dl>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button v-if="topic.status !== 'approved' && topic.status !== 'rejected'" class="btn btn-secondary" title="Approve" @click="approve(topic)">
                                    <Check class="size-4" />Save for later
                                </button>
                                <button v-if="topic.blogs_count" class="btn btn-secondary" title="Open blog" @click="openExistingBlog(topic)">
                                    <FilePlus2 class="size-4" />View blog
                                </button>
                                <button v-else-if="topic.status !== 'rejected'" class="btn btn-primary" title="Generate blog" @click="generateBlog(topic)">
                                    <FilePlus2 class="size-4" />Write blog
                                </button>
                                <button v-if="topic.status !== 'approved' && topic.status !== 'rejected'" class="btn btn-danger" title="Reject" @click="reject(topic)">
                                    <X class="size-4" />Skip
                                </button>
                            </div>
                        </div>
                    </article>
                    <div v-if="!visibleTopics.length" class="panel p-6 text-sm text-zinc-500 lg:col-span-2 2xl:col-span-3">No topics in this filter.</div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
