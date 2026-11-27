<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CorrectionForm from './Partials/CorrectionForm.vue';
import { Head, Link } from '@inertiajs/vue3';
import { day, identificationLabels, relationshipLabels, sexLabels, statusLabels } from '../Students/Partials/labels.js';

defineProps({
    student: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`${student.givenNames} ${student.familyNames}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ student.givenNames }} {{ student.familyNames }}</h1>
                <Link :href="route('my-students.index')" class="text-sm font-medium text-indigo-700">Volver</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Datos del estudiante</h3>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-gray-500">{{ identificationLabels[student.identificationType] }}</dt><dd class="text-gray-900">{{ student.identification }}</dd></div>
                        <div><dt class="text-gray-500">Fecha de nacimiento</dt><dd class="text-gray-900">{{ day(student.birthDate) }}</dd></div>
                        <div><dt class="text-gray-500">Sexo</dt><dd class="text-gray-900">{{ sexLabels[student.sex] }}</dd></div>
                        <div><dt class="text-gray-500">Estado</dt><dd class="text-gray-900">{{ statusLabels[student.status] }}</dd></div>
                        <div v-if="student.address"><dt class="text-gray-500">Dirección</dt><dd class="text-gray-900">{{ student.address }}</dd></div>
                        <div v-if="student.emergencyPhone"><dt class="text-gray-500">Teléfono de emergencia</dt><dd class="text-gray-900">{{ student.emergencyPhone }}</dd></div>
                    </dl>
                </section>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-1 text-lg font-medium text-gray-900">¿Algún dato está mal?</h3>
                    <p class="mb-4 text-sm text-gray-600">Cuéntale al plantel qué hay que corregir; lo revisará la administración.</p>
                    <CorrectionForm :student-id="student.id" />
                </section>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Representantes</h3>
                    <ul class="space-y-1 text-sm text-gray-800">
                        <li v-for="(guardian, index) in student.guardians" :key="index">
                            {{ guardian.name }} · {{ relationshipLabels[guardian.relationship] }}<span v-if="guardian.isLegal"> · representante legal</span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
