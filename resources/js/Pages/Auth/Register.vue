<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({
    name: '',
    email: '',
    account_name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/register');
</script>

<template>
    <Head title="Register" />
    <AuthLayout>
        <div class="mb-6">
            <h1 class="text-xl font-bold text-zinc-950">Create account</h1>
            <p class="mt-1 text-sm text-zinc-500">Start your customer workspace.</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label>Name</label>
                <input v-model="form.name" autocomplete="name" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-rose-700">{{ form.errors.name }}</p>
            </div>
            <div>
                <label>Email</label>
                <input v-model="form.email" type="email" autocomplete="email" />
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-700">{{ form.errors.email }}</p>
            </div>
            <div>
                <label>Account name</label>
                <input v-model="form.account_name" />
                <p v-if="form.errors.account_name" class="mt-1 text-xs text-rose-700">{{ form.errors.account_name }}</p>
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
            <button class="btn btn-primary w-full" :disabled="form.processing">Create account</button>
        </form>

        <p class="mt-5 text-center text-sm text-zinc-500">
            Already registered?
            <Link href="/login" class="font-semibold text-teal-700">Sign in</Link>
        </p>
    </AuthLayout>
</template>
