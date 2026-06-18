<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    topics: Object,
    filters: Object,
});

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const apply = () => router.get('/admin/topics', filters, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Admin Topics" />
    <AppLayout>
        <template #title>All Topics</template>

        <section class="panel mb-6">
            <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">Topic Ideas</h2></div>
            <div class="panel-body grid gap-3 md:grid-cols-3">
                <div><label>Search</label><input v-model="filters.search" @keydown.enter="apply" /></div>
                <div><label>Status</label><input v-model="filters.status" @keydown.enter="apply" /></div>
                <div class="flex items-end"><button class="btn btn-primary w-full" @click="apply">Apply</button></div>
            </div>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Account</th><th>Store</th><th>Keyword</th><th>Intent</th><th>Score</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr v-for="topic in props.topics.data" :key="topic.id">
                            <td class="font-semibold text-zinc-950">{{ topic.title }}</td>
                            <td>{{ topic.account?.name }}</td>
                            <td>{{ topic.store?.name }}</td>
                            <td>{{ topic.primary_keyword ?? '-' }}</td>
                            <td>{{ topic.search_intent ?? '-' }}</td>
                            <td>{{ topic.opportunity_score ?? '-' }}</td>
                            <td><span class="badge" :class="`badge-${topic.status}`">{{ topic.status }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
