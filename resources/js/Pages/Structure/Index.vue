<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    structure: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const statusLabels = { planned: 'Planificado', active: 'Activo', closed: 'Cerrado' };
const editable = computed(() => props.canManage && props.structure.status && props.structure.status !== 'closed');
const sectionsOf = (grade) => props.structure.sections.filter((section) => section.grade === grade.code);
const curriculumOf = (grade) => props.structure.curriculum[grade.code] ?? [];

const chooseYear = (event) => router.get(route('structure.show'), { anio: event.target.value }, { preserveScroll: true });

// One form for the grade being edited at a time.
const openGrade = ref(null);
const sectionForm = useForm({ grade: '', letter: '', capacity: '', capacity_reason: '' });
const curriculumForm = useForm({ subjects: [] });

const open = (grade) => {
    openGrade.value = grade.code;
    sectionForm.reset();
    sectionForm.clearErrors();
    sectionForm.grade = grade.code;
    sectionForm.capacity = grade.capacityCap;
    curriculumForm.subjects = curriculumOf(grade).map((subject) => subject.id);
    curriculumForm.clearErrors();
};

const overCap = (grade) => Number(sectionForm.capacity) > grade.capacityCap;
const addSection = () => sectionForm.post(route('structure.sections.store', props.structure.schoolYearId), {
    preserveScroll: true,
    onSuccess: () => sectionForm.reset('letter', 'capacity_reason'),
});
const removeSection = (section) => router.delete(route('structure.sections.destroy', section.id), { preserveScroll: true });
const saveCurriculum = (grade) => curriculumForm.put(route('structure.curriculum', [props.structure.schoolYearId, grade.code]), { preserveScroll: true });

const copyForm = useForm({});
const copyPrevious = () => copyForm.post(route('structure.copy', props.structure.schoolYearId), { preserveScroll: true });

const selectClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <Head title="Estructura" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Estructura del año escolar</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
                <p v-if="!structure.schoolYearId" class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">
                    El plantel aún no tiene años escolares: planifica uno en «Año escolar».
                </p>

                <template v-else>
                    <section class="flex flex-wrap items-end justify-between gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                        <div class="w-full sm:w-auto">
                            <InputLabel for="year" value="Año escolar" />
                            <select id="year" :value="structure.schoolYearId" :class="selectClass" @change="chooseYear">
                                <option v-for="year in structure.years" :key="year.id" :value="year.id">{{ year.name }} · {{ statusLabels[year.status] }}</option>
                            </select>
                        </div>
                        <SecondaryButton
                            v-if="canManage && structure.status === 'planned' && !structure.sections.length"
                            type="button"
                            :disabled="copyForm.processing"
                            @click="copyPrevious"
                        >
                            Copiar del año anterior
                        </SecondaryButton>
                    </section>
                    <InputError :message="copyForm.errors.school_year" />

                    <p v-if="!structure.grades.length" class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">
                        Indica en «Plantel» los niveles que ofrece el plantel para organizar sus grados.
                    </p>

                    <section v-for="grade in structure.grades" :key="grade.code" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-lg font-medium text-gray-900">{{ grade.name }}</h3>
                            <button v-if="editable && openGrade !== grade.code" type="button" class="text-sm font-medium text-indigo-700" @click="open(grade)">Editar</button>
                        </div>

                        <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                            <li v-for="section in sectionsOf(grade)" :key="section.id" class="rounded-full bg-gray-100 px-3 py-1 text-gray-800">
                                Sección {{ section.letter }} · cupo {{ section.capacity }}
                                <span v-if="section.capacityReason" :title="section.capacityReason"> (sobre el tope)</span>
                                <button v-if="editable && openGrade === grade.code" type="button" class="ms-1 text-red-700" :aria-label="`Quitar la sección ${section.letter}`" @click="removeSection(section)">×</button>
                            </li>
                            <li v-if="!sectionsOf(grade).length" class="text-gray-500">Sin secciones.</li>
                        </ul>

                        <p class="mt-3 text-sm text-gray-700">
                            <span class="font-medium">Pensum:</span>
                            {{ curriculumOf(grade).map((subject) => subject.name).join(', ') || 'sin materias' }}
                        </p>

                        <div v-if="openGrade === grade.code" class="mt-4 space-y-6 border-t border-gray-100 pt-4">
                            <form class="grid gap-3 sm:grid-cols-3 sm:items-end" @submit.prevent="addSection">
                                <div>
                                    <InputLabel :for="`letter_${grade.code}`" value="Nueva sección (letra)" />
                                    <TextInput :id="`letter_${grade.code}`" v-model="sectionForm.letter" class="mt-1 block w-full" maxlength="1" required />
                                    <InputError :message="sectionForm.errors.letter" />
                                </div>
                                <div>
                                    <InputLabel :for="`capacity_${grade.code}`" :value="`Cupo (tope ${grade.capacityCap})`" />
                                    <TextInput :id="`capacity_${grade.code}`" v-model="sectionForm.capacity" type="number" min="1" class="mt-1 block w-full" required />
                                    <InputError :message="sectionForm.errors.capacity" />
                                </div>
                                <PrimaryButton :disabled="sectionForm.processing">Añadir sección</PrimaryButton>
                                <div v-if="overCap(grade)" class="sm:col-span-3">
                                    <InputLabel :for="`reason_${grade.code}`" value="Motivo para superar el tope" />
                                    <TextInput :id="`reason_${grade.code}`" v-model="sectionForm.capacity_reason" class="mt-1 block w-full" maxlength="500" required />
                                    <InputError :message="sectionForm.errors.capacity_reason" />
                                </div>
                                <InputError class="sm:col-span-3" :message="sectionForm.errors.grade || sectionForm.errors.school_year" />
                            </form>

                            <form class="space-y-2" @submit.prevent="saveCurriculum(grade)">
                                <fieldset>
                                    <legend class="text-sm font-medium text-gray-700">Materias del pensum</legend>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        <label v-for="subject in structure.subjectOptions[grade.level] ?? []" :key="subject.id" class="flex items-center gap-2 text-sm text-gray-800">
                                            <input v-model="curriculumForm.subjects" type="checkbox" :value="subject.id" class="rounded border-gray-300 text-indigo-600" />
                                            {{ subject.name }}
                                        </label>
                                    </div>
                                </fieldset>
                                <InputError :message="curriculumForm.errors.subjects" />
                                <div class="flex flex-wrap gap-2">
                                    <PrimaryButton :disabled="curriculumForm.processing">Guardar pensum</PrimaryButton>
                                    <SecondaryButton type="button" @click="openGrade = null">Cerrar</SecondaryButton>
                                </div>
                            </form>
                        </div>
                    </section>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
