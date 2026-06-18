<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    account: Object,
    plans: Array,
    stores: Array,
    blogs: Array,
    activity: Array,
    creditUsage: Array,
});

const packageForm = useForm({
    plan_key: props.account.plan_key ?? 'starter',
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
        packageForm.credit_balance = plan.monthly_credit_allowance ?? packageForm.credit_balance;
    }
});

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
        <template #title>Account Detail</template>

        <div class="mb-4">
            <Link href="/admin/accounts" class="text-sm font-semibold text-teal-700">Back to accounts</Link>
            <h2 class="mt-1 text-2xl font-bold text-zinc-950">{{ props.account.name }}</h2>
            <p class="text-sm text-zinc-500">{{ props.account.billing_email ?? props.account.owner?.email ?? 'No billing email' }} · {{ props.account.plan_key }}</p>
        </div>

        <section class="panel mb-6">
            <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Customer settings</h3></div>
            <form class="panel-body grid gap-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="saveAccount">
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
                    <label>Status</label>
                    <select v-model="accountForm.status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
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
                    <label>Reset password</label>
                    <input v-model="accountForm.password" type="password" placeholder="Leave blank to keep current" />
                </div>
                <div class="flex items-end">
                    <button class="btn btn-primary w-full" :disabled="accountForm.processing">Save customer</button>
                </div>
            </form>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="panel xl:col-span-1">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Members</h3></div>
                <div class="panel-body space-y-3">
                    <div v-for="user in props.account.users" :key="user.id" class="rounded-md border border-zinc-200 p-3">
                        <div class="font-semibold text-zinc-950">{{ user.name }}</div>
                        <div class="text-xs text-zinc-500">{{ user.email }}</div>
                    </div>
                </div>
            </section>

            <section class="panel xl:col-span-2">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Package and credits</h3></div>
                <form class="panel-body grid gap-4 md:grid-cols-5" @submit.prevent="savePackage">
                    <div>
                        <label>Package key</label>
                        <select v-model="packageForm.plan_key">
                            <option v-for="plan in props.plans" :key="plan.key" :value="plan.key">
                                {{ plan.name }} ({{ plan.store_limit }} store{{ plan.store_limit === 1 ? '' : 's' }})
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
                    <div class="flex items-end">
                        <button class="btn btn-primary w-full" :disabled="packageForm.processing">Save package</button>
                    </div>
                </form>
                <form class="border-t border-zinc-200 p-4" @submit.prevent="adjustCredits">
                    <div class="grid gap-4 md:grid-cols-[160px_1fr_180px_150px]">
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
                        <div class="flex items-end">
                            <button class="btn btn-secondary w-full" :disabled="creditForm.processing">Apply credits</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="panel xl:col-span-3">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Stores and Shopify credentials</h3></div>
                <div class="panel-body space-y-4">
                    <form v-for="store in props.stores" :key="store.id" class="rounded-md border border-zinc-200 p-4" @submit.prevent="saveStore(store)">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="font-semibold text-zinc-950">{{ store.name }}</h4>
                                <p class="text-xs text-zinc-500">{{ store.shop_domain }} · {{ store.products_count }} products · {{ store.collections_count }} collections · {{ store.blogs_count }} blogs</p>
                            </div>
                            <span class="badge" :class="`badge-${store.status}`">{{ store.status }}</span>
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
                            <p class="mt-2 text-xs text-zinc-500">Stored credentials are write-only. Enter new values only when rotating or fixing Shopify access.</p>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button class="btn btn-primary" type="submit">Save store</button>
                        </div>
                    </form>
                    <div v-if="!props.stores.length" class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">No stores connected for this customer.</div>
                </div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Recent Blogs</h3></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Title</th><th>Store</th><th>Status</th><th>SEO</th></tr></thead>
                        <tbody>
                            <tr v-for="blog in props.blogs" :key="blog.id">
                                <td>{{ blog.title }}</td>
                                <td>{{ blog.store?.name }}</td>
                                <td><span class="badge" :class="`badge-${blog.status}`">{{ blog.status.replace('_', ' ') }}</span></td>
                                <td>{{ blog.seo_score ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Credit usage</h3></div>
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
            </section>

            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Activity</h3></div>
                <div class="panel-body space-y-3">
                    <div v-for="item in props.activity" :key="item.id" class="rounded-md border border-zinc-200 p-3">
                        <div class="font-semibold text-zinc-950">{{ item.action }}</div>
                        <div class="text-sm text-zinc-600">{{ item.description ?? '-' }}</div>
                        <div class="text-xs text-zinc-500">{{ item.user?.email ?? 'System' }} · {{ new Date(item.created_at).toLocaleString() }}</div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
