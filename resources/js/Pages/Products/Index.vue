<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InfoHint from '@/Components/InfoHint.vue';
import TablePagination from '@/Components/TablePagination.vue';
import { Coins, ExternalLink, LoaderCircle, RefreshCw, Search, Sparkles, Undo2, UploadCloud, X } from 'lucide-vue-next';

const props = defineProps({
    products: Object,
    filters: Object,
    credits: Object,
    planUsage: Object,
    productCreditCosts: Object,
    stores: Array,
    primaryStore: Object,
    filterOptions: Object,
});

const selected = ref(null);
const originalContent = ref(null);
const creditBalance = ref(props.credits.balance ?? 0);
const contentError = ref('');
const contentStatus = ref('');
const generatingContent = ref(false);
const pushingContent = ref(false);
const contentForm = ref({
    base_title: '',
    base_description: '',
    description_style: 'balanced',
    generated_title: '',
    generated_description: '',
    generated_seo_title: '',
    generated_seo_description: '',
});
const filters = ref({
    search: props.filters.search ?? '',
    store: props.filters.store ?? '',
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    vendor: props.filters.vendor ?? '',
    per_page: Number(props.filters.per_page ?? props.products.per_page ?? 15),
});

const applyFilters = () => router.get('/products', filters.value, { preserveState: true, preserveScroll: true });
const resetFilters = () => {
    filters.value = { search: '', store: '', status: '', type: '', vendor: '', per_page: 15 };
    router.get('/products', {}, { preserveState: true, preserveScroll: true });
};

const productCountLabel = computed(() => {
    const total = props.products.total ?? props.products.data.length;
    return `${total} synced product${total === 1 ? '' : 's'}`;
});

const selectedStyleCost = computed(() => props.productCreditCosts[contentForm.value.description_style] ?? props.productCreditCosts.balanced);
const hasEnoughCredits = computed(() => selectedStyleCost.value <= creditBalance.value);
const productMetric = computed(() => props.planUsage?.metrics?.product_descriptions ?? null);
const syncingStore = ref(false);
const hasGeneratedDraft = computed(() => Boolean(contentForm.value.generated_title || contentForm.value.generated_description));

const plainDescription = (html) => {
    if (!html) return 'No description synced from Shopify.';
    return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
};

const productUrl = (product) => {
    if (!product) return '';
    if (!['active', 'published'].includes(String(product.status || '').toLowerCase())) return '';

    if (product.url) return product.url;

    const baseUrl = product.store?.shop_url || (product.store?.shop_domain ? `https://${product.store.shop_domain}` : '');

    return baseUrl && product.handle ? `${baseUrl.replace(/\/$/, '')}/products/${product.handle}` : '';
};

const isDraftProduct = (product) => String(product?.status || '').toLowerCase() === 'draft';
const productContentState = (product) => {
    if (product.shopify_content_pushed_at) return 'Pushed';
    if (product.generated_description || product.generated_title) return 'Generated';
    if (!plainDescription(product.description) || plainDescription(product.description) === 'No description synced from Shopify.') return 'Missing';
    return 'Synced';
};
const contentBadgeClass = (product) => {
    const status = productContentState(product).toLowerCase();

    if (status === 'pushed') return 'badge-published';
    if (status === 'generated') return 'badge-generated';
    if (status === 'missing') return 'badge-rejected';
    return 'badge-completed';
};
const syncProgress = computed(() => {
    const status = props.primaryStore?.latest_sync_log?.status;

    if (status === 'completed' || status === 'failed') return 100;
    if (status === 'running') return 65;
    if (status === 'pending') return 25;

    return props.primaryStore?.last_synced_at ? 100 : 0;
});
const syncLabel = computed(() => {
    const log = props.primaryStore?.latest_sync_log;

    if (!props.primaryStore) return 'No connected store';
    if (!log) return props.primaryStore.last_synced_at ? 'Catalog synced' : 'Sync products from Shopify to get started';
    if (log.status === 'completed') {
        const counts = log.counts || {};

        return `${counts.products ?? 0} products, ${counts.collections ?? 0} collections, ${counts.pages ?? 0} pages, ${counts.existing_blogs ?? 0} blogs synced`;
    }
    if (log.status === 'failed') return log.error_message || 'Sync failed';
    if (log.status === 'running') return 'Sync running';
    if (log.status === 'pending') return 'Sync pending';

    return log.status;
});

