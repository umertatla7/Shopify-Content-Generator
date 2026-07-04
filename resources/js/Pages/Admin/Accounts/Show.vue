<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Coins, Cpu, ExternalLink, Package, RefreshCw, Settings, Store, TriangleAlert, UserRound, Users } from 'lucide-vue-next';

const props = defineProps({
    account: Object,
    plans: Array,
    stores: Array,
    blogs: Array,
    activity: Array,
    creditUsage: Array,
    aiCostSummary: Object,
    creditsUsedSummary: Object,
    recentFailures: Object,
});

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'package', label: 'Package' },
    { key: 'stores', label: 'Store' },
    { key: 'members', label: 'Members' },
    { key: 'activity', label: 'Activity' },
];

const activeTab = ref('overview');

const packageForm = useForm({
    plan_key: props.account.plan_key ?? 'free',
    monthly_credit_allowance: props.account.monthly_credit_allowance ?? 0,
    credit_balance: props.account.credit_balance ?? 0,
    credits_expire_at: props.account.credits_expire_at ? props.account.credits_expire_at.slice(0, 10) : '',
});

const accountForm = useForm({
    name: props.account.name ?? '',
    billing_email: props.account.billing_email ?? props.account.owner?.email ?? '',
    region: props.account.region ?? '',
    timezone: props.account.timezone ?? 'UTC',
    status: props.account.status ?? 'active',
    owner_name: props.account.owner?.name ?? '',
    owner_email: props.account.owner?.email ?? '',
    password: '',
});

const creditForm = useForm({
    credits: 0,
    note: '',
    credits_expire_at: props.account.credits_expire_at ? props.account.credits_expire_at.slice(0, 10) : '',
});

const storeForms = reactive(Object.fromEntries(props.stores.map((store) => [
    store.id,
    {
        name: store.name ?? '',
        shop_url: store.shop_url ?? '',
        country: store.country ?? '',
        default_language: store.default_language ?? 'en',
        brand_tone: store.brand_tone ?? '',
        status: store.status ?? 'pending',
        admin_api_access_token: '',
        api_key: '',
        client_secret: '',
        reset_credentials: false,
    },
])));

watch(() => packageForm.plan_key, (planKey) => {
    const plan = props.plans.find((item) => item.key === planKey);

    if (plan) {
        packageForm.monthly_credit_allowance = plan.monthly_credit_allowance ?? packageForm.monthly_credit_allowance;
    }
});

const primaryStore = computed(() => props.stores[0] ?? null);
const money = (value) => `$${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`;
const number = (value) => Number(value || 0).toLocaleString();
const humanStatus = (value) => String(value || '').replaceAll('_', ' ');

const savePackage = () => packageForm.patch(`/admin/accounts/${props.account.id}/package`, { preserveScroll: true });
const saveAccount = () => accountForm.patch(`/admin/accounts/${props.account.id}`, { preserveScroll: true });
const adjustCredits = () => creditForm.post(`/admin/accounts/${props.account.id}/credits`, {
    preserveScroll: true,
    onSuccess: () => creditForm.reset('credits', 'note'),
});
const saveStore = (store) => router.patch(`/admin/accounts/${props.account.id}/stores/${store.id}`, storeForms[store.id], { preserveScroll: true });
</script>

