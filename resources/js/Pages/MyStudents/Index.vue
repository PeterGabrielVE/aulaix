<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CorrectionForm from './Partials/CorrectionForm.vue';
import { Head, Link } from '@inertiajs/vue3';
import { day, statusLabels } from '../Students/Partials/labels.js';

defineProps({
    students: { type: Array, required: true },
    corrections: { type: Array, required: true },
});
</script>

<template>
    <Head title="Mis representados" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Mis representados</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
                <ul class="divide-y divide-gray-100 bg-white shadow-sm sm:rounded-lg">
                    <li v-for="student in students" :key="student.id">
                        <Link :href="route('my-students.show', student.id)" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-gray-50">
                            <span>
                                <span class="block font-medium text-gray-900">{{ student.fullName }}</span>
                                <span class="block text-sm text-gray-600">{{ student.identification }}</span>
                            </span>
                            <span class="text-xs font-medium text-gray-600">{{ statusLabels[student.status] }}</span>
                        </Link>
                    </li>
                    <li v-if="!students.length" class="px-4 py-6 text-sm text-gray-600">No tienes estudiantes registrados en este plantel.</li>
                </ul>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-1 text-lg font-medium text-gray-900">¿Cambiaron tus datos?</h3>
                    <p class="mb-4 text-sm text-gray-600">Por ejemplo, tu teléfono o tu dirección.</p>
                    <CorrectionForm />
                </section>

                <section v-if="corrections.length" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Mis solicitudes de corrección</h3>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="correction in corrections" :key="correction.id" class="space-y-1 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-gray-600">{{ day(correction.submittedOn) }}<span v-if="correction.studentName"> · {{ correction.studentName }}</span></span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="correction.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'">
                                    {{ correction.status === 'pending' ? 'Pendiente' : 'Atendida' }}
                                </span>
                            </div>
                            <p class="whitespace-pre-line text-gray-800">{{ correction.text }}</p>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
