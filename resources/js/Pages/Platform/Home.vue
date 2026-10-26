<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// SPEC-014: the panel's landing page. Institutions, consolidations and the
// log arrive with the next PRs.
const operator = computed(() => usePage().props.platform?.operator);
const regenerate = () => router.post(route('platform.recovery-codes.regenerate'));
</script>

<template>
    <Head title="Panel de plataforma" />

    <PlatformLayout title="Panel de plataforma">
        <ul class="grid gap-4 sm:grid-cols-2">
            <li v-if="operator?.role === 'super_admin'">
                <Link :href="route('platform.operators')" class="block h-full rounded-lg bg-white p-5 shadow-xs hover:bg-gray-50 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600">
                    <span class="block font-medium text-gray-900">Operadores</span>
                    <span class="mt-1 block text-sm text-gray-700">Invitar, cambiar de nivel, desactivar y restablecer el segundo factor.</span>
                </Link>
            </li>
            <li>
                <button type="button" class="block h-full w-full rounded-lg bg-white p-5 text-start shadow-xs hover:bg-gray-50 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600" @click="regenerate">
                    <span class="block font-medium text-gray-900">Nuevos códigos de recuperación</span>
                    <span class="mt-1 block text-sm text-gray-700">Sustituye los tuyos; los anteriores dejan de servir.</span>
                </button>
            </li>
        </ul>
    </PlatformLayout>
</template>
