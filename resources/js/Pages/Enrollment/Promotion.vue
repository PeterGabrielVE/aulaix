<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    sheet: { type: Object, required: true },
    outcomeLabels: { type: Object, required: true },
});

const flash = computed(() => usePage().props.flash?.success);
const editing = ref(null);
const form = useForm({ outcome: '', pending_subjects: [] });

const edit = (student) => {
    editing.value = student.enrollmentId;
    form.clearErrors();
    form.outcome = student.outcome ?? '';
    form.pending_subjects = [...student.pendingSubjects];
};

const save = () => form.put(route('promotion.update', editing.value), {
    preserveScroll: true,
    onSuccess: () => { editing.value = null; },
});

const subjectName = (id) => props.sheet.subjects.find((subject) => subject.id === id)?.name ?? id;
const missing = computed(() => props.sheet.students.filter((student) => !student.outcome).length);
</script>

<template>
    <Head :title="`Prosecución · ${sheet.section.gradeName} ${sheet.section.letter}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">Prosecución · {{ sheet.section.gradeName }} · Sección {{ sheet.section.letter }}</h1>
                <Link :href="route('enrollments.section', { year: sheet.schoolYearId, section: sheet.section.id })" class="text-sm text-indigo-700 hover:underline">Volver a la sección</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6">
                <p v-if="flash" role="status" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash }}</p>
                <p v-if="!sheet.ready" class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Los resultados se registran con el año activo y su tercer lapso cerrado.
                </p>
                <p class="text-sm text-gray-700">{{ missing }} de {{ sheet.students.length }} estudiantes sin resultado.</p>

                <ul class="divide-y divide-gray-100 bg-white shadow-sm sm:rounded-lg">
                    <li v-for="student in sheet.students" :key="student.enrollmentId" class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span>
                                <span class="block font-medium text-gray-900">{{ student.fullName }}</span>
                                <span class="block text-sm text-gray-600">{{ student.identification }}</span>
                            </span>
                            <span class="flex flex-wrap items-center gap-3 text-sm">
                                <span v-if="student.outcome" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">
                                    {{ outcomeLabels[student.outcome] }}<template v-if="student.pendingSubjects.length">: {{ student.pendingSubjects.map(subjectName).join(', ') }}</template>
                                </span>
                                <span v-else class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Sin resultado</span>
                                <button v-if="sheet.ready" type="button" class="font-medium text-indigo-700 hover:underline" @click="edit(student)">
                                    {{ student.outcome ? 'Corregir' : 'Registrar' }}
                                </button>
                            </span>
                        </div>

                        <form v-if="editing === student.enrollmentId" class="mt-3 space-y-3" @submit.prevent="save">
                            <fieldset>
                                <legend class="text-sm font-medium text-gray-700">Resultado</legend>
                                <div class="mt-1 flex flex-wrap gap-4">
                                    <label v-for="outcome in sheet.outcomes" :key="outcome" class="flex items-center gap-2 text-sm text-gray-700">
                                        <input v-model="form.outcome" type="radio" :value="outcome" class="border-gray-300 text-indigo-600" />
                                        {{ outcomeLabels[outcome] }}
                                    </label>
                                </div>
                                <InputError :message="form.errors.outcome" />
                            </fieldset>
                            <fieldset v-if="form.outcome === 'promoted_with_pending'">
                                <legend class="text-sm font-medium text-gray-700">Materias pendientes (una o dos)</legend>
                                <div class="mt-1 grid gap-1 sm:grid-cols-2">
                                    <label v-for="subject in sheet.subjects" :key="subject.id" class="flex items-center gap-2 text-sm text-gray-700">
                                        <input v-model="form.pending_subjects" type="checkbox" :value="subject.id" class="rounded border-gray-300 text-indigo-600" />
                                        {{ subject.name }}
                                    </label>
                                </div>
                                <InputError :message="form.errors.pending_subjects" />
                            </fieldset>
                            <PrimaryButton :disabled="form.processing">Guardar resultado</PrimaryButton>
                        </form>
                    </li>
                    <li v-if="!sheet.students.length" class="px-4 py-6 text-sm text-gray-600">No hay estudiantes inscritos en esta sección.</li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