const replaceProduct = (product) => {
    const index = props.products.data.findIndex((item) => item.id === product.id);

    if (index !== -1) {
        props.products.data[index] = product;
    }

    selected.value = product;
};

const openProduct = (product) => {
    selected.value = product;
    contentError.value = '';
    contentStatus.value = '';
    originalContent.value = {
        title: product.title || '',
        description: plainDescription(product.description || ''),
        seo_title: product.seo_title || '',
        seo_description: product.seo_description || '',
    };
    contentForm.value = {
        base_title: product.generated_title || product.title || '',
        base_description: plainDescription(product.generated_description || product.description || ''),
        description_style: product.generated_description_style || 'balanced',
        generated_title: product.generated_title || '',
        generated_description: product.generated_description || '',
        generated_seo_title: product.generated_seo_title || '',
        generated_seo_description: product.generated_seo_description || '',
    };
};

const syncStore = () => {
    if (!props.primaryStore?.id) return;

    syncingStore.value = true;
    router.post(`/stores/${props.primaryStore.id}/sync`, {}, {
        preserveScroll: true,
        onFinish: () => syncingStore.value = false,
    });
};

const generateContent = async () => {
    if (!selected.value || !hasEnoughCredits.value) return;

    contentError.value = '';
    contentStatus.value = '';
    generatingContent.value = true;

    try {
        const response = await window.axios.post(`/products/${selected.value.id}/generate-content`, {
            base_title: contentForm.value.base_title,
            base_description: contentForm.value.base_description,
            description_style: contentForm.value.description_style,
        }, { headers: { Accept: 'application/json' } });

        const product = response.data.product;
        replaceProduct(product);
        contentForm.value.generated_title = product.generated_title || '';
        contentForm.value.generated_description = product.generated_description || '';
        contentForm.value.generated_seo_title = product.generated_seo_title || '';
        contentForm.value.generated_seo_description = product.generated_seo_description || '';
        contentForm.value.base_title = product.generated_title || contentForm.value.base_title;
        contentForm.value.base_description = plainDescription(product.generated_description || contentForm.value.base_description);
        creditBalance.value = Math.max(0, creditBalance.value - selectedStyleCost.value);
        contentStatus.value = 'AI draft ready. Review the title and description below, then push to Shopify when you are happy with it.';
    } catch (error) {
        contentError.value = error.response?.data?.message ?? 'Product content generation failed.';
    } finally {
        generatingContent.value = false;
    }
};

const restoreOriginalContent = () => {
    if (!originalContent.value) return;

    contentForm.value.base_title = originalContent.value.title;
    contentForm.value.base_description = originalContent.value.description;
    contentForm.value.generated_title = '';
    contentForm.value.generated_description = '';
    contentForm.value.generated_seo_title = originalContent.value.seo_title;
    contentForm.value.generated_seo_description = originalContent.value.seo_description;
    contentStatus.value = 'Restored the original Shopify content in this window.';
    contentError.value = '';

    if (selected.value) {
        replaceProduct({
            ...selected.value,
            generated_title: null,
            generated_description: null,
            generated_seo_title: null,
            generated_seo_description: null,
        });
    }
};

const pushContent = async (publish = false) => {
    if (!selected.value) return;

    contentError.value = '';
    contentStatus.value = '';
    pushingContent.value = true;

    try {
        const response = await window.axios.post(`/products/${selected.value.id}/push-content`, {
            generated_title: contentForm.value.generated_title,
            generated_description: contentForm.value.generated_description,
            generated_seo_title: contentForm.value.generated_seo_title,
            generated_seo_description: contentForm.value.generated_seo_description,
            publish,
        }, { headers: { Accept: 'application/json' } });

        replaceProduct(response.data.product);
        contentStatus.value = response.data.message;
    } catch (error) {
        contentError.value = error.response?.data?.message ?? 'Product push to Shopify failed.';
    } finally {
        pushingContent.value = false;
    }
};

const changePage = (page) => {
    router.get('/products', { ...filters.value, page }, { preserveState: true, preserveScroll: true });
};

