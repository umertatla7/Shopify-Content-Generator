<script setup>
import { reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    plans: Array,
    featureOptions: Array,
});

const defaultFeatures = ['product_descriptions'];
const defaultFeatureText = defaultFeatures.join('\n');

const newForm = useForm({
    key: '',
    name: '',
    monthly_price: 0,
    trial_days: 14,
    monthly_blog_limit: 1,
    monthly_ai_token_limit: 150000,
    monthly_credit_allowance: 500,
    word_limit_estimate: 5000,
    store_limit: 1,
    user_limit: 1,
    product_description_limit: 25,
    collection_description_limit: 0,
    monthly_seo_report_limit: 1,
    monthly_ai_visibility_report_limit: 1,
    monthly_image_optimization_limit: 0,
    monthly_image_alt_text_limit: 0,
    tracked_keyword_limit: 10,
    credit_expires_after_days: 30,
    shopify_billing_plan_handle: '',
    features: [...defaultFeatures],
    is_active: true,
});
const newFeaturesText = ref(defaultFeatureText);

const planForms = reactive(Object.fromEntries(props.plans.map((plan) => [
    plan.id,
    {
        ...plan,
        features_text: (plan.features ?? []).join('\n'),
    },
])));

const normalizeFeatures = (value) => String(value || '')
    .split('\n')
    .map((item) => item.trim())
    .filter(Boolean);

const resetNewForm = () => {
    newForm.reset();
    Object.assign(newForm, {
        monthly_price: 0,
        trial_days: 14,
        monthly_blog_limit: 1,
        monthly_ai_token_limit: 150000,
        monthly_credit_allowance: 500,
        word_limit_estimate: 5000,
        store_limit: 1,
        user_limit: 1,
        product_description_limit: 25,
        collection_description_limit: 0,
        monthly_seo_report_limit: 1,
        monthly_ai_visibility_report_limit: 1,
        monthly_image_optimization_limit: 0,
        monthly_image_alt_text_limit: 0,
        tracked_keyword_limit: 10,
        credit_expires_after_days: 30,
        shopify_billing_plan_handle: '',
        features: [...defaultFeatures],
        is_active: true,
    });
    newFeaturesText.value = defaultFeatureText;
};

const submitNew = () => {
    newForm.features = Array.from(new Set([
        ...newForm.features,
        ...normalizeFeatures(newFeaturesText.value),
    ]));

    newForm.post('/admin/plans', {
        preserveScroll: true,
        onSuccess: () => resetNewForm(),
    });
};

const savePlan = (plan) => {
    const form = planForms[plan.id];

    router.patch(`/admin/plans/${plan.id}`, {
        key: form.key,
        name: form.name,
        monthly_price: form.monthly_price,
        trial_days: form.trial_days,
        monthly_blog_limit: form.monthly_blog_limit,
        monthly_ai_token_limit: form.monthly_ai_token_limit,
        monthly_credit_allowance: form.monthly_credit_allowance,
        word_limit_estimate: form.word_limit_estimate,
        store_limit: form.store_limit,
        user_limit: form.user_limit,
        product_description_limit: form.product_description_limit,
        collection_description_limit: form.collection_description_limit,
        monthly_seo_report_limit: form.monthly_seo_report_limit,
        monthly_ai_visibility_report_limit: form.monthly_ai_visibility_report_limit,
        monthly_image_optimization_limit: form.monthly_image_optimization_limit,
        monthly_image_alt_text_limit: form.monthly_image_alt_text_limit,
        tracked_keyword_limit: form.tracked_keyword_limit,
        credit_expires_after_days: form.credit_expires_after_days,
        shopify_billing_plan_handle: form.shopify_billing_plan_handle,
        features: normalizeFeatures(form.features_text),
        is_active: Boolean(form.is_active),
    }, { preserveScroll: true });
};

const planTone = (key) => ({
    free: 'border-zinc-200 bg-white',
    growth: 'border-teal-200 bg-teal-50/40',
    pro: 'border-indigo-200 bg-indigo-50/40',
}[key] ?? 'border-zinc-200 bg-white');
</script>

