<script setup>
import { reactive } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    plans: Array,
});

const newForm = useForm({
    key: '',
    name: '',
    monthly_price: 0,
    monthly_blog_limit: 40,
    monthly_ai_token_limit: 500000,
    monthly_credit_allowance: 1000,
    word_limit_estimate: 10000,
    store_limit: 1,
    user_limit: 1,
    product_description_limit: 70,
    collection_description_limit: 10,
    credit_expires_after_days: '',
    features: ['phase_1_blog_manager'],
    is_active: true,
});

const planForms = reactive(Object.fromEntries(props.plans.map((plan) => [
    plan.id,
    {
        ...plan,
        features_text: (plan.features ?? []).join('\n'),
    },
])));

const submitNew = () => {
    newForm.features = Array.isArray(newForm.features)
        ? newForm.features
        : String(newForm.features || '').split('\n').map((item) => item.trim()).filter(Boolean);
    newForm.post('/admin/plans', {
        preserveScroll: true,
        onSuccess: () => newForm.reset(),
    });
};

const savePlan = (plan) => {
    const form = planForms[plan.id];
    router.patch(`/admin/plans/${plan.id}`, {
        key: form.key,
        name: form.name,
        monthly_price: form.monthly_price,
        monthly_blog_limit: form.monthly_blog_limit,
        monthly_ai_token_limit: form.monthly_ai_token_limit,
        monthly_credit_allowance: form.monthly_credit_allowance,
        word_limit_estimate: form.word_limit_estimate,
        store_limit: form.store_limit,
        user_limit: form.user_limit,
        product_description_limit: form.product_description_limit,
        collection_description_limit: form.collection_description_limit,
        credit_expires_after_days: form.credit_expires_after_days,
        features: String(form.features_text || '').split('\n').map((item) => item.trim()).filter(Boolean),
        is_active: Boolean(form.is_active),
    }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Admin Plans" />
    <AppLayout>
        <template #title>Plans</template>

        <section class="panel mb-6">
            <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">Create package</h2></div>
            <form class="panel-body grid gap-4 md:grid-cols-3 xl:grid-cols-6" @submit.prevent="submitNew">
                <div>
                    <label>Key</label>
                    <input v-model="newForm.key" placeholder="growth" />
                </div>
                <div>
                    <label>Name</label>
                    <input v-model="newForm.name" placeholder="Growth" />
                </div>
                <div>
                    <label>Monthly price</label>
                    <input v-model="newForm.monthly_price" type="number" min="0" step="0.01" />
                </div>
                <div>
                    <label>Store limit</label>
                    <input v-model="newForm.store_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Monthly credits</label>
                    <input v-model="newForm.monthly_credit_allowance" type="number" min="0" />
                </div>
                <div>
                    <label>User limit</label>
                    <input v-model="newForm.user_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Blog limit</label>
                    <input v-model="newForm.monthly_blog_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Word estimate</label>
                    <input v-model="newForm.word_limit_estimate" type="number" min="0" />
                </div>
                <div>
                    <label>Product desc limit</label>
                    <input v-model="newForm.product_description_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Collection desc limit</label>
                    <input v-model="newForm.collection_description_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Credit expiry days</label>
                    <input v-model="newForm.credit_expires_after_days" type="number" min="0" placeholder="Optional" />
                </div>
                <div class="flex items-end">
                    <button class="btn btn-primary w-full" :disabled="newForm.processing">Create</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header"><h2 class="text-sm font-bold text-zinc-950">Package definitions</h2></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Price</th>
                            <th>Credits</th>
                            <th>Limits</th>
                            <th>Blogs</th>
                            <th>Features</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="plan in props.plans" :key="plan.id">
                            <td class="min-w-60">
                                <input v-model="planForms[plan.id].name" class="mb-2" />
                                <input v-model="planForms[plan.id].key" class="font-mono text-xs" />
                            </td>
                            <td><input v-model="planForms[plan.id].monthly_price" type="number" min="0" step="0.01" class="w-28" /></td>
                            <td><input v-model="planForms[plan.id].monthly_credit_allowance" type="number" min="0" class="w-32" /></td>
                            <td>
                                <div class="grid min-w-72 grid-cols-2 gap-2">
                                    <label class="normal-case tracking-normal text-zinc-600">Stores<input v-model="planForms[plan.id].store_limit" type="number" min="0" class="mt-1" /></label>
                                    <label class="normal-case tracking-normal text-zinc-600">Users<input v-model="planForms[plan.id].user_limit" type="number" min="0" class="mt-1" /></label>
                                    <label class="normal-case tracking-normal text-zinc-600">Products<input v-model="planForms[plan.id].product_description_limit" type="number" min="0" class="mt-1" /></label>
                                    <label class="normal-case tracking-normal text-zinc-600">Collections<input v-model="planForms[plan.id].collection_description_limit" type="number" min="0" class="mt-1" /></label>
                                    <label class="normal-case tracking-normal text-zinc-600">Words<input v-model="planForms[plan.id].word_limit_estimate" type="number" min="0" class="mt-1" /></label>
                                    <label class="normal-case tracking-normal text-zinc-600">Expiry days<input v-model="planForms[plan.id].credit_expires_after_days" type="number" min="0" class="mt-1" /></label>
                                </div>
                            </td>
                            <td><input v-model="planForms[plan.id].monthly_blog_limit" type="number" min="0" class="w-28" /></td>
                            <td><textarea v-model="planForms[plan.id].features_text" class="min-h-24 w-64 font-mono text-xs" /></td>
                            <td><input v-model="planForms[plan.id].is_active" type="checkbox" class="size-4 rounded border-zinc-300 p-0" /></td>
                            <td><button class="btn btn-primary" type="button" @click="savePlan(plan)">Save</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
