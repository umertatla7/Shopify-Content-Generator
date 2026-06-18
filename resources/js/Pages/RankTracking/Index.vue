<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    BarChart3,
    CheckCircle2,
    ExternalLink,
    Filter,
    KeyRound,
    LineChart,
    LoaderCircle,
    MousePointerClick,
    RefreshCw,
    Search,
    ShieldCheck,
    TrendingUp,
} from 'lucide-vue-next';

const props = defineProps({
    isConfigured: Boolean,
    connection: Object,
    properties: Array,
    selectedPropertyId: Number,
    stores: Array,
    filters: Object,
    summary: Object,
    topPages: Array,
    rankings: Object,
});

const syncingProperties = ref(false);
const syncingPerformance = ref(false);
const updatingProperty = ref(null);
const filters = ref({
    property_id: props.filters.property_id ?? props.selectedPropertyId ?? '',
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
    device: props.filters.device ?? '',
    country: props.filters.country ?? '',
    q: props.filters.q ?? '',
});

const selectedProperty = computed(() => props.properties.find((property) => property.id === Number(filters.value.property_id)) ?? props.properties[0] ?? null);
const connected = computed(() => props.connection && ['connected', 'failed'].includes(props.connection.status));
const hasRows = computed(() => props.rankings?.data?.length > 0);

const statCards = computed(() => [
    ['Tracked keywords', props.summary.tracked_keywords ?? 0, KeyRound, 'bg-teal-50 text-teal-800'],
    ['Clicks', props.summary.clicks ?? 0, MousePointerClick, 'bg-sky-50 text-sky-800'],
    ['Impressions', props.summary.impressions ?? 0, BarChart3, 'bg-indigo-50 text-indigo-800'],
    ['Avg position', props.summary.avg_position ?? 'N/A', TrendingUp, 'bg-amber-50 text-amber-800'],
]);

const formatNumber = (value) => new Intl.NumberFormat().format(Number(value ?? 0));
const formatDate = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString() : 'Not synced';
const formatDateTime = (value) => value ? new Date(value).toLocaleString() : 'Not synced';
const shortUrl = (url) => {
    if (!url) return 'No page';

    try {
        const parsed = new URL(url);
        return `${parsed.hostname}${parsed.pathname}`.replace(/\/$/, '');
    } catch {
        return url;
    }
};

const applyFilters = () => router.get('/rank-tracking', filters.value, { preserveState: true, preserveScroll: true });
const resetFilters = () => {
    filters.value = {
        property_id: props.selectedPropertyId ?? '',
        start_date: props.filters.start_date ?? '',
        end_date: props.filters.end_date ?? '',
        device: '',
        country: '',
        q: '',
    };
    router.get('/rank-tracking', filters.value, { preserveState: true, preserveScroll: true });
};

const syncProperties = () => {
    syncingProperties.value = true;
    router.post('/rank-tracking/search-console/properties/sync', {}, {
        preserveScroll: true,
        onFinish: () => syncingProperties.value = false,
    });
};

const syncPerformance = () => {
    syncingPerformance.value = true;
    router.post('/rank-tracking/search-console/sync', {
        property_id: filters.value.property_id,
        start_date: filters.value.start_date,
        end_date: filters.value.end_date,
    }, {
        preserveScroll: true,
        onFinish: () => syncingPerformance.value = false,
    });
};

const selectProperty = (property) => {
    updatingProperty.value = property.id;
    filters.value.property_id = property.id;
    router.patch(`/rank-tracking/search-console/properties/${property.id}`, {
        selected: true,
        shopify_store_id: property.shopify_store_id,
    }, {
        preserveScroll: true,
        onFinish: () => updatingProperty.value = null,
    });
};

const updatePropertyStore = (property, storeId) => {
    updatingProperty.value = property.id;
    router.patch(`/rank-tracking/search-console/properties/${property.id}`, {
        selected: property.selected,
        shopify_store_id: storeId || null,
    }, {
        preserveScroll: true,
        onFinish: () => updatingProperty.value = null,
    });
};
</script>

