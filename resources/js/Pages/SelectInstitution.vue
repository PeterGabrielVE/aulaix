<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const appDomain = usePage().props.appDomain;

const query = ref('');
const results = ref([]);
const loading = ref(false);
const searched = ref(false);

let debounceTimer = null;

async function search() {
    loading.value = true;

    try {
        const response = await fetch(
            `/api/institutions/search?q=${encodeURIComponent(query.value)}`,
            { headers: { Accept: 'application/json' } },
        );
        const payload = await response.json();
        results.value = payload.data;
    } finally {
        loading.value = false;
        searched.value = true;
    }
}

watch(query, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(search, 300);
});

search();

function goToInstitution(subdomain) {
    window.location.href = `${window.location.protocol}//${subdomain}.${appDomain}/login`;
}
</script>

<template>
    <GuestLayout>
        <Head title="Selecciona tu institución" />

        <h1 class="mb-1 text-lg font-semibold text-gray-900">
            Bienvenido a AulaX
        </h1>
        <p class="mb-4 text-sm text-gray-600">
            Busca tu institución para continuar al inicio de sesión.
        </p>

        <TextInput
            v-model="query"
            type="search"
            placeholder="Nombre de la institución..."
            class="block w-full"
            autofocus
        />

        <p v-if="loading" class="mt-4 text-sm text-gray-500">Buscando...</p>

        <ul v-else-if="results.length" class="mt-4 divide-y divide-gray-100">
            <li v-for="institution in results" :key="institution.subdomain">
                <button
                    type="button"
                    class="w-full rounded-md px-2 py-3 text-left hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                    @click="goToInstitution(institution.subdomain)"
                >
                    <span class="block font-medium text-gray-900">{{ institution.name }}</span>
                    <span class="block text-xs text-gray-500">{{ institution.subdomain }}.{{ appDomain }}</span>
                </button>
            </li>
        </ul>

        <p v-else-if="searched" class="mt-4 text-sm text-gray-500">
            No se encontraron instituciones.
        </p>
    </GuestLayout>
</template>