<template>
    <Head title="Admin Plans" />
    <AppLayout>
        <template #title>Plans</template>

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-zinc-950">Public app plan catalog</h2>
            <p class="mt-1 max-w-3xl text-sm text-zinc-500">Manage credits, limits, and Shopify billing handles for the public pricing tiers.</p>
        </div>

        <section class="panel mb-6">
            <div class="panel-header">
                <h2 class="text-sm font-bold text-zinc-950">Create plan</h2>
            </div>
            <form class="panel-body grid gap-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="submitNew">
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
                    <label>Trial days</label>
                    <input v-model="newForm.trial_days" type="number" min="0" />
                </div>
                <div>
                    <label>Billing handle</label>
                    <input v-model="newForm.shopify_billing_plan_handle" placeholder="growth" />
                </div>
                <div>
                    <label>Monthly credits</label>
                    <input v-model="newForm.monthly_credit_allowance" type="number" min="0" />
                </div>
                <div>
                    <label>Blogs / month</label>
                    <input v-model="newForm.monthly_blog_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Products / month</label>
                    <input v-model="newForm.product_description_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Collections / month</label>
                    <input v-model="newForm.collection_description_limit" type="number" min="0" />
                </div>
                <div>
                    <label>SEO reports / month</label>
                    <input v-model="newForm.monthly_seo_report_limit" type="number" min="0" />
                </div>
                <div>
                    <label>AI visibility / month</label>
                    <input v-model="newForm.monthly_ai_visibility_report_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Image optimization / month</label>
                    <input v-model="newForm.monthly_image_optimization_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Image alt text / month</label>
                    <input v-model="newForm.monthly_image_alt_text_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Tracked keywords</label>
                    <input v-model="newForm.tracked_keyword_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Store limit</label>
                    <input v-model="newForm.store_limit" type="number" min="0" />
                </div>
                <div>
                    <label>User limit</label>
                    <input v-model="newForm.user_limit" type="number" min="0" />
                </div>
                <div>
                    <label>Credit expiry days</label>
                    <input v-model="newForm.credit_expires_after_days" type="number" min="0" />
                </div>
                <div class="md:col-span-2 xl:col-span-4">
                    <label>Feature access</label>
                    <div class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <label
                            v-for="feature in props.featureOptions"
                            :key="feature.key"
                            class="rounded-md border border-zinc-200 bg-white p-3 text-sm"
                        >
                            <div class="flex items-start gap-3">
                                <input
                                    v-model="newForm.features"
                                    :value="feature.key"
                                    type="checkbox"
                                    class="mt-1 size-4 rounded border-zinc-300 p-0"
                                />
                                <div>
                                    <div class="font-semibold text-zinc-950">{{ feature.label }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">{{ feature.description }}</div>
                                </div>
                            </div>
                        </label>
                    </div>
                    <textarea v-model="newFeaturesText" class="mt-3 min-h-20 font-mono text-xs" placeholder="Optional raw flags, one per line" />
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                    <input v-model="newForm.is_active" type="checkbox" class="size-4 rounded border-zinc-300 p-0" />
                    Active
                </label>
                <div class="flex items-end justify-end md:col-span-2 xl:col-span-3">
                    <button class="btn btn-primary min-w-40" :disabled="newForm.processing">Create</button>
                </div>
            </form>
        </section>

        <section class="space-y-6">
            <article
                v-for="plan in props.plans"
                :key="plan.id"
                class="panel border"
                :class="planTone(plan.key)"
            >
                <div class="panel-header flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-zinc-950">{{ planForms[plan.id].name || 'Untitled plan' }}</h3>
                        <p class="text-sm text-zinc-500">{{ planForms[plan.id].key || 'no-key' }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                            <input v-model="planForms[plan.id].is_active" type="checkbox" class="size-4 rounded border-zinc-300 p-0" />
                            Active
                        </label>
                        <button class="btn btn-primary" type="button" @click="savePlan(plan)">Save</button>
                    </div>
                </div>

                <div class="panel-body space-y-6">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label>Plan name</label>
                            <input v-model="planForms[plan.id].name" />
                        </div>
                        <div>
                            <label>Plan key</label>
                            <input v-model="planForms[plan.id].key" class="font-mono text-xs" />
                        </div>
                        <div>
                            <label>Monthly price</label>
                            <input v-model="planForms[plan.id].monthly_price" type="number" min="0" step="0.01" />
                        </div>
                        <div>
                            <label>Trial days</label>
                            <input v-model="planForms[plan.id].trial_days" type="number" min="0" />
                        </div>
                        <div>
                            <label>Billing handle</label>
                            <input v-model="planForms[plan.id].shopify_billing_plan_handle" placeholder="Optional Shopify price handle" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <div>
                            <label>Monthly credits</label>
                            <input v-model="planForms[plan.id].monthly_credit_allowance" type="number" min="0" />
                        </div>
                        <div>
                            <label>AI token budget</label>
                            <input v-model="planForms[plan.id].monthly_ai_token_limit" type="number" min="0" />
                        </div>
                        <div>
                            <label>Word estimate</label>
                            <input v-model="planForms[plan.id].word_limit_estimate" type="number" min="0" />
                        </div>
                        <div>
                            <label>Store limit</label>
                            <input v-model="planForms[plan.id].store_limit" type="number" min="0" />
                        </div>
                        <div>
                            <label>User limit</label>
                            <input v-model="planForms[plan.id].user_limit" type="number" min="0" />
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-zinc-900">Monthly usage limits</h4>
                        <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <label>Blogs</label>
                                <input v-model="planForms[plan.id].monthly_blog_limit" type="number" min="0" />
                            </div>
                            <div>
                                <label>Product descriptions</label>
                                <input v-model="planForms[plan.id].product_description_limit" type="number" min="0" />
                            </div>
                            <div>
                                <label>Collection descriptions</label>
                                <input v-model="planForms[plan.id].collection_description_limit" type="number" min="0" />
                            </div>
                            <div>
                                <label>SEO reports</label>
                                <input v-model="planForms[plan.id].monthly_seo_report_limit" type="number" min="0" />
                            </div>
                            <div>
                                <label>AI visibility reports</label>
                                <input v-model="planForms[plan.id].monthly_ai_visibility_report_limit" type="number" min="0" />
                            </div>
                            <div>
                                <label>Image optimization</label>
                                <input v-model="planForms[plan.id].monthly_image_optimization_limit" type="number" min="0" />
                            </div>
                            <div>
                                <label>Image alt text</label>
                                <input v-model="planForms[plan.id].monthly_image_alt_text_limit" type="number" min="0" />
                            </div>
                            <div>
                                <label>Tracked keywords</label>
                                <input v-model="planForms[plan.id].tracked_keyword_limit" type="number" min="0" />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label>Credit expiry days</label>
                            <input v-model="planForms[plan.id].credit_expires_after_days" type="number" min="0" />
                        </div>
                        <div>
                            <label>Feature access</label>
                            <div class="mt-2 grid gap-3 lg:grid-cols-2">
                                <label
                                    v-for="feature in props.featureOptions"
                                    :key="`${plan.id}-${feature.key}`"
                                    class="rounded-md border border-zinc-200 bg-white p-3 text-sm"
                                >
                                    <div class="flex items-start gap-3">
                                        <input
                                            :checked="normalizeFeatures(planForms[plan.id].features_text).includes(feature.key)"
                                            type="checkbox"
                                            class="mt-1 size-4 rounded border-zinc-300 p-0"
                                            @change="(event) => {
                                                const next = new Set(normalizeFeatures(planForms[plan.id].features_text));
                                                if (event.target.checked) next.add(feature.key); else next.delete(feature.key);
                                                planForms[plan.id].features_text = Array.from(next).join('\n');
                                            }"
                                        />
                                        <div>
                                            <div class="font-semibold text-zinc-950">{{ feature.label }}</div>
                                            <div class="mt-1 text-xs text-zinc-500">{{ feature.description }}</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <textarea v-model="planForms[plan.id].features_text" class="mt-3 min-h-20 font-mono text-xs" />
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </AppLayout>
</template>