<template>
    <Head title="Rank Tracking" />
    <AppLayout>
        <template #title>Rank Tracking</template>

        <div class="space-y-5">
            <section class="panel overflow-hidden">
                <div class="panel-body grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
                    <div>
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">
                            <ShieldCheck class="size-4" />
                            Google Search Console integration
                        </div>
                        <h2 class="text-xl font-bold text-zinc-950">Track Google rankings for generated blogs and store keywords</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600">
                            Connect the customer’s verified Search Console property, then import clicks, impressions, CTR, and average Google position by query, page, device, and country.
                        </p>
                        <p class="mt-2 text-xs text-zinc-500">
                            Search Console is trusted Google data, but it is not live SERP scraping. Finalized rows usually appear with a short delay.
                        </p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Connection</p>
                                <h3 class="mt-1 text-lg font-bold text-zinc-950">
                                    {{ connected ? 'Connected' : 'Not connected' }}
                                </h3>
                                <p class="mt-1 text-sm text-zinc-600">
                                    {{ props.connection?.google_email || props.connection?.user?.email || 'Connect a Google account with Search Console access.' }}
                                </p>
                                <p v-if="props.connection?.last_synced_at" class="mt-1 text-xs text-zinc-500">Last sync: {{ formatDateTime(props.connection.last_synced_at) }}</p>
                            </div>
                            <span class="badge" :class="connected ? 'badge-connected' : 'badge-pending'">
                                {{ props.connection?.status || 'setup needed' }}
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a v-if="props.isConfigured" href="/search-console/connect" class="btn btn-primary">
                                <CheckCircle2 class="size-4" />
                                {{ connected ? 'Reconnect Google' : 'Connect Google' }}
                            </a>
                            <button v-if="connected" type="button" class="btn btn-secondary" :disabled="syncingProperties" @click="syncProperties">
                                <LoaderCircle v-if="syncingProperties" class="size-4 animate-spin" />
                                <RefreshCw v-else class="size-4" />
                                Sync properties
                            </button>
                        </div>

                        <div v-if="!props.isConfigured" class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Add Google OAuth credentials in `.env` before connecting Search Console.
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="props.properties.length" class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-950">Search Console properties</h2>
                        <p class="text-xs text-zinc-500">Select the verified property that belongs to this Shopify store.</p>
                    </div>
                    <button type="button" class="btn btn-primary" :disabled="syncingPerformance || !selectedProperty" @click="syncPerformance">
                        <LoaderCircle v-if="syncingPerformance" class="size-4 animate-spin" />
                        <LineChart v-else class="size-4" />
                        Sync ranking data
                    </button>
                </div>
                <div class="panel-body grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="property in props.properties"
                        :key="property.id"
                        class="rounded-lg border p-4"
                        :class="property.selected ? 'border-teal-600 bg-teal-50/50' : 'border-zinc-200 bg-white'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-zinc-950">{{ property.site_url }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ property.permission_level || 'Permission unknown' }}</p>
                                <p class="mt-1 text-xs text-zinc-500">Last imported: {{ formatDateTime(property.last_synced_at) }}</p>
                            </div>
                            <span v-if="property.selected" class="badge badge-connected">Selected</span>
                        </div>

                        <div class="mt-4">
                            <label>Mapped store</label>
                            <select :value="property.shopify_store_id || ''" class="mt-1" @change="updatePropertyStore(property, $event.target.value)">
                                <option value="">No store mapped</option>
                                <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                            </select>
                        </div>

                        <button type="button" class="btn btn-secondary mt-3 w-full" :disabled="updatingProperty === property.id" @click="selectProperty(property)">
                            <LoaderCircle v-if="updatingProperty === property.id" class="size-4 animate-spin" />
                            <CheckCircle2 v-else class="size-4" />
                            Use this property
                        </button>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="[label, value, Icon, color] in statCards" :key="label" class="panel p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ label }}</p>
                            <p class="mt-2 text-2xl font-bold text-zinc-950">{{ typeof value === 'number' ? formatNumber(value) : value }}</p>
                        </div>
                        <div class="grid size-10 place-items-center rounded-md" :class="color">
                            <component :is="Icon" class="size-5" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1fr_.8fr]">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-sm font-bold text-zinc-950">Keyword performance</h2>
                            <p class="text-xs text-zinc-500">Grouped by query, page, country, and device.</p>
                        </div>
                    </div>
                    <div class="panel-body border-b border-zinc-200">
                        <div class="grid gap-3 lg:grid-cols-[1.4fr_repeat(5,1fr)_auto]">
                            <div>
                                <label>Search</label>
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                                    <input v-model="filters.q" class="pl-9" placeholder="Keyword or page" @keyup.enter="applyFilters" />
                                </div>
                            </div>
                            <div>
                                <label>Property</label>
                                <select v-model="filters.property_id" @change="applyFilters">
                                    <option v-for="property in props.properties" :key="property.id" :value="property.id">{{ property.site_url }}</option>
                                </select>
                            </div>
                            <div>
                                <label>Device</label>
                                <select v-model="filters.device" @change="applyFilters">
                                    <option value="">All</option>
                                    <option value="DESKTOP">Desktop</option>
                                    <option value="MOBILE">Mobile</option>
                                    <option value="TABLET">Tablet</option>
                                </select>
                            </div>
                            <div>
                                <label>Country</label>
                                <input v-model="filters.country" placeholder="usa" @keyup.enter="applyFilters" />
                            </div>
                            <div>
                                <label>Start</label>
                                <input v-model="filters.start_date" type="date" />
                            </div>
                            <div>
                                <label>End</label>
                                <input v-model="filters.end_date" type="date" />
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="button" class="btn btn-primary" @click="applyFilters">
                                    <Filter class="size-4" />
                                </button>
                                <button type="button" class="btn btn-secondary" @click="resetFilters">Reset</button>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Keyword</th>
                                    <th>Page</th>
                                    <th>Device</th>
                                    <th>Country</th>
                                    <th>Clicks</th>
                                    <th>Impressions</th>
                                    <th>CTR</th>
                                    <th>Position</th>
                                    <th>Last seen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <tr v-for="row in props.rankings.data" :key="`${row.query}-${row.page}-${row.device}-${row.country}`">
                                    <td class="max-w-xs px-4 py-3">
                                        <p class="font-semibold text-zinc-950">{{ row.query }}</p>
                                    </td>
                                    <td class="max-w-sm px-4 py-3 text-zinc-600">
                                        <a v-if="row.page" :href="row.page" target="_blank" rel="noreferrer" class="inline-flex max-w-xs items-center gap-1 truncate text-teal-700 hover:text-teal-900">
                                            <span class="truncate">{{ shortUrl(row.page) }}</span>
                                            <ExternalLink class="size-3 shrink-0" />
                                        </a>
                                        <span v-else>No page</span>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600">{{ row.device || 'All' }}</td>
                                    <td class="px-4 py-3 text-zinc-600">{{ row.country || 'All' }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ formatNumber(row.clicks) }}</td>
                                    <td class="px-4 py-3">{{ formatNumber(row.impressions) }}</td>
                                    <td class="px-4 py-3">{{ row.ctr }}%</td>
                                    <td class="px-4 py-3 font-semibold">{{ row.position ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-zinc-600">{{ formatDate(row.last_seen) }}</td>
                                </tr>
                                <tr v-if="!hasRows">
                                    <td colspan="9" class="px-4 py-8 text-center text-zinc-500">
                                        Connect Google Search Console and sync ranking data to see keyword positions.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="props.rankings.links?.length > 3" class="flex flex-wrap gap-2 border-t border-zinc-200 p-4">
                        <Link
                            v-for="link in props.rankings.links"
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
                </div>

                <aside class="space-y-4">
                    <section class="panel">
                        <div class="panel-header">
                            <h2 class="text-sm font-bold text-zinc-950">Top query</h2>
                        </div>
                        <div class="panel-body">
                            <template v-if="props.summary.top_query">
                                <p class="text-lg font-bold text-zinc-950">{{ props.summary.top_query.query }}</p>
                                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-md bg-zinc-50 p-3">
                                        <p class="text-xs text-zinc-500">Clicks</p>
                                        <p class="font-bold">{{ formatNumber(props.summary.top_query.clicks) }}</p>
                                    </div>
                                    <div class="rounded-md bg-zinc-50 p-3">
                                        <p class="text-xs text-zinc-500">Impr.</p>
                                        <p class="font-bold">{{ formatNumber(props.summary.top_query.impressions) }}</p>
                                    </div>
                                    <div class="rounded-md bg-zinc-50 p-3">
                                        <p class="text-xs text-zinc-500">Pos.</p>
                                        <p class="font-bold">{{ props.summary.top_query.position ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </template>
                            <p v-else class="text-sm text-zinc-500">No Search Console data imported yet.</p>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2 class="text-sm font-bold text-zinc-950">Top pages</h2>
                        </div>
                        <div class="divide-y divide-zinc-100">
                            <div v-for="page in props.topPages" :key="page.page" class="p-4">
                                <a :href="page.page" target="_blank" rel="noreferrer" class="block truncate text-sm font-semibold text-teal-700 hover:text-teal-900">
                                    {{ shortUrl(page.page) }}
                                </a>
                                <p class="mt-1 text-xs text-zinc-500">
                                    {{ formatNumber(page.clicks) }} clicks · {{ formatNumber(page.impressions) }} impressions · position {{ page.position ?? 'N/A' }}
                                </p>
                            </div>
                            <div v-if="!props.topPages.length" class="p-4 text-sm text-zinc-500">No page data yet.</div>
                        </div>
                    </section>
                </aside>
            </section>
        </div>
    </AppLayout>
</template>
