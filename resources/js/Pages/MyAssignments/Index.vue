<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    assignments: { type: Object, required: true },
});

// SPEC-011: a class of Media General opens its sheet, in the open term.
const term = computed(() => usePage().props.layout?.calendar?.term ?? 1);
</script>

<template>
    <Head title="Mis asignaciones" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">Mis asignaciones</h1>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
                <p v-if="!assignments.schoolYearId" class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">
                    No hay un año escolar activo.
                </p>

                <template v-else>
                    <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                        <h3 class="mb-3 text-lg font-medium text-gray-900">Materias que dictas</h3>
                        <ul class="divide-y divide-gray-100 text-sm">
                            <li v-for="item in assignments.teaching" :key="`${item.sectionId}-${item.subjectId}`" class="flex flex-wrap justify-between gap-2 py-2">
                                <Link
                                    v-if="item.level === 'media_general'"
                                    :href="route('grades.sheet', { year: assignments.schoolYearId, section: item.sectionId, subject: item.subjectId, term })"
                                    class="font-medium text-indigo-700 hover:underline"
                                >
                                    {{ item.subject }}
                                </Link>
                                <span v-else class="font-medium text-gray-900">{{ item.subject }}</span>
                                <span class="text-gray-600">{{ item.section }}</span>
                            </li>
                            <li v-if="!assignments.teaching.length" class="py-2 text-gray-600">No tienes materias asignadas.</li>
                        </ul>
                    </section>

                    <section v-if="assignments.guiding.length" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                        <h3 class="mb-3 text-lg font-medium text-gray-900">Secciones que guías</h3>
                        <ul class="space-y-1 text-sm text-gray-800">
                            <li v-for="item in assignments.guiding" :key="item.sectionId">
                                <Link
                                    v-if="item.level !== 'media_general'"
                                    :href="route('grades.marks', { year: assignments.schoolYearId, section: item.sectionId })"
                                    class="text-indigo-700 hover:underline"
                                >
                                    {{ item.section }} · {{ item.level === 'primaria' ? 'Literales' : 'Informes' }}
                                </Link>
                                <template v-else>{{ item.section }}</template>
                            </li>
                        </ul>
                    </section>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
