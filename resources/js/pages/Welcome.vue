<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { trans as t } from 'laravel-vue-i18n'

defineProps<{
    canRegister: boolean
    socialProviders: { provider: string; label: string }[]
}>()
const page = usePage<{ auth: { user: { firstName: string } | null } }>()
</script>

<template>
    <div class="vault-landing">
        <Head :title="t('avatarvault.home_title')" />
        <nav class="vault-nav" :aria-label="t('avatarvault.navigation')">
            <Link href="/" class="vault-brand">
                <img
                    src="/images/brand/icon.svg"
                    alt=""
                    width="40"
                    height="40"
                />
                <span>AvatarVault</span>
            </Link>
            <Link
                :href="page.props.auth.user ? '/dashboard' : '/login'"
                class="vault-text-link"
            >
                {{
                    page.props.auth.user
                        ? t('avatarvault.dashboard')
                        : t('avatarvault.login')
                }}
                <span aria-hidden="true">↗</span>
            </Link>
        </nav>
        <main>
            <section class="vault-hero">
                <div>
                    <span class="vault-eyebrow">{{
                        t('avatarvault.self_hosted')
                    }}</span>
                    <h1>
                        {{ t('avatarvault.hero_title') }}<br /><em>{{
                            t('avatarvault.hero_accent')
                        }}</em>
                    </h1>
                    <p class="vault-intro">
                        {{ t('avatarvault.hero_description') }}
                    </p>
                    <div class="vault-actions">
                        <Link
                            :href="
                                page.props.auth.user
                                    ? '/dashboard'
                                    : canRegister
                                      ? '/register'
                                      : '/login'
                            "
                            class="vault-primary"
                        >
                            {{ t('avatarvault.get_started') }}
                            <span aria-hidden="true">→</span>
                        </Link>
                        <a href="#how-it-works" class="vault-text-link">{{
                            t('avatarvault.how_it_works')
                        }}</a>
                    </div>
                    <div
                        v-if="!page.props.auth.user && socialProviders.length"
                        class="vault-social"
                    >
                        <a
                            v-for="provider in socialProviders"
                            :key="provider.provider"
                            :href="`/auth/${provider.provider}/redirect`"
                            class="vault-secondary"
                        >
                            {{ t('avatarvault.continue_with') }}
                            {{ provider.label }}
                        </a>
                    </div>
                    <p class="vault-note">{{ t('avatarvault.hero_note') }}</p>
                </div>
                <div class="vault-hero-art" aria-hidden="true">
                    <div class="vault-art-orbit"></div>
                    <span class="vault-satellite vault-satellite-one">JD</span>
                    <span class="vault-satellite vault-satellite-two">ML</span>
                    <div class="vault-preview-card">
                        <span class="vault-eyebrow">AVATARVAULT</span>
                        <img
                            src="/images/brand/icon.svg"
                            alt=""
                            class="vault-preview-icon"
                        />
                        <strong>{{ t('avatarvault.your_avatar') }}</strong>
                        <span class="vault-muted">{{
                            t('avatarvault.your_control')
                        }}</span>
                        <div class="vault-code-line">/avatar/7d793037…</div>
                        <span class="vault-status"
                            ><span></span
                            >{{ t('avatarvault.ready_everywhere') }}</span
                        >
                    </div>
                    <span class="vault-art-caption"
                        >ONE AVATAR. YOUR INFRASTRUCTURE.</span
                    >
                </div>
            </section>
            <section id="how-it-works" class="vault-steps">
                <article
                    v-for="step in ['upload', 'link', 'control']"
                    :key="step"
                >
                    <span class="vault-step-icon" aria-hidden="true">{{
                        step === 'upload' ? '↑' : step === 'link' ? '↗' : '✓'
                    }}</span>
                    <h2>{{ t(`avatarvault.${step}_title`) }}</h2>
                    <p>{{ t(`avatarvault.${step}_description`) }}</p>
                </article>
            </section>
        </main>
        <footer class="vault-footer">
            <span>AvatarVault</span><span>{{ t('avatarvault.footer') }}</span
            ><a href="https://github.com/mapo-89/avatar-vault">GitHub ↗</a>
        </footer>
    </div>
</template>