<template>
    <Head :title="props.account.name" />
    <AppLayout>
        <template #title>Customer Detail</template>

        <div class="mb-4">
            <Link href="/admin/accounts" class="text-sm font-semibold text-teal-700">Back to customers</Link>
            <h2 class="mt-1 text-2xl font-bold text-zinc-950">{{ props.account.name }}</h2>
            <p class="text-sm text-zinc-500">
                {{ primaryStore?.name || 'No store connected' }} · {{ props.account.owner?.email ?? props.account.billing_email ?? 'No billing email' }} · {{ props.account.plan_key }}
            </p>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Package</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-950">{{ props.account.plan_key }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ number(props.account.monthly_credit_allowance) }} monthly credits</p>
                    </div>
                    <Package class="size-5 text-indigo-700" />
                </div>
            </div>
            <div class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Credit balance</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-950">{{ number(props.account.credit_balance) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ number(props.creditsUsedSummary.current_month) }} used this month</p>
                    </div>
                    <Coins class="size-5 text-amber-700" />
                </div>
            </div>
            <div class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">AI cost</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-950">{{ money(props.aiCostSummary.current_month.estimated_cost) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ money(props.aiCostSummary.all_time.estimated_cost) }} all time</p>
                    </div>
                    <Cpu class="size-5 text-emerald-700" />
                </div>
            </div>
            <div class="panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Workspace</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-950">{{ props.account.stores_count }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ props.account.users_count }} member{{ props.account.users_count === 1 ? '' : 's' }}</p>
                    </div>
                    <Users class="size-5 text-teal-700" />
                </div>
            </div>
        </section>

        <section class="panel mt-6">
            <div class="panel-body flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-md border px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === tab.key ? 'border-teal-200 bg-teal-50 text-teal-800' : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>
        </section>

        <div v-if="activeTab === 'overview'" class="mt-6 space-y-6">
            <div class="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
                <section class="panel">
                    <div class="panel-header">
                        <h3 class="text-sm font-bold text-zinc-950">Customer and owner</h3>
                    </div>
                    <form class="panel-body grid gap-4 md:grid-cols-2" @submit.prevent="saveAccount">
                        <div>
                            <label>Customer/account name</label>
                            <input v-model="accountForm.name" />
                        </div>
                        <div>
                            <label>Billing email</label>
                            <input v-model="accountForm.billing_email" type="email" />
                        </div>
                        <div>
                            <label>Region</label>
                            <input v-model="accountForm.region" />
                        </div>
                        <div>
                            <label>Timezone</label>
                            <input v-model="accountForm.timezone" />
                        </div>
                        <div>
                            <label>Owner name</label>
                            <input v-model="accountForm.owner_name" />
                        </div>
                        <div>
                            <label>Owner email</label>
                            <input v-model="accountForm.owner_email" type="email" />
                        </div>
                        <div>
                            <label>Status</label>
                            <select v-model="accountForm.status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label>Reset password</label>
                            <input v-model="accountForm.password" type="password" placeholder="Leave blank to keep current" />
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <button class="btn btn-primary" :disabled="accountForm.processing">
                                <Settings class="size-4" />
                                Save customer
                            </button>
                        </div>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h3 class="text-sm font-bold text-zinc-950">Primary store snapshot</h3>
                    </div>
                    <div class="panel-body space-y-3" v-if="primaryStore">
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="text-xs text-zinc-500">Store name</div>
                            <div class="mt-1 font-semibold text-zinc-950">{{ primaryStore.name }}</div>
                        </div>
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="text-xs text-zinc-500">Store URL</div>
                            <a :href="primaryStore.shop_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 font-semibold text-teal-700">
                                {{ primaryStore.shop_domain }}
                                <ExternalLink class="size-3.5" />
                            </a>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs text-zinc-500">Status</div>
                                <div class="mt-1"><span class="badge" :class="`badge-${primaryStore.status}`">{{ humanStatus(primaryStore.status) }}</span></div>
                            </div>
                            <div class="rounded-md border border-zinc-200 p-3">
                                <div class="text-xs text-zinc-500">Last sync</div>
                                <div class="mt-1 text-sm font-semibold text-zinc-950">{{ primaryStore.last_synced_at ? new Date(primaryStore.last_synced_at).toLocaleString() : '-' }}</div>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Link :href="`/admin/stores/${primaryStore.id}`" class="btn btn-secondary">
                                <Store class="size-4" />
                                Open support view
                            </Link>
                        </div>
                    </div>
                    <div v-else class="panel-body">
                        <div class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No store connected for this customer yet.</div>
                    </div>
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
                <section class="panel">
                    <div class="panel-header">
                        <h3 class="text-sm font-bold text-zinc-950">Recent issues</h3>
                    </div>
                    <div class="panel-body space-y-3">
                        <div v-for="item in props.recentFailures.sync" :key="`sync-${item.id}`" class="rounded-md border border-zinc-200 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-semibold text-zinc-950">{{ item.store_name }}</div>
                                <span class="badge" :class="`badge-${item.status}`">{{ humanStatus(item.status) }}</span>
                            </div>
                            <div class="mt-1 text-sm text-zinc-600">{{ item.error_message || 'Sync failure recorded.' }}</div>
                        </div>
                        <div v-for="item in props.recentFailures.analysis" :key="`analysis-${item.id}`" class="rounded-md border border-zinc-200 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-semibold text-zinc-950">{{ item.store_name }}</div>
                                <span class="badge" :class="`badge-${item.status}`">{{ humanStatus(item.status) }}</span>
                            </div>
                            <div class="mt-1 text-sm text-zinc-600">{{ item.error_message || 'Analysis failure recorded.' }}</div>
                        </div>
                        <div v-if="!props.recentFailures.sync.length && !props.recentFailures.analysis.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">
                            No recent store issues for this customer.
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h3 class="text-sm font-bold text-zinc-950">Recent blogs</h3>
                    </div>
                    <div class="panel-body space-y-3">
                        <div v-for="blog in props.blogs" :key="blog.id" class="rounded-md border border-zinc-200 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-zinc-950">{{ blog.title }}</div>
                                    <div class="text-xs text-zinc-500">{{ blog.store?.name || '-' }}</div>
                                </div>
                                <span class="badge" :class="`badge-${blog.status}`">{{ humanStatus(blog.status) }}</span>
                            </div>
                        </div>
                        <div v-if="!props.blogs.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No blogs yet for this customer.</div>
                    </div>
                </section>
            </div>
        </div>

        <div v-else-if="activeTab === 'package'" class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Package and limits</h3></div>
                <form class="panel-body grid gap-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="savePackage">
                    <div>
                        <label>Package key</label>
                        <select v-model="packageForm.plan_key">
                            <option v-for="plan in props.plans" :key="plan.key" :value="plan.key">
                                {{ plan.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label>Monthly credits</label>
                        <input v-model="packageForm.monthly_credit_allowance" type="number" min="0" />
                    </div>
                    <div>
                        <label>Current balance</label>
                        <input v-model="packageForm.credit_balance" type="number" min="0" />
                    </div>
                    <div>
                        <label>Credit expiry</label>
                        <input v-model="packageForm.credits_expire_at" type="date" />
                    </div>
                    <div class="xl:col-span-4 flex justify-end">
                        <button class="btn btn-primary" :disabled="packageForm.processing">
                            <Package class="size-4" />
                            Save package
                        </button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Credit adjustment</h3></div>
                <form class="panel-body space-y-4" @submit.prevent="adjustCredits">
                    <div>
                        <label>Add/remove credits</label>
                        <input v-model="creditForm.credits" type="number" />
                    </div>
                    <div>
                        <label>Admin note</label>
                        <input v-model="creditForm.note" placeholder="Bonus credits, correction, expired credits..." />
                    </div>
                    <div>
                        <label>Credit expiry</label>
                        <input v-model="creditForm.credits_expire_at" type="date" />
                    </div>
                    <button class="btn btn-secondary w-full" :disabled="creditForm.processing">
                        <Coins class="size-4" />
                        Apply credits
                    </button>
                </form>
            </section>
        </div>

        <div v-else-if="activeTab === 'stores'" class="mt-6">
            <section class="panel">
                <div class="panel-header">
                    <h3 class="text-sm font-bold text-zinc-950">Connected store details</h3>
                </div>
                <div class="panel-body space-y-4">
                    <form v-for="store in props.stores" :key="store.id" class="rounded-md border border-zinc-200 p-4" @submit.prevent="saveStore(store)">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h4 class="font-semibold text-zinc-950">{{ store.name }}</h4>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs text-zinc-500">
                                    <span>{{ store.products_count }} products</span>
                                    <span>{{ store.collections_count }} collections</span>
                                    <span>{{ store.blogs_count }} blogs</span>
                                    <span>{{ store.pages_count }} pages</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="badge" :class="`badge-${store.status}`">{{ humanStatus(store.status) }}</span>
                                <Link :href="`/admin/stores/${store.id}`" class="btn btn-secondary">
                                    <ExternalLink class="size-4" />
                                    Open support view
                                </Link>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <label>Store name</label>
                                <input v-model="storeForms[store.id].name" />
                            </div>
                            <div>
                                <label>Store URL</label>
                                <input v-model="storeForms[store.id].shop_url" />
                            </div>
                            <div>
                                <label>Status</label>
                                <select v-model="storeForms[store.id].status">
                                    <option value="pending">Pending</option>
                                    <option value="connected">Connected</option>
                                    <option value="disconnected">Disconnected</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div>
                                <label>Last sync</label>
                                <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600">{{ store.last_synced_at ? new Date(store.last_synced_at).toLocaleString() : '-' }}</div>
                            </div>
                            <div>
                                <label>Region/country</label>
                                <input v-model="storeForms[store.id].country" />
                            </div>
                            <div>
                                <label>Language</label>
                                <input v-model="storeForms[store.id].default_language" />
                            </div>
                            <div class="xl:col-span-2">
                                <label>Brand tone</label>
                                <input v-model="storeForms[store.id].brand_tone" />
                            </div>
                        </div>

                        <div class="mt-4 rounded-md border border-dashed border-zinc-300 p-3">
                            <label class="mb-3 flex items-center gap-2 normal-case tracking-normal text-zinc-600">
                                <input v-model="storeForms[store.id].reset_credentials" type="checkbox" class="size-4 rounded border-zinc-300 p-0" />
                                Reset saved credentials before saving
                            </label>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label>New access token</label>
                                    <input v-model="storeForms[store.id].admin_api_access_token" type="password" placeholder="Leave blank to keep existing" />
                                </div>
                                <div>
                                    <label>New API key / Client ID</label>
                                    <input v-model="storeForms[store.id].api_key" type="password" placeholder="Leave blank to keep existing" />
                                </div>
                                <div>
                                    <label>New Client Secret</label>
                                    <input v-model="storeForms[store.id].client_secret" type="password" placeholder="Leave blank to keep existing" />
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button class="btn btn-primary" type="submit">
                                <RefreshCw class="size-4" />
                                Save store
                            </button>
                        </div>
                    </form>
                    <div v-if="!props.stores.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No stores connected for this customer.</div>
                </div>
            </section>
        </div>

        <div v-else-if="activeTab === 'members'" class="mt-6">
            <section class="panel">
                <div class="panel-header">
                    <h3 class="text-sm font-bold text-zinc-950">Customer members</h3>
                </div>
                <div class="panel-body space-y-3">
                    <div v-for="user in props.account.users" :key="user.id" class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-zinc-200 p-4">
                        <div>
                            <div class="font-semibold text-zinc-950">{{ user.name }}</div>
                            <div class="text-sm text-zinc-500">{{ user.email }}</div>
                            <div class="mt-1 text-xs text-zinc-500">{{ user.global_role }}</div>
                        </div>
                        <Link :href="`/admin/users/${user.id}/edit`" class="btn btn-secondary">
                            <UserRound class="size-4" />
                            Edit access
                        </Link>
                    </div>
                    <div v-if="!props.account.users.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No members yet for this customer.</div>
                </div>
            </section>
        </div>

        <div v-else class="mt-6 grid gap-6 xl:grid-cols-[1fr_.9fr]">
            <section class="panel">
                <div class="panel-header">
                    <h3 class="text-sm font-bold text-zinc-950">Activity timeline</h3>
                </div>
                <div class="panel-body space-y-3">
                    <div v-for="item in props.activity" :key="item.id" class="rounded-md border border-zinc-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-semibold text-zinc-950">{{ item.action }}</div>
                            <span class="badge" :class="`badge-${item.status || 'success'}`">{{ humanStatus(item.status || 'success') }}</span>
                        </div>
                        <div class="mt-1 text-sm text-zinc-600">{{ item.description || '-' }}</div>
                        <div class="mt-1 text-xs text-zinc-500">{{ item.store?.name || 'Workspace' }} · {{ item.user?.email || 'System' }} · {{ new Date(item.created_at).toLocaleString() }}</div>
                    </div>
                    <div v-if="!props.activity.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No activity yet for this customer.</div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h3 class="text-sm font-bold text-zinc-950">Credit usage</h3>
                </div>
                <div class="panel-body space-y-3">
                    <div v-for="item in props.creditUsage" :key="item.id" class="rounded-md border border-zinc-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-semibold text-zinc-950">{{ item.metadata?.action ?? item.type }}</div>
                            <div class="text-sm font-bold text-zinc-950">{{ item.quantity }} {{ item.unit }}</div>
                        </div>
                        <div class="mt-1 text-xs text-zinc-500">{{ new Date(item.created_at).toLocaleString() }}</div>
                    </div>
                    <div v-if="!props.creditUsage.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No credit usage yet.</div>
                </div>

                <div class="border-t border-zinc-200 p-4">
                    <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-zinc-950">
                        <TriangleAlert class="size-4 text-amber-600" />
                        Support note
                    </div>
                    <p class="text-sm text-zinc-600">Use the customer tabs for ownership, package, and store settings. Use the store support view when you need sync, publish, or analysis failure history in more detail.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
