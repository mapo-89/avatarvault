<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { trans as t } from 'laravel-vue-i18n'
const form = useForm({ password: '' })
</script>

<template>
    <div
        class="vault-landing min-h-screen flex items-center justify-center p-6"
    >
        <Head :title="t('auth.confirm_password')" />
        <form
            class="vault-panel w-full max-w-md"
            @submit.prevent="form.post('/user/confirm-password')"
        >
            <img
                src="/images/brand/icon.svg"
                alt="AvatarVault"
                width="48"
                height="48"
            />
            <h1 class="text-xl font-semibold my-6">
                {{ t('auth.confirm_password') }}
            </h1>
            <label for="confirm-password" class="vault-file-label">{{
                t('auth.password_label')
            }}</label>
            <input
                id="confirm-password"
                v-model="form.password"
                type="password"
                autocomplete="current-password"
                required
                autofocus
                class="w-full rounded-lg border p-3"
            />
            <p v-if="form.errors.password" role="alert" class="vault-error">
                {{ form.errors.password }}
            </p>
            <button
                type="submit"
                class="vault-primary mt-6"
                :disabled="form.processing"
            >
                {{ t('auth.confirm_password') }}
            </button>
        </form>
    </div>
</template>
