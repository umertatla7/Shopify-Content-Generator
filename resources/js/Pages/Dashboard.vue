<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowRight, Bot, Coins, Eye, FileText, Lightbulb, LockKeyhole, Search, Send, ShoppingBag, Sparkles, TriangleAlert } from 'lucide-vue-next';

const props = defineProps({
    stats: Object,
    credits: Object,
    billing: Object,
    stores: Array,
    blogs: Array,
    latestAnalysis: Object,
});

const page = usePage();
const planAccess = computed(() => page.props.auth?.plan_access ?? page.props.auth?.account?.plan_access ?? {});
const primaryStore = computed(() => props.stores?.[0] ?? null);

const metricCards = [
    ['Connected stores', 'stores', ShoppingBag, 'text-teal-700'],
    ['Synced products', 'products', Sparkles, 'text-sky-700'],
    ['Existing Shopify blogs', 'existing_blogs', FileText, 'text-indigo-700'],
    ['Generated topics', 'topics', Lightbulb, 'text-amber-700'],
    ['Generated blogs', 'blogs', FileText, 'text-zinc-800'],
    ['Published blogs', 'published', Send, 'text-emerald-700'],
    ['Failed blogs', 'failed', TriangleAlert, 'text-rose-700'],
];

const quickActions = computed(() => [
    {
        label: 'Sync store data',
        description: 'Pull products, collections, pages, and blogs into the workspace.',
        href: '/stores',
        icon: ShoppingBag,
        locked: false,
    },
    {
        label: 'Run store audit',
        description: planAccess.value.store_audit ? 'Check technical, SEO, and content gaps.' : 'Preview the audit workflow and upgrade path.',
        href: '/store-audit',
        icon: Search,
        locked: !planAccess.value.store_audit,
    },
    {
        label: 'Open AI Visibility',
        description: planAccess.value.ai_visibility ? 'Review prompt coverage and platform readiness.' : 'Preview how prompt readiness is presented.',
        href: '/ai-visibility',
        icon: Bot,
        locked: !planAccess.value.ai_visibility,
    },
    {
        label: 'Generate blog topics',
        description: planAccess.value.topics ? 'Start from store-aware topic suggestions.' : 'Preview topic generation access for higher plans.',
        href: '/topics',
        icon: Lightbulb,
        locked: !planAccess.value.topics,
    },
]);
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <template #title>Dashboard</template>

        <section class="panel mb-6 overflow-hidden">
            <div class="grid gap-6 bg-[linear-gradient(135deg,#0f766e_0%,#0b5f59_55%,#111827_100%)] p-5 text-white xl:grid-cols-[1.2fr_.8fr]">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/12 px-3 py-1 text-xs font-semibold text-white/90 ring-1 ring-white/10">
                        <Eye class="size-4" />
                        Client demo workspace
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="grid size-12 shrink-0 place-items-center rounded-xl bg-white/12 text-white ring-1 ring-white/10">
                            <Coins class="size-6" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white/70">Credits remaining</p>
                            <p class="mt-1 text-4xl font-bold">{{ props.credits.balance.toLocaleString() }}</p>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-white/75">
                                {{ primaryStore?.name ?? 'Your store' }} is ready to generate product copy, blog topics, and visibility reports from synced Shopify data.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/10 backdrop-blur-sm">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Estimated words left</div>
                            <div class="mt-2 text-xl font-bold">{{ props.credits.estimated_words_left.toLocaleString() }}</div>
                        </div>
                        <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/10 backdrop-blur-sm">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Current plan</div>
                            <div class="mt-2 text-xl font-bold capitalize">{{ props.billing.plan_key }}</div>
                        </div>
                        <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/10 backdrop-blur-sm">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Connected store</div>
                            <div class="mt-2 text-xl font-bold">{{ primaryStore?.name ?? 'Not connected' }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-4 text-zinc-950 shadow-lg">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-bold">Plan usage</div>
                            <div class="mt-1 text-sm text-zinc-500">A quick view of this month’s credit balance.</div>
                        </div>
                        <span class="badge bg-teal-50 text-teal-800">{{ props.billing.has_connected_store ? 'Store connected' : 'Store needed' }}</span>
                    </div>
                    <div class="mt-4 rounded-xl border border-zinc-200 p-4 text-sm">
                        <div class="flex justify-between gap-3"><span class="text-zinc-500">Plan credits</span><span class="font-semibold text-zinc-950">{{ props.credits.monthly_allowance.toLocaleString() }}</span></div>
                        <div class="mt-2 flex justify-between gap-3"><span class="text-zinc-500">Used</span><span class="font-semibold text-zinc-950">{{ props.credits.used.toLocaleString() }}</span></div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-100">
                            <div
                                class="h-full rounded-full bg-teal-700"
                                :style="{ width: `${props.credits.monthly_allowance ? Math.min(100, (props.credits.used / props.credits.monthly_allowance) * 100) : 0}%` }"
                            />
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Link href="/billing" class="btn btn-secondary">Manage plan</Link>
                            <Link href="/blogs" class="btn btn-primary">Open blogs</Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel mb-6">
            <div class="grid gap-4 p-4 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <div class="text-sm font-semibold text-zinc-950">Start here</div>
                    <p class="mt-1 text-sm text-zinc-600">
                        The cleanest demo flow is: sync store data, run a store audit, review AI Visibility, then generate topics and publish-ready blogs.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="badge bg-zinc-100 text-zinc-800">Plan: {{ props.billing.plan_key }}</span>
                    <span class="badge" :class="props.billing.has_connected_store ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                        {{ props.billing.has_connected_store ? 'Store connected' : 'Store needed for billing' }}
                    </span>
                </div>
            </div>
            <div class="grid gap-3 border-t border-zinc-200 p-4 md:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="action in quickActions"
                    :key="action.href"
                    :href="action.href"
                    class="rounded-xl border border-zinc-200 p-4 transition hover:border-teal-200 hover:bg-teal-50/40"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="grid size-10 place-items-center rounded-xl bg-zinc-100 text-zinc-700">
                            <component :is="action.icon" class="size-5" />
                        </div>
                        <LockKeyhole v-if="action.locked" class="size-4 text-zinc-400" />
                    </div>
                    <div class="mt-4 text-sm font-bold text-zinc-950">{{ action.label }}</div>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ action.description }}</p>
                    <div class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-teal-700">
                        Open
                        <ArrowRight class="size-4" />
                    </div>
                </Link>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="[label, key, Icon, color] in metricCards" :key="key" class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-zinc-500">{{ label }}</p>
                        <p class="mt-2 text-3xl font-bold text-zinc-950">{{ props.stats[key] ?? 0 }}</p>
                    </div>
                    <div class="grid size-10 place-items-center rounded-xl bg-zinc-100" :class="color">
                        <component :is="Icon" class="size-5" />
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_1.2fr]">
            <section class="panel overflow-hidden">
                <div class="panel-header">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-950">Store readiness</h2>
                        <p class="text-xs text-zinc-500">Keep this section clean in demos so the merchant immediately understands store status and what unlocks next.</p>
                    </div>
                    <Link href="/store-audit" class="btn btn-secondary">{{ planAccess.store_audit ? 'Open audit' : 'Preview' }}</Link>
                </div>
                <div class="panel-body space-y-3">
                    <template v-if="planAccess.store_audit">
                        <div v-if="!props.stores.length" class="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No stores connected.</div>
                        <div v-for="store in props.stores" :key="store.id" class="rounded-xl border border-zinc-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">{{ store.name }}</div>
                                    <div class="text-xs text-zinc-500">{{ store.shop_domain }}</div>
                                </div>
                                <span class="badge" :class="`badge-${store.status}`">{{ store.status }}</span>
                            </div>
                            <div class="mt-4 grid gap-2 text-center text-xs sm:grid-cols-4">
                                <div class="rounded-xl bg-zinc-100 p-3"><div class="font-bold text-zinc-950">{{ store.products_count }}</div><div class="mt-1 text-zinc-500">Products</div></div>
                                <div class="rounded-xl bg-zinc-100 p-3"><div class="font-bold text-zinc-950">{{ store.collections_count }}</div><div class="mt-1 text-zinc-500">Collections</div></div>
                                <div class="rounded-xl bg-zinc-100 p-3"><div class="font-bold text-zinc-950">{{ store.blogs_count }}</div><div class="mt-1 text-zinc-500">Blogs</div></div>
                                <div class="rounded-xl bg-zinc-100 p-3"><div class="font-bold text-zinc-950">{{ store.analyses_count }}</div><div class="mt-1 text-zinc-500">Analyses</div></div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2 text-sm font-bold text-zinc-950">
                                    <LockKeyhole class="size-4 text-amber-700" />
                                    Store Audit is locked on this package
                                </div>
                                <div class="mt-1 text-sm text-zinc-600">Upgrade to unlock real store analysis, technical signals, and synced audit summaries.</div>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-center text-xs sm:grid-cols-4 blur-[3px]">
                            <div class="rounded-xl bg-white p-3"><div class="font-bold text-zinc-950">624</div><div class="mt-1 text-zinc-500">Products</div></div>
                            <div class="rounded-xl bg-white p-3"><div class="font-bold text-zinc-950">18</div><div class="mt-1 text-zinc-500">Collections</div></div>
                            <div class="rounded-xl bg-white p-3"><div class="font-bold text-zinc-950">11</div><div class="mt-1 text-zinc-500">Pages</div></div>
                            <div class="rounded-xl bg-white p-3"><div class="font-bold text-zinc-950">83</div><div class="mt-1 text-zinc-500">Score</div></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="panel-header">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-950">Latest AI Store Analysis</h2>
                        <p class="text-xs text-zinc-500">Use this in demos to show the jump from raw store data into specific SEO and AEO opportunities.</p>
                    </div>
                    <Link href="/store-audit" class="btn btn-secondary">{{ planAccess.store_audit ? 'Analyze' : 'Preview' }}</Link>
                </div>
                <div class="panel-body">
                    <template v-if="planAccess.store_audit">
                        <div v-if="!props.latestAnalysis" class="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">Run store analysis after syncing Shopify data.</div>
                        <div v-else class="space-y-4">
                            <div>
                                <div class="text-xs font-semibold uppercase text-zinc-500">{{ props.latestAnalysis.store?.name }}</div>
                                <div class="mt-1 text-lg font-bold text-zinc-950">{{ props.latestAnalysis.niche ?? 'Niche pending' }}</div>
                                <p class="mt-1 text-sm text-zinc-600">{{ props.latestAnalysis.target_audience ?? 'Audience summary pending.' }}</p>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-xl border border-zinc-200 p-3">
                                    <div class="text-xs font-semibold uppercase text-zinc-500">Keywords</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span v-for="keyword in (props.latestAnalysis.suggested_keywords ?? []).slice(0, 8)" :key="keyword" class="badge bg-teal-50 text-teal-800">{{ keyword }}</span>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-zinc-200 p-3">
                                    <div class="text-xs font-semibold uppercase text-zinc-500">Content gaps</div>
                                    <ul class="mt-2 space-y-1 text-sm text-zinc-700">
                                        <li v-for="gap in (props.latestAnalysis.content_gaps ?? []).slice(0, 5)" :key="gap">{{ gap }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="space-y-4 blur-[4px]">
                        <div>
                            <div class="text-xs font-semibold uppercase text-zinc-500">Demo store</div>
                            <div class="mt-1 text-lg font-bold text-zinc-950">Luxury jewelry gifting and comparison content</div>
                            <p class="mt-1 text-sm text-zinc-600">Audience summary and buyer signals unlock after package upgrade.</p>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-xl border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Keywords</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span v-for="keyword in ['lab diamond rings', 'gift guide', 'engagement styles', 'bridal sets']" :key="keyword" class="badge bg-teal-50 text-teal-800">{{ keyword }}</span>
                                </div>
                            </div>
                            <div class="rounded-xl border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Content gaps</div>
                                <ul class="mt-2 space-y-1 text-sm text-zinc-700">
                                    <li v-for="gap in ['Buying guide coverage', 'FAQ answer blocks', 'Policy clarity', 'Collection intros']" :key="gap">{{ gap }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="mt-6">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-950">Recent blogs</h2>
                        <p class="text-xs text-zinc-500">A simple publishing view that works well in demos and makes approval status obvious.</p>
                    </div>
                    <Link href="/blogs" class="btn btn-secondary">Open</Link>
                </div>
                <div class="divide-y divide-zinc-100">
                    <article v-for="blog in props.blogs" :key="blog.id" class="grid gap-3 p-4 lg:grid-cols-[1.4fr_.8fr_.8fr_.7fr_.7fr] lg:items-start">
                        <div>
                            <Link :href="`/blogs/${blog.id}/edit`" class="font-semibold text-zinc-950 hover:text-teal-700">{{ blog.title }}</Link>
                            <p class="mt-1 text-sm text-zinc-500">{{ blog.store?.name ?? 'No store' }}</p>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Keyword</div>
                            <div class="mt-1 text-sm text-zinc-700">{{ blog.primary_keyword ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">SEO score</div>
                            <div class="mt-1 text-sm font-semibold text-zinc-950">{{ blog.seo_score ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Image</div>
                            <div class="mt-1 text-sm text-zinc-700">{{ blog.featured_image_idea ? 'Briefed' : 'Pending' }}</div>
                        </div>
                        <div class="flex items-start lg:justify-end">
                            <span class="badge" :class="`badge-${blog.status}`">{{ blog.status.replace('_', ' ') }}</span>
                        </div>
                    </article>
                    <div v-if="!props.blogs.length" class="p-4 text-sm text-zinc-500">No blogs generated.</div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
