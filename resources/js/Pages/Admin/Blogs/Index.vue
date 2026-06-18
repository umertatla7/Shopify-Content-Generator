<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    blogs: Object,
    filters: Object,
    statuses: Array,
});

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const apply = () => router.get('/admin/blogs', filters, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Admin Blogs" />
    <AppLayout>
        <template #title>All Blogs</template>

        <section class="panel mb-6">
            <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">Generated Content</h2></div>
            <div class="panel-body grid gap-3 md:grid-cols-3">
                <div><label>Search</label><input v-model="filters.search" @keydown.enter="apply" /></div>
                <div>
                    <label>Status</label>
                    <select v-model="filters.status" @change="apply">
                        <option value="">All</option>
                        <option v-for="status in props.statuses" :key="status" :value="status">{{ status.replace('_', ' ') }}</option>
                    </select>
                </div>
                <div class="flex items-end"><button class="btn btn-primary w-full" @click="apply">Apply</button></div>
            </div>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Account</th><th>Store</th><th>Keyword</th><th>SEO</th><th>Status</th><th>Assignee</th></tr></thead>
                    <tbody>
                        <tr v-for="blog in props.blogs.data" :key="blog.id">
                            <td><Link :href="`/blogs/${blog.id}/edit`" class="font-semibold text-zinc-950 hover:text-teal-700">{{ blog.title }}</Link></td>
                            <td>{{ blog.account?.name }}</td>
                            <td>{{ blog.store?.name }}</td>
                            <td>{{ blog.primary_keyword ?? '-' }}</td>
                            <td>{{ blog.seo_score ?? '-' }}</td>
                            <td><span class="badge" :class="`badge-${blog.status}`">{{ blog.status.replace('_', ' ') }}</span></td>
                            <td>{{ blog.assignee?.name ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