const changePerPage = (perPage) => {
    filters.value.per_page = perPage;
    router.get('/products', { ...filters.value, page: 1 }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Products" />
    <AppLayout>
        <template #title>Products</template>

        <section class="panel">
            <div class="panel-header flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-zinc-950">Synced Shopify Products</h2>
                    <p class="text-xs text-zinc-500">{{ productCountLabel }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div v-if="productMetric" class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs font-semibold text-zinc-700">
                        {{ productMetric.used }}/{{ productMetric.limit ?? 'Unlimited' }} product descriptions used this month
                    </div>
                    <button v-if="props.primaryStore?.id" class="btn btn-secondary" type="button" :disabled="syncingStore" @click="syncStore">
                        <LoaderCircle v-if="syncingStore" class="size-4 animate-spin" />
                        <RefreshCw v-else class="size-4" />
                        Sync products
                    </button>
                    <Link href="/stores" class="btn btn-secondary">Store center</Link>
                </div>
            </div>

            <div v-if="props.primaryStore" class="panel-body border-b border-zinc-200">
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-zinc-950">{{ props.primaryStore.name }}</div>
                            <div class="mt-1 text-xs text-zinc-500">{{ syncLabel }}</div>
                        </div>
                        <div class="text-right text-xs text-zinc-500">
                            <div class="font-semibold text-zinc-700">{{ syncProgress }}%</div>
                            <div>{{ props.primaryStore.last_synced_at ? new Date(props.primaryStore.last_synced_at).toLocaleString() : 'Not synced yet' }}</div>
                        </div>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-200">
                        <div class="h-full rounded-full bg-teal-700 transition-all" :style="{ width: `${syncProgress}%` }" />
                    </div>
                </div>
            </div>

            <div class="panel-body border-b border-zinc-200">
                <div class="grid gap-3 lg:grid-cols-[1.4fr_repeat(4,1fr)_auto]">
                    <div>
                        <label>Search</label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                            <input v-model="filters.search" class="pl-9" placeholder="Title, description, product type" @keyup.enter="applyFilters" />
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
                            <option v-for="status in props.filterOptions.statuses" :key="status" :value="status">{{ status }}</option>
                        </select>
                    </div>
                    <div>
                        <label>Type</label>
                        <select v-model="filters.type">
                            <option value="">Any type</option>
                            <option v-for="type in props.filterOptions.types" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </div>
                    <div>
                        <label>Vendor</label>
                        <select v-model="filters.vendor">
                            <option value="">Any vendor</option>
                            <option v-for="vendor in props.filterOptions.vendors" :key="vendor" :value="vendor">{{ vendor }}</option>
                        </select>
                    </div>
                    <div>
                        <label>Rows</label>
                        <select v-model="filters.per_page">
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
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
                            <th>Product</th>
                            <th>Store</th>
                            <th>Type</th>
                            <th>Vendor</th>
                            <th>Status</th>
                            <th>SEO</th>
                            <th>Synced</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in props.products.data" :key="product.id">
                            <td>
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="product.image_url"
                                        :src="product.image_url"
                                        :alt="product.title"
                                        class="size-14 rounded-md border border-zinc-200 object-cover"
                                    />
                                    <div v-else class="grid size-14 place-items-center rounded-md border border-dashed border-zinc-300 text-xs text-zinc-400">No image</div>
                                    <div>
                                        <button class="two-line-title max-w-64 text-left font-semibold text-zinc-950 hover:text-teal-700" type="button" @click="openProduct(product)">
                                            {{ product.title }}
                                        </button>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span class="badge" :class="contentBadgeClass(product)">{{ productContentState(product) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ product.store?.name }}</td>
                            <td>{{ product.product_type || '-' }}</td>
                            <td>{{ product.vendor || '-' }}</td>
                            <td><span class="badge" :class="`badge-${product.status || 'draft'}`">{{ product.status || 'unknown' }}</span></td>
                            <td>
                                <div class="max-w-48 text-xs text-zinc-600">{{ product.seo_title || product.seo_description || 'No SEO fields synced' }}</div>
                            </td>
                            <td>{{ product.last_synced_at ? new Date(product.last_synced_at).toLocaleString() : '-' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <button class="btn btn-secondary" type="button" @click="openProduct(product)">View</button>
                                    <a
                                        v-if="productUrl(product)"
                                        class="btn btn-secondary !px-2"
                                        :href="productUrl(product)"
                                        target="_blank"
                                        rel="noreferrer"
                                        title="View product on website"
                                    >
                                        <ExternalLink class="size-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!props.products.data.length">
                            <td colspan="8" class="text-zinc-500">No products match the current filters. Sync a connected store first.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination
                v-if="(props.products.total ?? 0) > 0"
                :current-page="props.products.current_page"
                :last-page="props.products.last_page"
                :from="props.products.from ?? 0"
                :to="props.products.to ?? 0"
                :total="props.products.total ?? 0"
                :per-page="Number(filters.per_page)"
                :per-page-options="[10, 15, 25, 50, 100]"
                @page="changePage"
                @per-page="changePerPage"
            />
        </section>

        <div v-if="selected" class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/50 p-4" @click.self="selected = null">
            <section class="max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-md bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
                    <div>
                        <h2 class="two-line-title max-w-2xl text-base font-bold text-zinc-950">{{ selected.title }}</h2>
                        <p class="text-xs text-zinc-500">{{ selected.store?.name }}</p>
                    </div>
                    <button class="btn btn-secondary" type="button" @click="selected = null"><X class="size-4" /></button>
                </div>

                <div class="grid max-h-[calc(90vh-73px)] gap-5 overflow-y-auto p-5 lg:grid-cols-[260px_1fr]">
                    <div class="space-y-4">
                        <img
                            v-if="selected.image_url"
                            :src="selected.image_url"
                            :alt="selected.title"
                            class="aspect-square w-full rounded-md border border-zinc-200 object-cover"
                        />
                        <div v-else class="grid aspect-square w-full place-items-center rounded-md border border-dashed border-zinc-300 text-sm text-zinc-400">No image synced</div>

                        <a v-if="productUrl(selected)" class="btn btn-secondary w-full justify-center" :href="productUrl(selected)" target="_blank" rel="noreferrer">
                            <ExternalLink class="size-4" />
                            View product on website
                        </a>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-md bg-zinc-100 p-3">
                                <div class="text-zinc-500">Status</div>
                                <div class="font-semibold text-zinc-950">{{ selected.status || 'unknown' }}</div>
                            </div>
                            <div class="rounded-md bg-zinc-100 p-3">
                                <div class="text-zinc-500">Vendor</div>
                                <div class="font-semibold text-zinc-950">{{ selected.vendor || '-' }}</div>
                            </div>
                            <div class="rounded-md bg-zinc-100 p-3">
                                <div class="text-zinc-500">Type</div>
                                <div class="font-semibold text-zinc-950">{{ selected.product_type || '-' }}</div>
                            </div>
                            <div class="rounded-md bg-zinc-100 p-3">
                                <div class="text-zinc-500">Published</div>
                                <div class="font-semibold text-zinc-950">{{ selected.published_at ? new Date(selected.published_at).toLocaleDateString() : '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <div class="mb-2 flex items-center gap-2">
                                <h3 class="text-sm font-bold text-zinc-950">{{ hasGeneratedDraft ? 'Original Shopify content' : 'Current Shopify content' }}</h3>
                                <InfoHint text="This is the live content currently synced from Shopify. It stays here as a reference while you review the AI version." />
                            </div>
                            <div class="rounded-md border border-zinc-200 p-4 text-zinc-700">
                                <div class="text-sm font-semibold text-zinc-950">{{ originalContent?.title || selected.title }}</div>
                                <div class="prose prose-sm mt-3 max-w-none text-zinc-700" v-html="selected.description || plainDescription(selected.description)" />
                            </div>
                        </div>

                        <section class="rounded-md border border-teal-200 bg-teal-50/50 p-4">
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-bold text-zinc-950">{{ hasGeneratedDraft ? 'Review your AI version' : 'Generate AI product content' }}</h3>
                                        <InfoHint text="Generate a stronger product title and description, review the result, then decide when to push it live to Shopify." />
                                    </div>
                                    <p class="mt-1 text-xs text-zinc-600">
                                        {{ hasGeneratedDraft
                                            ? 'The AI version is now the working copy below. Edit it if needed, undo it, or push it live to Shopify.'
                                            : 'Start from your current product details, generate a better version, then review it before anything goes live.' }}
                                    </p>
                                </div>
                                <div class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-xs font-semibold text-zinc-700">
                                    <Coins class="size-4 text-teal-700" />
                                    {{ selectedStyleCost }} credits
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label>{{ hasGeneratedDraft ? 'AI title to review' : 'Current product title' }}</label>
                                    <input v-if="hasGeneratedDraft" v-model="contentForm.generated_title" />
                                    <input v-else v-model="contentForm.base_title" />
                                </div>
                                <div>
                                    <label>Description style</label>
                                    <select v-model="contentForm.description_style">
                                        <option value="short">Short</option>
                                        <option value="balanced">Balanced</option>
                                        <option value="bullets">Bullet points</option>
                                        <option value="long">Long description</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label>{{ hasGeneratedDraft ? 'AI description to review' : 'Current product details' }}</label>
                                <textarea
                                    v-if="hasGeneratedDraft"
                                    v-model="contentForm.generated_description"
                                    class="min-h-36"
                                    placeholder="Review the AI version here before pushing it live."
                                />
                                <textarea
                                    v-else
                                    v-model="contentForm.base_description"
                                    class="min-h-28"
                                    placeholder="Example: 925 silver hoop earrings, lightweight, daily wear, gift box included if true..."
                                />
                            </div>

                            <div class="mt-3 rounded-md border border-zinc-200 bg-white p-3 text-sm">
                                <div class="flex justify-between gap-3">
                                    <span class="text-zinc-500">Credits remaining</span>
                                    <span class="font-semibold text-zinc-950">{{ creditBalance.toLocaleString() }}</span>
                                </div>
                                <p v-if="!hasEnoughCredits" class="mt-2 text-xs font-semibold text-rose-700">Not enough credits for this product generation.</p>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button class="btn btn-primary" type="button" :disabled="generatingContent || !hasEnoughCredits" @click="generateContent">
                                    <LoaderCircle v-if="generatingContent" class="size-4 animate-spin" />
                                    <Sparkles v-else class="size-4" />
                                    {{ hasGeneratedDraft ? 'Generate another version' : 'Generate title and description' }}
                                </button>
                                <button v-if="hasGeneratedDraft" class="btn btn-secondary" type="button" :disabled="pushingContent" @click="restoreOriginalContent">
                                    <Undo2 class="size-4" />
                                    Undo AI version
                                </button>
                                <button class="btn btn-secondary" type="button" :disabled="pushingContent || !contentForm.generated_title || !contentForm.generated_description" @click="pushContent(false)">
                                    <LoaderCircle v-if="pushingContent" class="size-4 animate-spin" />
                                    <UploadCloud v-else class="size-4" />
                                    Push to Shopify
                                </button>
                                <button v-if="isDraftProduct(selected)" class="btn btn-primary" type="button" :disabled="pushingContent || !contentForm.generated_title || !contentForm.generated_description" @click="pushContent(true)">
                                    <LoaderCircle v-if="pushingContent" class="size-4 animate-spin" />
                                    <UploadCloud v-else class="size-4" />
                                    Push & publish
                                </button>
                            </div>

                            <div v-if="contentError" class="mt-3 rounded-md border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800">{{ contentError }}</div>
                            <div v-if="contentStatus" class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ contentStatus }}</div>

                            <div v-if="hasGeneratedDraft" class="mt-5 space-y-4">
                                <div class="rounded-md border border-white/80 bg-white p-3 text-xs text-zinc-600">
                                    This AI version is not live yet. It only updates Shopify after you click <span class="font-semibold text-zinc-950">Push to Shopify</span>.
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label>SEO title to push</label>
                                        <input v-model="contentForm.generated_seo_title" />
                                    </div>
                                    <div>
                                        <label>SEO description to push</label>
                                        <textarea v-model="contentForm.generated_seo_description" class="min-h-20" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <h3 class="mb-2 text-sm font-bold text-zinc-950">SEO Title</h3>
                                <p class="rounded-md border border-zinc-200 p-3 text-sm text-zinc-700">{{ selected.seo_title || 'No SEO title synced.' }}</p>
                            </div>
                            <div>
                                <h3 class="mb-2 text-sm font-bold text-zinc-950">SEO Description</h3>
                                <p class="rounded-md border border-zinc-200 p-3 text-sm text-zinc-700">{{ selected.seo_description || 'No SEO description synced.' }}</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 text-sm font-bold text-zinc-950">Tags</h3>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="tag in selected.tags || []" :key="tag" class="badge badge-draft">{{ tag }}</span>
                                <span v-if="!selected.tags?.length" class="text-sm text-zinc-500">No tags synced.</span>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 text-sm font-bold text-zinc-950">Collections</h3>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="collection in selected.collections || []" :key="collection.id || collection.handle" class="badge badge-approved">{{ collection.title }}</span>
                                <span v-if="!selected.collections?.length" class="text-sm text-zinc-500">No collections synced.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
