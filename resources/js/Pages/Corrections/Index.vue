<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { day } from '../Students/Partials/labels.js';

defineProps({
    corrections: { type: Array, required: true },
});

const form = useForm({});
const resolve = (correction) => form.post(route('corrections.resolve', correction.id), { preserveScroll: true });
</script>

<template>
    <Head title="Correcciones" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">Correcciones pedidas por representantes</h1>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <p class="mb-4 text-sm text-gray-600">Corrige el dato en la ficha del estudiante o del representante y marca la solicitud como atendida.</p>
                <ul class="divide-y divide-gray-100 bg-white shadow-sm sm:rounded-lg">
                    <li v-for="correction in corrections" :key="correction.id" class="space-y-2 px-4 py-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium text-gray-900">{{ correction.guardianName }}<span v-if="correction.studentName"> · sobre {{ correction.studentName }}</span></span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="correction.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'">
                                {{ correction.status === 'pending' ? 'Pendiente' : 'Atendida' }}
                            </span>
                        </div>
                        <p class="whitespace-pre-line text-gray-800">{{ correction.text }}</p>
                        <p class="text-xs text-gray-500">Enviada el {{ day(correction.submittedOn) }}</p>
                        <PrimaryButton v-if="correction.status === 'pending'" type="button" :disabled="form.processing" @click="resolve(correction)">Marcar atendida</PrimaryButton>
                    </li>
                    <li v-if="!corrections.length" class="px-4 py-6 text-sm text-gray-600">No hay solicitudes de corrección.</li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
