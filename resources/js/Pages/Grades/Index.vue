<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    list: { type: Object, required: true },
});
</script>

<template>
    <Head title="Notas" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">Notas</h1>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6">
                <p v-if="!list.schoolYearId" class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">
                    No hay un año escolar activo.
                </p>

                <section v-else-if="list.sheets.length || !list.sections.length" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h2 class="mb-1 text-lg font-medium text-gray-900">Planillas de Media General</h2>
                    <p class="mb-3 text-sm text-gray-600">Se abren en el lapso {{ list.term }}; dentro de cada planilla puedes cambiar de lapso.</p>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="sheet in list.sheets" :key="`${sheet.sectionId}-${sheet.subjectId}`">
                            <Link
                                :href="route('grades.sheet', { year: list.schoolYearId, section: sheet.sectionId, subject: sheet.subjectId, term: list.term })"
                                class="flex flex-wrap justify-between gap-2 py-2 hover:underline"
                            >
                                <span class="font-medium text-gray-900">{{ sheet.subject }}</span>
                                <span class="text-gray-600">
                                    {{ sheet.section }}
                                    <span v-if="sheet.teaches" class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">La dictas</span>
                                </span>
                            </Link>
                        </li>
                        <li v-if="!list.sheets.length" class="py-2 text-gray-600">No tienes planillas en este año escolar.</li>
                    </ul>
                </section>

                <section v-if="list.schoolYearId && (list.pendingGroups.length || list.managesPendingGroups)" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-medium text-gray-900">Materias pendientes</h2>
                        <Link v-if="list.managesPendingGroups" :href="route('grades.pending.index')" class="text-sm text-indigo-700 hover:underline">Grupos de pendientes</Link>
                    </div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="group in list.pendingGroups" :key="group.groupId">
                            <Link :href="route('grades.pending.sheet', { group: group.groupId, term: list.term })" class="flex flex-wrap justify-between gap-2 py-2 hover:underline">
                                <span class="font-medium text-gray-900">{{ group.subject }}</span>
                                <span class="text-gray-600">Pendientes de {{ group.originGrade }}</span>
                            </Link>
                        </li>
                        <li v-if="!list.pendingGroups.length" class="py-2 text-gray-600">No tienes grupos de pendientes asignados.</li>
                    </ul>
                </section>

                <section v-if="list.schoolYearId && list.sections.length" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h2 class="mb-3 text-lg font-medium text-gray-900">Literales e informes</h2>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="section in list.sections" :key="section.sectionId">
                            <Link
                                :href="route('grades.marks', { year: list.schoolYearId, section: section.sectionId })"
                                class="flex flex-wrap justify-between gap-2 py-2 hover:underline"
                            >
                                <span class="font-medium text-gray-900">{{ section.section }}</span>
                                <span class="text-gray-600">
                                    {{ section.kind === 'literal' ? 'Literales' : 'Informes' }}
                                    <span v-if="section.guides" class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">La guías</span>
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
