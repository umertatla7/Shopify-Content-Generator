<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const page = usePage();
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post('/login');
</script>

<template>
    <Head title="Login" />
    <AuthLayout>
        <div class="mb-6">
            <h1 class="text-xl font-bold text-zinc-950">Sign in</h1>
            <p class="mt-1 text-sm text-zinc-500">Access your Shopify content dashboard.</p>
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
            <div>
                <label>Password</label>
                <input v-model="form.password" type="password" autocomplete="current-password" />
            </div>
            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 normal-case tracking-normal text-zinc-600">
                    <input v-model="form.remember" type="checkbox" class="size-4 rounded border-zinc-300 p-0" />
                    Remember me
                </label>
                <Link href="/forgot-password" class="text-sm font-semibold text-teal-700">Forgot password?</Link>
            </div>
            <button class="btn btn-primary w-full" :disabled="form.processing">Sign in</button>
        </form>

        <p class="mt-5 text-center text-sm text-zinc-500">
            New workspace?
            <Link href="/register" class="font-semibold text-teal-700">Create account</Link>
        </p>
    </AuthLayout>
</template>
