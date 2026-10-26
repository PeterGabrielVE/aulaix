<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// SPEC-014 PLT-R01.10: the ten recovery codes, shown this once.
defineProps({
    codes: { type: Array, required: true },
});
</script>

<template>
    <Head title="Códigos de recuperación" />

    <PlatformLayout title="Guarda tus códigos de recuperación">
        <p class="text-sm text-gray-700">
            Cada código sirve una sola vez si pierdes tu app de autenticación. Guárdalos en un lugar seguro:
            no volverás a verlos.
        </p>
        <ul class="mt-4 grid grid-cols-2 gap-2 font-mono text-sm text-gray-900">
            <li v-for="code in codes" :key="code" class="rounded bg-gray-100 px-2 py-1">{{ code }}</li>
        </ul>
        <Link :href="route('platform.login')" class="mt-6 inline-block text-sm font-medium text-indigo-700 hover:underline">
            Ya los guardé, ir a entrar
        </Link>
    </PlatformLayout>
</template>
