<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Coins, FileText, Lightbulb, LockKeyhole, Send, ShoppingBag, Sparkles, TriangleAlert } from 'lucide-vue-next';

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

const metricCards = [
    ['Connected stores', 'stores', ShoppingBag, 'text-teal-700'],
    ['Synced products', 'products', Sparkles, 'text-sky-700'],
    ['Existing Shopify blogs', 'existing_blogs', FileText, 'text-indigo-700'],
    ['Generated topics', 'topics', Lightbulb, 'text-amber-700'],
    ['Generated blogs', 'blogs', FileText, 'text-zinc-800'],
    ['Published blogs', 'published', Send, 'text-emerald-700'],
    ['Failed blogs', 'failed', TriangleAlert, 'text-rose-700'],
];
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <template #title>Dashboard</template>

        <section class="panel mb-6 overflow-hidden">
            <div class="grid gap-4 p-4 md:grid-cols-[1fr_auto] md:items-center">
                <div class="flex items-center gap-4">
                    <div class="grid size-12 place-items-center rounded-md bg-teal-50 text-teal-700">
                        <Coins class="size-6" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-zinc-500">Credits remaining</p>
                        <p class="mt-1 text-3xl font-bold text-zinc-950">{{ props.credits.balance.toLocaleString() }}</p>
                        <p class="mt-1 text-sm text-zinc-500">Estimated words left: {{ props.credits.estimated_words_left.toLocaleString() }}</p>
                    </div>
                </div>
                <div class="min-w-56 rounded-md border border-zinc-200 p-3 text-sm">
                    <div class="flex justify-between gap-3"><span class="text-zinc-500">Plan credits</span><span class="font-semibold text-zinc-950">{{ props.credits.monthly_allowance.toLocaleString() }}</span></div>
                    <div class="mt-2 flex justify-between gap-3"><span class="text-zinc-500">Used</span><span class="font-semibold text-zinc-950">{{ props.credits.used.toLocaleString() }}</span></div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-100">
                        <div
                            class="h-full rounded-full bg-teal-700"
                            :style="{ width: `${props.credits.monthly_allowance ? Math.min(100, (props.credits.used / props.credits.monthly_allowance) * 100) : 0}%` }"
                        />
                    </div>
                </div>
            </div>
        </section>

        <section class="panel mb-6">
            <div class="grid gap-4 p-4 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <div class="text-sm font-semibold text-zinc-950">Public app rollout track</div>
                    <p class="mt-1 text-sm text-zinc-600">
                        Billing now has a Shopify-facing setup path. Next we’ll keep tightening install, OAuth, and embedded admin behavior for App Store review.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="badge bg-zinc-100 text-zinc-800">Plan: {{ props.billing.plan_key }}</span>
                    <span class="badge" :class="props.billing.has_connected_store ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                        {{ props.billing.has_connected_store ? 'Store connected' : 'Store needed for paid billing' }}
                    </span>
                    <Link href="/billing" class="btn btn-secondary">Open billing</Link>
                </div>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="[label, key, Icon, color] in metricCards" :key="key" class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-zinc-500">{{ label }}</p>
                        <p class="mt-2 text-3xl font-bold text-zinc-950">{{ props.stats[key] ?? 0 }}</p>
                    </div>
                    <div class="grid size-10 place-items-center rounded-md bg-zinc-100" :class="color">
                        <component :is="Icon" class="size-5" />
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_1.2fr]">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Store Readiness</h2>
                    <Link href="/store-audit" class="btn btn-secondary">{{ planAccess.store_audit ? 'Open audit' : 'Preview' }}</Link>
                </div>
                <div class="panel-body space-y-3">
                    <template v-if="planAccess.store_audit">
                        <div v-if="!props.stores.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No stores connected.</div>
                        <div v-for="store in props.stores" :key="store.id" class="rounded-md border border-zinc-200 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">{{ store.name }}</div>
                                    <div class="text-xs text-zinc-500">{{ store.shop_domain }}</div>
                                </div>
                                <span class="badge" :class="`badge-${store.status}`">{{ store.status }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs">
                                <div class="rounded-md bg-zinc-100 p-2"><div class="font-bold text-zinc-950">{{ store.products_count }}</div><div class="text-zinc-500">Products</div></div>
                                <div class="rounded-md bg-zinc-100 p-2"><div class="font-bold text-zinc-950">{{ store.collections_count }}</div><div class="text-zinc-500">Collections</div></div>
                                <div class="rounded-md bg-zinc-100 p-2"><div class="font-bold text-zinc-950">{{ store.blogs_count }}</div><div class="text-zinc-500">Blogs</div></div>
                                <div class="rounded-md bg-zinc-100 p-2"><div class="font-bold text-zinc-950">{{ store.analyses_count }}</div><div class="text-zinc-500">Analyses</div></div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2 text-sm font-bold text-zinc-950">
                                    <LockKeyhole class="size-4 text-amber-700" />
                                    Store Audit is locked on this package
                                </div>
                                <div class="mt-1 text-sm text-zinc-600">Upgrade to unlock real store analysis, technical signals, and synced audit summaries.</div>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-4 gap-2 text-center text-xs blur-[3px]">
                            <div class="rounded-md bg-white p-2"><div class="font-bold text-zinc-950">624</div><div class="text-zinc-500">Products</div></div>
                            <div class="rounded-md bg-white p-2"><div class="font-bold text-zinc-950">18</div><div class="text-zinc-500">Collections</div></div>
                            <div class="rounded-md bg-white p-2"><div class="font-bold text-zinc-950">11</div><div class="text-zinc-500">Pages</div></div>
                            <div class="rounded-md bg-white p-2"><div class="font-bold text-zinc-950">83</div><div class="text-zinc-500">Score</div></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="text-sm font-bold text-zinc-950">Latest AI Store Analysis</h2>
                    <Link href="/store-audit" class="btn btn-secondary">{{ planAccess.store_audit ? 'Analyze' : 'Preview' }}</Link>
                </div>
                <div class="panel-body">
                    <template v-if="planAccess.store_audit">
                        <div v-if="!props.latestAnalysis" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">Run store analysis after syncing Shopify data.</div>
                        <div v-else class="space-y-4">
                            <div>
                                <div class="text-xs font-semibold uppercase text-zinc-500">{{ props.latestAnalysis.store?.name }}</div>
                                <div class="mt-1 text-lg font-bold text-zinc-950">{{ props.latestAnalysis.niche ?? 'Niche pending' }}</div>
                                <p class="mt-1 text-sm text-zinc-600">{{ props.latestAnalysis.target_audience ?? 'Audience summary pending.' }}</p>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-md border border-zinc-200 p-3">
                                    <div class="text-xs font-semibold uppercase text-zinc-500">Keywords</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span v-for="keyword in (props.latestAnalysis.suggested_keywords ?? []).slice(0, 8)" :key="keyword" class="badge bg-teal-50 text-teal-800">{{ keyword }}</span>
                                    </div>
                                </div>
                                <div class="rounded-md border border-zinc-200 p-3">
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
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs font-semibold uppercase text-zinc-500">Keywords</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span v-for="keyword in ['lab diamond rings', 'gift guide', 'engagement styles', 'bridal sets']" :key="keyword" class="badge bg-teal-50 text-teal-800">{{ keyword }}</span>
                                </div>
                            </div>
                            <div class="rounded-md border border-zinc-200 p-3">
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
                    <h2 class="text-sm font-bold text-zinc-950">Recent Blogs</h2>
                    <Link href="/blogs" class="btn btn-secondary">Open</Link>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Store</th>
                                <th>Keyword</th>
                                <th>Score</th>
                                <th>Image</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="blog in props.blogs" :key="blog.id">
                                <td><Link :href="`/blogs/${blog.id}/edit`" class="font-semibold text-zinc-950 hover:text-teal-700">{{ blog.title }}</Link></td>
                                <td>{{ blog.store?.name }}</td>
                                <td>{{ blog.primary_keyword ?? '-' }}</td>
                                <td>{{ blog.seo_score ?? '-' }}</td>
                                <td>{{ blog.featured_image_idea ? 'Briefed' : 'Pending' }}</td>
                                <td><span class="badge" :class="`badge-${blog.status}`">{{ blog.status.replace('_', ' ') }}</span></td>
                            </tr>
                            <tr v-if="!props.blogs.length">
                                <td colspan="6" class="text-zinc-500">No blogs generated.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
