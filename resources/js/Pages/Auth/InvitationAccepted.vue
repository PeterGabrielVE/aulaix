<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    institutionName: {
        type: String,
        required: true,
    },
    loginUrl: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <GuestLayout>
        <Head title="Invitación aceptada" />

        <h1 class="mb-1 text-lg font-semibold text-gray-900">
            ¡Listo! Ya formas parte de {{ institutionName }}
        </h1>
        <p class="mb-4 text-sm text-gray-600">
            Tu cuenta de AulaX está creada. Inicia sesión con tu correo y la
            contraseña que acabas de elegir.
        </p>

        <a
            :href="loginUrl"
            class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
            Iniciar sesión
        </a>
    </GuestLayout>
</template>
