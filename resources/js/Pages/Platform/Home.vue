<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head } from '@inertiajs/vue3';

// SPEC-014: the panel's landing page. Institutions, operators,
// consolidations and the log arrive with the next PRs.
</script>

<template>
    <Head title="Panel de plataforma" />

    <PlatformLayout title="Panel de plataforma">
        <p class="rounded-md bg-white p-4 text-sm text-gray-700 shadow-xs">
            Desde aquí se operan los planteles, los operadores y las consolidaciones de cuentas.
        </p>
    </PlatformLayout>
</template>
