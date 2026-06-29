<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircle2, KeyRound, Save, ShieldAlert } from 'lucide-vue-next';

const props = defineProps({
    groups: Array,
});

const form = useForm({
    settings: Object.fromEntries(
        props.groups.flatMap((group) => group.fields.map((field) => [field.key, field.value ?? '']))
    ),
});

const save = () => form.post('/admin/settings', { preserveScroll: true });

const sourceLabel = (source) => ({
    admin: 'Admin',
    env: '.env',
    missing: 'Missing',
}[source] ?? source);

const sourceClass = (source) => ({
    admin: 'bg-emerald-100 text-emerald-800',
    env: 'bg-sky-100 text-sky-800',
    missing: 'bg-rose-100 text-rose-800',
}[source] ?? 'bg-zinc-100 text-zinc-700');
</script>

<template>
    <Head title="Provider Settings" />
    <AppLayout>
        <template #title>Provider Settings</template>

        <div class="space-y-5">
            <section class="panel">
                <div class="panel-body flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">
                            <KeyRound class="size-4" />
                            Secure provider keys
                        </div>
                        <h2 class="text-lg font-bold text-zinc-950">Manage API keys used by the portal</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-zinc-600">
                            Values saved here are encrypted in the database. Existing server `.env` values stay as fallback until an admin value is saved.
                        </p>
                    </div>
                    <button class="btn btn-primary" type="button" :disabled="form.processing" @click="save">
                        <Save class="size-4" />
                        Save settings
                    </button>
                </div>
            </section>

            <section v-for="group in props.groups" :key="group.label" class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-950">{{ group.label }}</h2>
                        <p class="text-xs text-zinc-500">{{ group.description }}</p>
                    </div>
                </div>
                <div class="grid gap-4 p-4 lg:grid-cols-2">
                    <div v-for="field in group.fields" :key="field.key" class="rounded-md border border-zinc-200 p-4">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <label :for="field.key">{{ field.label }}</label>
                                <p class="mt-1 text-xs text-zinc-500">{{ field.secret ? 'Leave blank to keep the existing secret.' : field.placeholder }}</p>
                            </div>
                            <span class="badge shrink-0" :class="sourceClass(field.source)">
                                {{ sourceLabel(field.source) }}
                            </span>
                        </div>
                        <input
                            :id="field.key"
                            v-model="form.settings[field.key]"
                            :type="field.secret ? 'password' : 'text'"
                            :placeholder="field.configured && field.secret ? 'Configured' : field.placeholder"
                        />
                        <div class="mt-3 flex items-center gap-2 text-xs font-semibold" :class="field.configured ? 'text-emerald-700' : 'text-rose-700'">
                            <CheckCircle2 v-if="field.configured" class="size-4" />
                            <ShieldAlert v-else class="size-4" />
                            {{ field.configured ? 'Configured' : 'Not configured' }}
                        </div>
                        <p v-if="form.errors[`settings.${field.key}`]" class="mt-2 text-xs text-rose-700">
                            {{ form.errors[`settings.${field.key}`] }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
