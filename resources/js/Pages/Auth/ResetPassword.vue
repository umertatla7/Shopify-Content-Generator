<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/reset-password');
</script>

<template>
    <Head title="Reset Password" />
    <AuthLayout>
        <div class="mb-6">
            <h1 class="text-xl font-bold text-zinc-950">New password</h1>
            <p class="mt-1 text-sm text-zinc-500">Set a new login password.</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label>Email</label>
                <input v-model="form.email" type="email" autocomplete="email" />
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-700">{{ form.errors.email }}</p>
            </div>
            <div>
                <label>Password</label>
                <input v-model="form.password" type="password" autocomplete="new-password" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-700">{{ form.errors.password }}</p>
            </div>
            <div>
                <label>Confirm password</label>
                <input v-model="form.password_confirmation" type="password" autocomplete="new-password" />
            </div>
            <button class="btn btn-primary w-full" :disabled="form.processing">Save password</button>
        </form>

        <p class="mt-5 text-center text-sm text-zinc-500">
            <Link href="/login" class="font-semibold text-teal-700">Back to sign in</Link>
        </p>
    </AuthLayout>
</template>
