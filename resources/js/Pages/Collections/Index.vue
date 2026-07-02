<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Coins, ExternalLink, LoaderCircle, RefreshCw, Search, Sparkles, UploadCloud, X } from 'lucide-vue-next';

const props = defineProps({
    collections: Object,
    filters: Object,
    credits: Object,
    collectionCreditCosts: Object,
    stores: Array,
});

const selected = ref(null);
const creditBalance = ref(props.credits.balance ?? 0);
const contentError = ref('');
const contentStatus = ref('');
const generatingContent = ref(false);
const pushingContent = ref(false);
const benefitsText = ref('[]');
const faqText = ref('[]');
const contentForm = ref({
    collection_brief: '',
    description_style: 'balanced',
    generated_description: '',
    generated_intro: '',
    generated_benefits: [],
    generated_faq: [],
    generated_meta_title: '',
    generated_meta_description: '',
    generated_handle: '',
    generated_aeo_content: '',
});
const filters = ref({
    search: props.filters.search ?? '',
    store: props.filters.store ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => router.get('/collections', filters.value, { preserveState: true, preserveScroll: true });
const resetFilters = () => {
    filters.value = { search: '', store: '', status: '' };
    router.get('/collections', {}, { preserveState: true, preserveScroll: true });
};

const collectionCountLabel = computed(() => {
    const total = props.collections.total ?? props.collections.data.length;
    return `${total} synced collection${total === 1 ? '' : 's'}`;
});

const selectedStyleCost = computed(() => props.collectionCreditCosts[contentForm.value.description_style] ?? props.collectionCreditCosts.balanced);
const hasEnoughCredits = computed(() => selectedStyleCost.value <= creditBalance.value);

const plainDescription = (html) => {
    if (!html) return 'No description synced from Shopify.';
    return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
};

const parseJson = (value, fallback = []) => {
    try {
        return JSON.parse(value || 'null') ?? fallback;
    } catch {
        return fallback;
    }
};

const collectionUrl = (collection) => {
    if (!collection) return '';

    if (collection.url) return collection.url;

    const baseUrl = collection.store?.shop_url || (collection.store?.shop_domain ? `https://${collection.store.shop_domain}` : '');

    return baseUrl && collection.handle ? `${baseUrl.replace(/\/$/, '')}/collections/${collection.handle}` : '';
};

const descriptionStatus = (collection) => {
    if (collection.shopify_pushed_at) return 'Pushed';
    if (collection.generated_at) return 'Generated';
    if (plainDescription(collection.description) === 'No description synced from Shopify.') return 'Missing';

    return 'Synced';
};

const badgeClass = (collection) => {
    const status = descriptionStatus(collection).toLowerCase();

    if (status === 'missing') return 'badge-rejected';
    if (status === 'generated') return 'badge-generated';
    if (status === 'pushed') return 'badge-published';

    return 'badge-completed';
};

const replaceCollection = (collection) => {
    const index = props.collections.data.findIndex((item) => item.id === collection.id);

    if (index !== -1) {
        props.collections.data[index] = collection;
    }

    selected.value = collection;
};

const openCollection = (collection) => {
    selected.value = collection;
    contentError.value = '';
    contentStatus.value = '';
    benefitsText.value = JSON.stringify(collection.generated_benefits ?? [], null, 2);
    faqText.value = JSON.stringify(collection.generated_faq ?? [], null, 2);
    contentForm.value = {
        collection_brief: plainDescription(collection.description),
        description_style: 'balanced',
        generated_description: collection.generated_description || '',
        generated_intro: collection.generated_intro || '',
        generated_benefits: collection.generated_benefits ?? [],
        generated_faq: collection.generated_faq ?? [],
        generated_meta_title: collection.generated_meta_title || collection.seo_title || '',
        generated_meta_description: collection.generated_meta_description || collection.seo_description || '',
        generated_handle: collection.generated_handle || collection.handle || '',
        generated_aeo_content: collection.generated_aeo_content || '',
    };
};

const generateContent = async () => {
    if (!selected.value || !hasEnoughCredits.value) return;

    contentError.value = '';
    contentStatus.value = '';
    generatingContent.value = true;

    try {
        const response = await window.axios.post(`/collections/${selected.value.id}/generate-content`, {
            collection_brief: contentForm.value.collection_brief,
            description_style: contentForm.value.description_style,
        }, { headers: { Accept: 'application/json' } });

        const collection = response.data.collection;
        replaceCollection(collection);
        contentForm.value.generated_description = collection.generated_description || '';
        contentForm.value.generated_intro = collection.generated_intro || '';
        contentForm.value.generated_benefits = collection.generated_benefits ?? [];
        contentForm.value.generated_faq = collection.generated_faq ?? [];
        contentForm.value.generated_meta_title = collection.generated_meta_title || '';
        contentForm.value.generated_meta_description = collection.generated_meta_description || '';
        contentForm.value.generated_handle = collection.generated_handle || collection.handle || '';
        contentForm.value.generated_aeo_content = collection.generated_aeo_content || '';
        benefitsText.value = JSON.stringify(contentForm.value.generated_benefits, null, 2);
        faqText.value = JSON.stringify(contentForm.value.generated_faq, null, 2);
        creditBalance.value = Math.max(0, creditBalance.value - selectedStyleCost.value);
        contentStatus.value = response.data.message;
    } catch (error) {
        contentError.value = error.response?.data?.message ?? 'Collection content generation failed.';
    } finally {
        generatingContent.value = false;
    }
};

const pushContent = async () => {
    if (!selected.value) return;

    contentError.value = '';
    contentStatus.value = '';
    pushingContent.value = true;

    try {
        const response = await window.axios.post(`/collections/${selected.value.id}/push-content`, {
            generated_description: contentForm.value.generated_description,
            generated_intro: contentForm.value.generated_intro,
            generated_benefits: parseJson(benefitsText.value),
            generated_faq: parseJson(faqText.value),
            generated_meta_title: contentForm.value.generated_meta_title,
            generated_meta_description: contentForm.value.generated_meta_description,
            generated_handle: contentForm.value.generated_handle,
            generated_aeo_content: contentForm.value.generated_aeo_content,
        }, { headers: { Accept: 'application/json' } });

        replaceCollection(response.data.collection);
        contentStatus.value = response.data.message;
    } catch (error) {
        contentError.value = error.response?.data?.message ?? 'Collection push to Shopify failed.';
    } finally {
        pushingContent.value = false;
    }
};
</script>

<template>
    <Head title="Collections" />
    <AppLayout>
        <template #title>Collections</template>

        <section class="panel">
            <div class="panel-header flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-zinc-950">Synced Shopify Collections</h2>
                    <p class="text-xs text-zinc-500">{{ collectionCountLabel }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-secondary" type="button" @click="applyFilters">
                        <RefreshCw class="size-4" />
                        Refresh view
                    </button>
                    <Link href="/store-audit" class="btn btn-secondary">Open store audit</Link>
                </div>
            </div>

            <div class="panel-body border-b border-zinc-200">
                <div class="grid gap-3 lg:grid-cols-[1.4fr_1fr_1fr_auto]">
                    <div>
                        <label>Search</label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                            <input v-model="filters.search" class="pl-9" placeholder="Collection title, handle, description" @keyup.enter="applyFilters" />
                        </div>
                    </div>
                    <div>
                        <label>Store</label>
                        <select v-model="filters.store">
                            <option value="">All stores</option>
                            <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label>Status</label>
                        <select v-model="filters.status">
                            <option value="">Any status</option>
                            <option value="missing_description">Missing description</option>
                            <option value="generated">Generated</option>
                            <option value="pushed">Pushed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="btn btn-primary" type="button" @click="applyFilters">Apply</button>
                        <button class="btn btn-secondary" type="button" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Collection</th>
                            <th>Store</th>
                            <th>Description</th>
                            <th>SEO</th>
                            <th>Products</th>
                            <th>Last synced</th>
                            <th>Last optimized</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="collection in props.collections.data" :key="collection.id">
                            <td>
                                <div class="flex min-w-72 items-center gap-3">
                                    <img
                                        v-if="collection.image_url"
                                        :src="collection.image_url"
                                        :alt="collection.title"
                                        class="size-14 rounded-md border border-zinc-200 object-cover"
                                    />
                                    <div v-else class="grid size-14 place-items-center rounded-md border border-dashed border-zinc-300 text-xs text-zinc-400">No image</div>
                                    <div>
                                        <button class="two-line-title max-w-80 text-left font-semibold text-zinc-950 hover:text-teal-700" type="button" @click="openCollection(collection)">
                                            {{ collection.title }}
                                        </button>
                                        <div class="mt-1 text-xs text-zinc-500">{{ collection.handle || 'No handle' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ collection.store?.name }}</td>
                            <td><span class="badge" :class="badgeClass(collection)">{{ descriptionStatus(collection) }}</span></td>
                            <td>
                                <div class="max-w-56 truncate text-xs text-zinc-600">{{ collection.seo_title || collection.generated_meta_title || 'No SEO title' }}</div>
                                <div class="mt-1 max-w-56 truncate text-xs text-zinc-500">{{ collection.seo_description || collection.generated_meta_description || 'No meta description' }}</div>
                            </td>
                            <td>{{ collection.product_count ?? '-' }}</td>
                            <td>{{ collection.last_synced_at ? new Date(collection.last_synced_at).toLocaleDateString() : '-' }}</td>
                            <td>{{ collection.last_optimized_at ? new Date(collection.last_optimized_at).toLocaleDateString() : '-' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <button class="btn btn-secondary" type="button" @click="openCollection(collection)">View</button>
                                    <a
                                        v-if="collectionUrl(collection)"
                                        class="btn btn-secondary !px-2"
                                        :href="collectionUrl(collection)"
                                        target="_blank"
                                        rel="noreferrer"
                                        title="View collection on website"
                                    >
                                        <ExternalLink class="size-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!props.collections.data.length">
                            <td colspan="8" class="text-zinc-500">No collections match the current filters. Sync a connected store first.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="props.collections.links?.length > 3" class="flex flex-wrap gap-2 border-t border-zinc-200 p-4">
                <Link
                    v-for="link in props.collections.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-md border px-3 py-2 text-sm"
                    :class="[
                        link.active ? 'border-teal-700 bg-teal-700 text-white' : 'border-zinc-200 text-zinc-700',
                        !link.url ? 'pointer-events-none opacity-40' : ''
                    ]"
                    v-html="link.label"
                />
            </div>
        </section>

        <div v-if="selected" class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/50 p-4" @click.self="selected = null">
            <section class="max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-md bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
                    <div>
                        <h2 class="two-line-title max-w-3xl text-base font-bold text-zinc-950">{{ selected.title }}</h2>
                        <p class="text-xs text-zinc-500">{{ selected.store?.name }} · {{ selected.handle || 'No handle' }}</p>
                    </div>
                    <button class="btn btn-secondary" type="button" @click="selected = null"><X class="size-4" /></button>
                </div>

                <div class="grid max-h-[calc(90vh-73px)] gap-5 overflow-y-auto p-5 lg:grid-cols-[280px_1fr]">
                    <div class="space-y-4">
                        <img
                            v-if="selected.image_url"
                            :src="selected.image_url"
                            :alt="selected.title"
                            class="aspect-square w-full rounded-md border border-zinc-200 object-cover"
                        />
                        <div v-else class="grid aspect-square w-full place-items-center rounded-md border border-dashed border-zinc-300 text-sm text-zinc-400">No image synced</div>

                        <a v-if="collectionUrl(selected)" class="btn btn-secondary w-full justify-center" :href="collectionUrl(selected)" target="_blank" rel="noreferrer">
                            <ExternalLink class="size-4" />
                            View collection on website
                        </a>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-md bg-zinc-100 p-3">
                                <div class="text-zinc-500">Description</div>
                                <div class="font-semibold text-zinc-950">{{ descriptionStatus(selected) }}</div>
                            </div>
                            <div class="rounded-md bg-zinc-100 p-3">
                                <div class="text-zinc-500">Products</div>
                                <div class="font-semibold text-zinc-950">{{ selected.product_count ?? '-' }}</div>
                            </div>
                            <div class="rounded-md bg-zinc-100 p-3">
                                <div class="text-zinc-500">Synced</div>
                                <div class="font-semibold text-zinc-950">{{ selected.last_synced_at ? new Date(selected.last_synced_at).toLocaleDateString() : '-' }}</div>
                            </div>
                            <div class="rounded-md bg-zinc-100 p-3">
                                <div class="text-zinc-500">Optimized</div>
                                <div class="font-semibold text-zinc-950">{{ selected.last_optimized_at ? new Date(selected.last_optimized_at).toLocaleDateString() : '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <h3 class="mb-2 text-sm font-bold text-zinc-950">Current description</h3>
                            <div class="prose prose-sm max-w-none rounded-md border border-zinc-200 p-4 text-zinc-700" v-html="selected.description || plainDescription(selected.description)" />
                        </div>

                        <section class="rounded-md border border-teal-200 bg-teal-50/50 p-4">
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-zinc-950">AI collection content</h3>
                                    <p class="mt-1 text-xs text-zinc-600">Generate SEO and AEO collection copy, review it, then push it to Shopify.</p>
                                </div>
                                <div class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-xs font-semibold text-zinc-700">
                                    <Coins class="size-4 text-teal-700" />
                                    {{ selectedStyleCost }} credits
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-[1fr_220px]">
                                <div>
                                    <label>Collection details or notes</label>
                                    <textarea v-model="contentForm.collection_brief" class="min-h-28" placeholder="Add important brand, product, or category details the AI should use." />
                                </div>
                                <div>
                                    <label>Description style</label>
                                    <select v-model="contentForm.description_style">
                                        <option value="short">Short</option>
                                        <option value="balanced">Balanced</option>
                                        <option value="long">Long</option>
                                    </select>
                                    <div class="mt-3 rounded-md border border-zinc-200 bg-white p-3 text-sm">
                                        <div class="flex justify-between gap-3">
                                            <span class="text-zinc-500">Credits remaining</span>
                                            <span class="font-semibold text-zinc-950">{{ creditBalance.toLocaleString() }}</span>
                                        </div>
                                        <p v-if="!hasEnoughCredits" class="mt-2 text-xs font-semibold text-rose-700">Not enough credits for this collection generation.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button class="btn btn-primary" type="button" :disabled="generatingContent || !hasEnoughCredits" @click="generateContent">
                                    <LoaderCircle v-if="generatingContent" class="size-4 animate-spin" />
                                    <Sparkles v-else class="size-4" />
                                    Generate description
                                </button>
                                <button class="btn btn-secondary" type="button" :disabled="pushingContent || !contentForm.generated_description" @click="pushContent">
                                    <LoaderCircle v-if="pushingContent" class="size-4 animate-spin" />
                                    <UploadCloud v-else class="size-4" />
                                    Push to Shopify
                                </button>
                            </div>

                            <div v-if="contentError" class="mt-3 rounded-md border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800">{{ contentError }}</div>
                            <div v-if="contentStatus" class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ contentStatus }}</div>

                            <div v-if="contentForm.generated_description" class="mt-5 space-y-4">
                                <div>
                                    <label>Generated collection description</label>
                                    <textarea v-model="contentForm.generated_description" class="min-h-56 font-mono text-xs" />
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label>Short intro</label>
                                        <textarea v-model="contentForm.generated_intro" class="min-h-24" />
                                    </div>
                                    <div>
                                        <label>AEO question-answer content</label>
                                        <textarea v-model="contentForm.generated_aeo_content" class="min-h-24" />
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label>Benefits JSON</label>
                                        <textarea v-model="benefitsText" class="min-h-28 font-mono text-xs" />
                                    </div>
                                    <div>
                                        <label>FAQ JSON</label>
                                        <textarea v-model="faqText" class="min-h-28 font-mono text-xs" />
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <label>Suggested handle</label>
                                        <input v-model="contentForm.generated_handle" />
                                    </div>
                                    <div>
                                        <label>Meta title</label>
                                        <input v-model="contentForm.generated_meta_title" />
                                    </div>
                                    <div>
                                        <label>Meta description</label>
                                        <textarea v-model="contentForm.generated_meta_description" class="min-h-20" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <h3 class="mb-2 text-sm font-bold text-zinc-950">SEO Title</h3>
                                <p class="rounded-md border border-zinc-200 p-3 text-sm text-zinc-700">{{ selected.seo_title || 'No SEO title synced.' }}</p>
                            </div>
                            <div>
                                <h3 class="mb-2 text-sm font-bold text-zinc-950">SEO Description</h3>
                                <p class="rounded-md border border-zinc-200 p-3 text-sm text-zinc-700">{{ selected.seo_description || 'No SEO description synced.' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
