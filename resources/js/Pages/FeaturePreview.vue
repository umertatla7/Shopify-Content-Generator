<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { LockKeyhole, Sparkles } from 'lucide-vue-next';

defineProps({
    title: String,
    description: String,
    highlights: Array,
    metrics: Array,
});
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <template #title>{{ title }}</template>

        <div class="space-y-6">
            <section class="panel overflow-hidden">
                <div class="grid gap-4 p-5 lg:grid-cols-[1.15fr_0.85fr] lg:items-start">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                            <LockKeyhole class="size-4" />
                            Upgrade required
                        </div>
                        <h2 class="mt-3 text-2xl font-bold text-zinc-950">{{ title }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600">{{ description }}</p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="item in highlights" :key="item" class="badge bg-zinc-100 text-zinc-700">{{ item }}</span>
                        </div>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        <div class="flex items-center gap-2 text-sm font-bold text-zinc-950">
                            <Sparkles class="size-4 text-teal-700" />
                            Higher plans unlock this module
                        </div>
                        <p class="mt-2 text-sm text-zinc-600">
                            This preview stays generic so your plan does not spend AI or report credits until the module is enabled.
                        </p>
                        <div class="mt-4">
                            <Link href="/billing" class="btn btn-primary w-full">Upgrade package</Link>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <div
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="panel p-4 blur-[3px]"
                >
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ metric.label }}</div>
                    <div class="mt-2 text-3xl font-bold text-zinc-950">{{ metric.value }}</div>
                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="border-b border-zinc-200 px-5 py-4">
                    <h3 class="text-sm font-bold text-zinc-950">Module preview</h3>
                    <p class="mt-1 text-sm text-zinc-500">This is intentionally blurred and generic until the plan unlocks the module.</p>
                </div>
                <div class="grid gap-4 p-5 lg:grid-cols-2">
                    <div class="space-y-3 blur-[4px]">
                        <div class="rounded-md border border-zinc-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase text-zinc-500">Signal</div>
                            <div class="mt-2 text-lg font-bold text-zinc-950">High-value recommendation summary</div>
                            <p class="mt-2 text-sm text-zinc-600">This example panel shows what customers unlock after upgrading the package.</p>
                        </div>
                        <div class="rounded-md border border-zinc-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase text-zinc-500">Opportunity</div>
                            <div class="mt-2 h-3 w-4/5 rounded bg-zinc-200" />
                            <div class="mt-3 h-3 w-3/5 rounded bg-zinc-200" />
                            <div class="mt-3 h-3 w-2/3 rounded bg-zinc-200" />
                        </div>
                    </div>

                    <div class="space-y-3 blur-[4px]">
                        <div class="rounded-md border border-zinc-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase text-zinc-500">Trend</div>
                            <div class="mt-4 grid grid-cols-7 gap-2">
                                <div v-for="bar in 7" :key="bar" class="flex items-end">
                                    <div class="w-full rounded-t bg-teal-200" :style="{ height: `${28 + (bar * 10)}px` }" />
                                </div>
                            </div>
                        </div>
                        <div class="rounded-md border border-zinc-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase text-zinc-500">Comparisons</div>
                            <div class="mt-3 h-3 w-full rounded bg-zinc-200" />
                            <div class="mt-3 h-3 w-5/6 rounded bg-zinc-200" />
                            <div class="mt-3 h-3 w-2/3 rounded bg-zinc-200" />
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
