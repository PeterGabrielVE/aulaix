<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { statusLabels } from './Partials/labels.js';

const props = defineProps({
    students: { type: Array, required: true },
    filters: { type: Object, required: true },
    pagination: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const search = ref(props.filters.search);
const withdrawn = ref(props.filters.withdrawn);

const visit = (page = 1) => router.get(
    route('students.index'),
    { buscar: search.value || undefined, retirados: withdrawn.value ? 1 : undefined, pagina: page > 1 ? page : undefined },
    { preserveState: true, preserveScroll: true },
);
</script>

<template>
    <Head title="Estudiantes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Estudiantes</h2>
                <Link
                    v-if="canManage"
                    :href="route('students.create')"
                    class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                >
                    Registrar estudiante
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6">
                <form class="flex flex-col gap-3 sm:flex-row sm:items-center" @submit.prevent="visit()">
                    <TextInput v-model="search" type="search" class="block w-full" placeholder="Nombre, apellido o cédula" aria-label="Buscar estudiante" />
                    <label class="flex shrink-0 items-center gap-2 text-sm text-gray-700">
                        <input v-model="withdrawn" type="checkbox" class="rounded border-gray-300 text-indigo-600" @change="visit()" />
                        Incluir retirados
                    </label>
                </form>

                <ul class="divide-y divide-gray-100 bg-white shadow-sm sm:rounded-lg">
                    <li v-for="student in students" :key="student.id">
                        <Link :href="route('students.show', student.id)" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-gray-50">
                            <span>
                                <span class="block font-medium text-gray-900">{{ student.fullName }}</span>
                                <span class="block text-sm text-gray-600">{{ student.identification }}</span>
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="student.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                            >
                                {{ statusLabels[student.status] }}
                            </span>
                        </Link>
                    </li>
                    <li v-if="!students.length" class="px-4 py-6 text-sm text-gray-600">No hay estudiantes que coincidan.</li>
                </ul>

                <nav v-if="pagination.lastPage > 1" class="flex items-center justify-between text-sm" aria-label="Páginas">
                    <button type="button" class="text-indigo-700 disabled:text-gray-400" :disabled="pagination.page <= 1" @click="visit(pagination.page - 1)">Anterior</button>
                    <span class="text-gray-600">Página {{ pagination.page }} de {{ pagination.lastPage }} · {{ pagination.total }} estudiantes</span>
                    <button type="button" class="text-indigo-700 disabled:text-gray-400" :disabled="pagination.page >= pagination.lastPage" @click="visit(pagination.page + 1)">Siguiente</button>
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
