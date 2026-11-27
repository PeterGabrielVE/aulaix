<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

// SPEC-014 PLT-R04.1, R04.2: every institution, searched by name or
// subdomain and filtered by status.
const props = defineProps({
    institutions: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const form = reactive({ search: props.filters.search, status: props.filters.status });
const statusNames = { active: 'Activo', inactive: 'Inactivo' };
const typeNames = { publica: 'Pública', privada: 'Privada', subvencionada: 'Subvencionada' };

const lastPage = computed(() => Math.max(1, Math.ceil(props.institutions.total / props.institutions.perPage)));
const query = (page = 1) => {
    const params = { page };
    if (form.search) params.search = form.search;
    if (form.status) params.status = form.status;

    return params;
};
const search = () => router.get(route('platform.institutions'), query(), { preserveState: true, replace: true });
const goTo = (page) => router.get(route('platform.institutions'), query(page), { preserveState: true });
</script>

<template>
    <Head title="Planteles" />

    <PlatformLayout title="Planteles">
        <section aria-labelledby="buscar" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="buscar" class="sr-only">Buscar planteles</h2>
            <form class="grid gap-3 sm:grid-cols-[1fr_12rem_auto] sm:items-end" role="search" @submit.prevent="search">
                <div>
                    <InputLabel for="search" value="Nombre o subdominio" />
                    <TextInput id="search" v-model="form.search" type="search" class="mt-1 block w-full" maxlength="100" />
                </div>
                <div>
                    <InputLabel for="status" value="Estado" />
                    <select id="status" v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600">Buscar</button>
            </form>
        </section>

        <section aria-labelledby="lista" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="lista" class="text-lg font-medium text-gray-900">{{ institutions.total }} {{ institutions.total === 1 ? 'plantel' : 'planteles' }}</h2>
            <p v-if="institutions.items.length === 0" class="mt-3 text-sm text-gray-700">Ningún plantel coincide con la búsqueda.</p>
            <div v-else class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-start text-gray-700">
                            <th scope="col" class="py-2 pe-4 text-start font-medium">Nombre</th>
                            <th scope="col" class="py-2 pe-4 text-start font-medium">Subdominio</th>
                            <th scope="col" class="py-2 pe-4 text-start font-medium">Tipo</th>
                            <th scope="col" class="py-2 pe-4 text-start font-medium">Estado</th>
                            <th scope="col" class="py-2 text-start font-medium">Alta</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="institution in institutions.items" :key="institution.id">
                            <td class="py-2 pe-4">
                                <Link :href="route('platform.institutions.show', institution.id)" class="font-medium text-indigo-700 hover:underline">{{ institution.name }}</Link>
                            </td>
                            <td class="py-2 pe-4 text-gray-700">{{ institution.subdomain }}</td>
                            <td class="py-2 pe-4 text-gray-700">{{ typeNames[institution.type] ?? '—' }}</td>
                            <td class="py-2 pe-4">
                                <span :class="institution.status === 'active' ? 'text-green-800' : 'text-red-800'">{{ statusNames[institution.status] }}</span>
                            </td>
                            <td class="py-2 text-gray-700">{{ institution.createdAt }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav v-if="lastPage > 1" aria-label="Páginas" class="mt-4 flex items-center gap-3 text-sm">
                <button type="button" class="rounded-md px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-50 disabled:text-gray-400" :disabled="institutions.page <= 1" @click="goTo(institutions.page - 1)">Anterior</button>
                <span class="text-gray-700">Página {{ institutions.page }} de {{ lastPage }}</span>
                <button type="button" class="rounded-md px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-50 disabled:text-gray-400" :disabled="institutions.page >= lastPage" @click="goTo(institutions.page + 1)">Siguiente</button>
            </nav>
        </section>
    </PlatformLayout>
</template>
