<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const page = usePage();
const form = useForm({ email: '' });
const submit = () => form.post('/forgot-password');
</script>

<template>
    <Head title="Forgot Password" />
    <AuthLayout>
        <div class="mb-6">
            <h1 class="text-xl font-bold text-zinc-950">Reset password</h1>
            <p class="mt-1 text-sm text-zinc-500">Receive a reset link by email.</p>
        </div>

        <div v-if="page.props.flash?.status" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ page.props.flash.status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label>Email</label>
                <input v-model="form.email" type="email" autocomplete="email" />
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-700">{{ form.errors.email }}</p>
            </div>
            <button class="btn btn-primary w-full" :disabled="form.processing">Send reset link</button>
        </form>

        <p class="mt-5 text-center text-sm text-zinc-500">
            <Link href="/login" class="font-semibold text-teal-700">Back to sign in</Link>
        </p>
    </AuthLayout>
</template>
