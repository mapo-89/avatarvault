<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { trans as t } from 'laravel-vue-i18n'
import AppLayout from '@core-panel/layouts/AppLayout.vue'

const props = defineProps<{
    avatar: { url: string; hash: string; uploaded: boolean; version: string }
}>()
const page = usePage<{
    auth: { user: { firstName: string; email: string } }
}>()
const accountForm = useForm({ password: '' })
const form = useForm<{ avatar: File | null }>({ avatar: null })
const fileInput = ref<HTMLInputElement | null>(null)
const preview = ref<string | null>(null)
const copyStatus = ref('')
const deleting = ref(false)
const imageUrl = computed(
    () =>
        preview.value ??
        `${props.avatar.url}?v=${encodeURIComponent(props.avatar.version)}`,
)
const snippet = computed(
    () => `<img src="${props.avatar.url}" alt="Avatar" width="80" height="80">`,
)

function clearPreview() {
    if (preview.value) URL.revokeObjectURL(preview.value)
    preview.value = null
    form.reset()
    if (fileInput.value) fileInput.value.value = ''
}
function selectPhoto(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0]
    clearPreview()
    if (!file) return
    form.avatar = file
    preview.value = URL.createObjectURL(file)
}
function upload() {
    form.post('/profile/avatar', {
        preserveScroll: true,
        onSuccess: clearPreview,
    })
}
function remove() {
    if (!window.confirm(t('avatarvault.remove_confirmation'))) return
    deleting.value = true
    router.delete('/profile/avatar', {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            clearPreview()
        },
    })
}
async function copy(value: string) {
    try {
        await navigator.clipboard.writeText(value)
        copyStatus.value = t('avatarvault.copied')
    } catch {
        copyStatus.value = t('avatarvault.copy_failed')
    }
}
onBeforeUnmount(() => {
    if (preview.value) URL.revokeObjectURL(preview.value)
})
</script>

<template>
    <AppLayout
        :title="t('avatarvault.dashboard')"
        :subtitle="t('avatarvault.dashboard_description')"
    >
        <Head :title="t('avatarvault.dashboard')" />
        <div class="vault-dashboard">
            <div class="vault-dashboard-heading">
                <span class="vault-eyebrow">AVATARVAULT</span>
                <h1>
                    {{ t('avatarvault.welcome') }},
                    {{ page.props.auth.user.firstName }}.
                </h1>
                <p>{{ t('avatarvault.dashboard_description') }}</p>
            </div>
            <div class="vault-dashboard-grid">
                <section class="vault-panel">
                    <div class="vault-panel-heading">
                        <h2>{{ t('avatarvault.your_avatar') }}</h2>
                        <span class="vault-chip">{{
                            props.avatar.uploaded
                                ? t('avatarvault.active')
                                : t('avatarvault.default_avatar')
                        }}</span>
                    </div>
                    <div class="vault-avatar-stage">
                        <img
                            :src="imageUrl"
                            :alt="t('avatarvault.your_avatar')"
                        /><span>{{ page.props.auth.user.email }}</span>
                    </div>
                    <form @submit.prevent="upload">
                        <label for="avatar-file" class="vault-file-label">{{
                            t('avatarvault.choose_photo')
                        }}</label>
                        <input
                            id="avatar-file"
                            ref="fileInput"
                            name="avatar"
                            type="file"
                            accept="image/jpeg,image/png,image/gif,image/webp"
                            :disabled="form.processing || deleting"
                            @change="selectPhoto"
                        />
                        <p class="vault-note">
                            JPG, PNG, GIF, WebP ·
                            {{ t('avatarvault.max_size') }}
                        </p>
                        <p
                            v-if="form.errors.avatar"
                            role="alert"
                            class="vault-error"
                        >
                            {{ form.errors.avatar }}
                        </p>
                        <progress
                            v-if="form.progress"
                            :value="form.progress.percentage"
                            max="100"
                            :aria-label="t('avatarvault.uploading')"
                        ></progress>
                        <div class="vault-actions">
                            <button
                                type="submit"
                                class="vault-primary"
                                :disabled="
                                    !form.avatar || form.processing || deleting
                                "
                            >
                                {{
                                    form.processing
                                        ? t('avatarvault.uploading')
                                        : t('avatarvault.save')
                                }}
                            </button>
                            <button
                                v-if="form.avatar"
                                type="button"
                                class="vault-text-link"
                                :disabled="form.processing"
                                @click="clearPreview"
                            >
                                {{ t('avatarvault.cancel') }}
                            </button>
                            <button
                                v-else-if="props.avatar.uploaded"
                                type="button"
                                class="vault-text-link"
                                :disabled="deleting"
                                @click="remove"
                            >
                                {{ t('avatarvault.remove') }}
                            </button>
                        </div>
                    </form>
                </section>
                <section class="vault-panel">
                    <div class="vault-panel-heading">
                        <h2>{{ t('avatarvault.embed_title') }}</h2>
                        <span aria-hidden="true">↗</span>
                    </div>
                    <p class="vault-muted">
                        {{ t('avatarvault.embed_description') }}
                    </p>
                    <label for="avatar-url" class="vault-file-label">{{
                        t('avatarvault.avatar_url')
                    }}</label>
                    <div class="vault-copy-row">
                        <input
                            id="avatar-url"
                            :value="props.avatar.url"
                            readonly
                            @focus="
                                ($event.target as HTMLInputElement).select()
                            "
                        /><button
                            type="button"
                            :aria-label="t('avatarvault.copy_url')"
                            @click="copy(props.avatar.url)"
                        >
                            {{ t('avatarvault.copy') }}
                        </button>
                    </div>
                    <label for="avatar-html" class="vault-file-label"
                        >HTML</label
                    >
                    <textarea
                        id="avatar-html"
                        :value="snippet"
                        readonly
                        rows="3"
                        @focus="($event.target as HTMLTextAreaElement).select()"
                    ></textarea>
                    <button
                        type="button"
                        class="vault-secondary"
                        @click="copy(snippet)"
                    >
                        {{ t('avatarvault.copy_html') }}
                    </button>
                    <p role="status" aria-live="polite" class="vault-note">
                        {{ copyStatus }}
                    </p>
                    <div class="vault-integration-note">
                        <strong>{{ t('avatarvault.hash_title') }}</strong>
                        <p>{{ t('avatarvault.hash_description') }}</p>
                        <code>{{ props.avatar.hash }}</code>
                    </div>
                    <Link href="/profile" class="vault-text-link"
                        >{{ t('avatarvault.profile_settings') }} →</Link
                    >
                </section>
            </div>
            <details class="vault-panel mt-6">
                <summary class="vault-text-link">
                    {{ t('avatarvault.delete_account') }}
                </summary>
                <p class="vault-note">
                    {{ t('avatarvault.delete_account_warning') }}
                </p>
                <form @submit.prevent="accountForm.delete('/profile/account')">
                    <label for="delete-password" class="vault-file-label">{{
                        t('auth.password_label')
                    }}</label>
                    <input
                        id="delete-password"
                        v-model="accountForm.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="rounded-lg border p-3"
                    />
                    <p
                        v-if="accountForm.errors.password"
                        class="vault-error"
                        role="alert"
                    >
                        {{ accountForm.errors.password }}
                    </p>
                    <button
                        class="vault-secondary ml-3"
                        type="submit"
                        :disabled="accountForm.processing"
                    >
                        {{ t('avatarvault.delete_account') }}
                    </button>
                </form>
            </details>
        </div>
    </AppLayout>
</template>
