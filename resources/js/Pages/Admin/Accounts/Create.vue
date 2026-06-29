<script setup>
import { watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { LoaderCircle, UserPlus } from 'lucide-vue-next';

const props = defineProps({
    plans: Array,
});

const form = useForm({
    customer_name: '',
    email: '',
    password: '',
    send_invite: false,
    company_name: '',
    plan_key: props.plans[0]?.key ?? 'free',
    credit_balance: props.plans[0]?.monthly_credit_allowance ?? 500,
    store_url: '',
    shopify_access_token: '',
    shopify_api_key: '',
    shopify_client_secret: '',
    store_region: '',
    store_language: 'en',
    brand_tone: '',
    status: 'active',
});

watch(() => form.plan_key, (planKey) => {
    const plan = props.plans.find((item) => item.key === planKey);

    if (plan) {
        form.credit_balance = plan.monthly_credit_allowance ?? form.credit_balance;
    }
});

const submit = () => form.post('/admin/accounts');
</script>

<template>
    <Head title="Create Customer" />
    <AppLayout>
        <template #title>Create Customer</template>

        <div class="mb-4">
            <Link href="/admin/accounts" class="text-sm font-semibold text-teal-700">Back to accounts</Link>
            <h2 class="mt-1 text-2xl font-bold text-zinc-950">Create customer account</h2>
            <p class="text-sm text-zinc-500">Create the owner, account, package, credits, and optional Shopify store from one place.</p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Customer</h3></div>
                <div class="panel-body grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label>Customer name</label>
                        <input v-model="form.customer_name" />
                        <p v-if="form.errors.customer_name" class="mt-1 text-xs text-rose-700">{{ form.errors.customer_name }}</p>
                    </div>
                    <div>
                        <label>Email</label>
                        <input v-model="form.email" type="email" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-700">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label>Password</label>
                        <input v-model="form.password" type="password" placeholder="Leave blank for generated password" />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-700">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label>Status</label>
                        <select v-model="form.status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 normal-case tracking-normal text-zinc-600 md:col-span-2">
                        <input v-model="form.send_invite" type="checkbox" class="size-4 rounded border-zinc-300 p-0" />
                        Mark as invite pending instead of active login
                    </label>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Company and package</h3></div>
                <div class="panel-body grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="xl:col-span-2">
                        <label>Company/store name</label>
                        <input v-model="form.company_name" />
                        <p v-if="form.errors.company_name" class="mt-1 text-xs text-rose-700">{{ form.errors.company_name }}</p>
                    </div>
                    <div>
                        <label>Assigned plan</label>
                        <select v-model="form.plan_key">
                            <option v-for="plan in props.plans" :key="plan.key" :value="plan.key">{{ plan.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label>Credit balance</label>
                        <input v-model="form.credit_balance" type="number" min="0" />
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Shopify store</h3></div>
                <div class="panel-body grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="xl:col-span-2">
                        <label>Store URL</label>
                        <input v-model="form.store_url" placeholder="your-store.myshopify.com" />
                    </div>
                    <div>
                        <label>Store region</label>
                        <input v-model="form.store_region" placeholder="US, PK, GB..." />
                    </div>
                    <div>
                        <label>Store language</label>
                        <input v-model="form.store_language" />
                    </div>
                    <div class="xl:col-span-2">
                        <label>Shopify access token</label>
                        <input v-model="form.shopify_access_token" type="password" placeholder="Optional: shpat_ or shpua_" />
                    </div>
                    <div>
                        <label>Shopify API key / Client ID</label>
                        <input v-model="form.shopify_api_key" type="password" />
                    </div>
                    <div>
                        <label>Client secret</label>
                        <input v-model="form.shopify_client_secret" type="password" />
                    </div>
                    <div class="xl:col-span-4">
                        <label>Brand tone</label>
                        <input v-model="form.brand_tone" placeholder="Luxury, friendly, educational..." />
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button class="btn btn-primary min-w-52" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <UserPlus v-else class="size-4" />
                    Create customer
                </button>
            </div>
        </form>
    </AppLayout>
</template>
