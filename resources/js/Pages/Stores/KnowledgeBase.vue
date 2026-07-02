<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, DatabaseZap, LoaderCircle, Save } from 'lucide-vue-next';

const props = defineProps({
    store: Object,
});

const knowledge = computed(() => props.store.knowledge_base ?? {});
const form = useForm({
    summary: knowledge.value.summary ?? '',
    editable_notes: knowledge.value.editable_notes ?? '',
});

const generate = () => router.post(`/stores/${props.store.id}/knowledge-base`, {}, { preserveScroll: true });
const save = () => form.patch(`/stores/${props.store.id}/knowledge-base`, { preserveScroll: true });

const progress = computed(() => {
    if (knowledge.value.status === 'completed') return 100;
    if (knowledge.value.status === 'running') return 65;
    if (knowledge.value.status === 'failed') return 100;
    return 0;
});

const jsonList = (value) => {
    if (!value) return [];
    if (Array.isArray(value)) return value;
    return Object.entries(value).map(([key, item]) => `${key}: ${Array.isArray(item) ? item.join(', ') : item}`);
};
</script>

<template>
    <Head :title="`${props.store.name} Knowledge Base`" />
    <AppLayout>
        <template #title>Store Knowledge Base</template>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/stores" class="btn btn-secondary"><ArrowLeft class="size-4" />Store</Link>
            <button class="btn btn-primary" type="button" @click="generate">
                <DatabaseZap class="size-4" />
                Build knowledge base
            </button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[360px_1fr]">
            <section class="space-y-4">
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="text-sm font-bold text-zinc-950">{{ props.store.name }}</h2>
                        <span class="badge" :class="`badge-${knowledge.status || 'pending'}`">{{ knowledge.status || 'pending' }}</span>
                    </div>
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="rounded-md bg-zinc-100 p-3"><div class="font-bold text-zinc-950">{{ props.store.products_count }}</div><div class="text-xs text-zinc-500">Products</div></div>
                            <div class="rounded-md bg-zinc-100 p-3"><div class="font-bold text-zinc-950">{{ props.store.collections_count }}</div><div class="text-xs text-zinc-500">Collections</div></div>
                            <div class="rounded-md bg-zinc-100 p-3"><div class="font-bold text-zinc-950">{{ props.store.pages_count }}</div><div class="text-xs text-zinc-500">Pages</div></div>
                            <div class="rounded-md bg-zinc-100 p-3"><div class="font-bold text-zinc-950">{{ props.store.existing_blogs_count }}</div><div class="text-xs text-zinc-500">Existing blogs</div></div>
                        </div>

                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="flex items-center gap-2 text-zinc-600">
                                    <LoaderCircle v-if="knowledge.status === 'running'" class="size-4 animate-spin text-teal-700" />
                                    Knowledge readiness
                                </span>
                                <span class="font-semibold">{{ progress }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-100">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="knowledge.status === 'failed' ? 'bg-rose-600' : 'bg-teal-700'"
                                    :style="{ width: `${progress}%` }"
                                />
                            </div>
                            <p v-if="knowledge.error_message" class="mt-2 text-xs text-rose-700">{{ knowledge.error_message }}</p>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">Synced Pages</h2></div>
                    <div class="panel-body space-y-3">
                        <a
                            v-for="page in props.store.pages"
                            :key="page.id"
                            :href="page.url"
                            target="_blank"
                            rel="noreferrer"
                            class="block rounded-md border border-zinc-200 p-3 text-sm hover:border-teal-300"
                        >
                            <span class="font-semibold text-zinc-950">{{ page.title }}</span>
                            <span class="block text-xs text-zinc-500">{{ page.handle }}</span>
                        </a>
                        <p v-if="!props.store.pages.length" class="text-sm text-zinc-500">No Shopify pages synced yet.</p>
                    </div>
                </div>
            </section>

            <section class="space-y-6">
                <form class="panel" @submit.prevent="save">
                    <div class="panel-header">
                        <h2 class="text-sm font-bold text-zinc-950">Editable Knowledge</h2>
                        <button class="btn btn-primary" :disabled="form.processing" type="submit"><Save class="size-4" />Save</button>
                    </div>
                    <div class="panel-body space-y-4">
                        <div>
                            <label>Store summary</label>
                            <textarea v-model="form.summary" class="min-h-40" placeholder="Generate the knowledge base or write a store summary here." />
                        </div>
                        <div>
                            <label>Owner notes and corrections</label>
                            <textarea v-model="form.editable_notes" class="min-h-36" placeholder="Add facts the AI must respect: brand rules, target customers, product details, tone restrictions, shipping notes, etc." />
                        </div>
                    </div>
                </form>

                <div class="grid gap-4 md:grid-cols-2">
                    <section v-for="[label, value] in [
                        ['Brand profile', knowledge.brand_profile],
                        ['Audience profile', knowledge.audience_profile],
                        ['Product insights', knowledge.product_insights],
                        ['Collection insights', knowledge.collection_insights],
                        ['Content insights', knowledge.content_insights],
                        ['SEO opportunities', knowledge.seo_opportunities],
                    ]" :key="label" class="panel">
                        <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">{{ label }}</h3></div>
                        <div class="panel-body">
                            <ul class="list-disc space-y-2 pl-5 text-sm text-zinc-700">
                                <li v-for="item in jsonList(value)" :key="item">{{ item }}</li>
                                <li v-if="!jsonList(value).length" class="list-none pl-0 text-zinc-500">Not generated yet.</li>
                            </ul>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
