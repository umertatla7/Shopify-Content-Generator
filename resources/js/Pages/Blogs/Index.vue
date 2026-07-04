<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircle, Edit3, RefreshCw, Send, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    blogs: Object,
    stores: Array,
    filters: Object,
    statuses: Array,
});

const selected = ref([]);
const filters = reactive({
    store: props.filters.store ?? '',
    status: props.filters.status ?? '',
    keyword: props.filters.keyword ?? '',
    created_from: props.filters.created_from ?? '',
    scheduled_from: props.filters.scheduled_from ?? '',
    published_from: props.filters.published_from ?? '',
});

const applyFilters = () => router.get('/blogs', filters, { preserveState: true, preserveScroll: true });
const approve = (blog) => router.post(`/blogs/${blog.id}/approve`, {}, { preserveScroll: true });
const publish = (blog) => router.post(`/blogs/${blog.id}/publish`, {}, { preserveScroll: true });
const syncFromShopify = (blog) => router.post(`/blogs/${blog.id}/sync-shopify`, {}, { preserveScroll: true });
const syncCatalog = () => router.post('/blogs/sync-shopify', {
    store_id: filters.store || null,
}, { preserveScroll: true });
const canPublish = (blog) => ['approved', 'scheduled', 'published'].includes(blog.status);
const formatStoreTime = (value, blog) => {
    if (!value) return '-';

    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: blog.store?.timezone || undefined,
        }).format(new Date(value));
    } catch {
        return new Date(value).toLocaleString();
    }
};
const remove = (blog) => {
    if (confirm(`Delete ${blog.title}?`)) router.delete(`/blogs/${blog.id}`, { preserveScroll: true });
};
const publishSelected = () => router.post('/blogs/publish-selected', { blog_ids: selected.value }, { preserveScroll: true });
const publishApproved = () => router.post('/blogs/publish-approved', {}, { preserveScroll: true });
</script>

<template>
    <Head title="Blogs" />
    <AppLayout>
        <template #title>Blogs</template>

        <section class="panel mb-6">
            <div class="panel-header">
                <h2 class="text-sm font-bold text-zinc-950">Filters</h2>
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-secondary" @click="syncCatalog"><RefreshCw class="size-4" />Sync Shopify</button>
                    <button class="btn btn-secondary" :disabled="!selected.length" @click="publishSelected"><Send class="size-4" />Selected</button>
                    <button class="btn btn-primary" @click="publishApproved"><Send class="size-4" />Approved</button>
                </div>
            </div>
            <div class="panel-body grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div>
                    <label>Store</label>
                    <select v-model="filters.store" @change="applyFilters">
                        <option value="">All</option>
                        <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select v-model="filters.status" @change="applyFilters">
                        <option value="">All</option>
                        <option v-for="status in props.statuses" :key="status" :value="status">{{ status.replace('_', ' ') }}</option>
                    </select>
                </div>
                <div>
                    <label>Keyword</label>
                    <input v-model="filters.keyword" @keydown.enter="applyFilters" />
                </div>
                <div>
                    <label>Created after</label>
                    <input v-model="filters.created_from" type="date" @change="applyFilters" />
                </div>
                <div>
                    <label>Scheduled after</label>
                    <input v-model="filters.scheduled_from" type="date" @change="applyFilters" />
                </div>
                <div>
                    <label>Published after</label>
                    <input v-model="filters.published_from" type="date" @change="applyFilters" />
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10"></th>
                            <th>Title</th>
                            <th>Store</th>
                            <th>Status</th>
                            <th>SEO score</th>
                            <th>Scheduled date</th>
                            <th>Published date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="blog in props.blogs.data" :key="blog.id">
                            <td><input v-model="selected" type="checkbox" :value="blog.id" :disabled="!canPublish(blog)" class="size-4 rounded border-zinc-300 p-0" /></td>
                            <td>
                                <Link
                                    :href="`/blogs/${blog.id}/edit`"
                                    :title="blog.title"
                                    class="two-line-title min-w-64 max-w-96 font-semibold text-zinc-950 hover:text-teal-700"
                                >
                                    {{ blog.title }}
                                </Link>
                                <div class="mt-1 max-w-96 truncate text-xs text-zinc-500">{{ blog.primary_keyword ?? 'No primary keyword' }}</div>
                            </td>
                            <td>{{ blog.store?.name }}</td>
                            <td><span class="badge" :class="`badge-${blog.status}`">{{ blog.status.replace('_', ' ') }}</span></td>
                            <td>{{ blog.seo_score ?? '-' }}</td>
                            <td>
                                <div>{{ formatStoreTime(blog.scheduled_at, blog) }}</div>
                                <div v-if="blog.scheduled_at && blog.store?.timezone" class="text-xs text-zinc-500">{{ blog.store.timezone }}</div>
                            </td>
                            <td>{{ formatStoreTime(blog.published_at, blog) }}</td>
                            <td>
                                <div class="flex gap-2">
                                    <Link class="btn btn-secondary" title="Edit" :href="`/blogs/${blog.id}/edit`"><Edit3 class="size-4" /></Link>
                                    <button class="btn btn-secondary" title="Approve" @click="approve(blog)"><CheckCircle class="size-4" /></button>
                                    <button class="btn btn-primary" :title="blog.status === 'published' ? 'Update Shopify' : 'Publish'" :disabled="!canPublish(blog)" @click="publish(blog)"><Send class="size-4" /></button>
                                    <button v-if="blog.shopify_article_id" class="btn btn-secondary" title="Sync from Shopify" @click="syncFromShopify(blog)"><RefreshCw class="size-4" /></button>
                                    <button class="btn btn-danger" title="Delete" @click="remove(blog)"><Trash2 class="size-4" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!props.blogs.data.length">
                            <td colspan="8" class="text-zinc-500">No blogs match the current filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
