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

// People (EAC-R05, R06, R08.5).
const personName = (accountId) => props.structure.people[accountId]?.name ?? '—';
const isInactive = (accountId) => accountId !== null && accountId !== undefined && props.structure.people[accountId]?.active === false;
const sectionById = (id) => props.structure.sections.find((section) => section.id === id);
const subjectName = (subjectId) => Object.values(props.structure.curriculum).flat().find((subject) => subject.id === subjectId)?.name ?? '';

const assignmentErrors = ref({});
const send = (method, url, data = {}) => router[method](url, data, {
    preserveScroll: true,
    onStart: () => { assignmentErrors.value = {}; },
    onError: (errors) => { assignmentErrors.value = errors; },
});
const accountOf = (event) => (event.target.value === '' ? null : Number(event.target.value));
const assignTeacher = (section, subject, event) => send('put', route('structure.teacher', [section.id, subject.id]), { account_id: accountOf(event) });
const setGuide = (section, event) => send('put', route('structure.guide', section.id), { account_id: accountOf(event) });

const levelLabels = { inicial: 'Educación Inicial', primaria: 'Primaria', media_general: 'Media General' };
const levels = computed(() => [...new Set(props.structure.grades.map((grade) => grade.level))]);
const gradeName = (code) => props.structure.grades.find((grade) => grade.code === code)?.name ?? code;
const coordinatorForm = useForm({ account_id: '', level: '', grades: [] });
const gradesOfLevel = computed(() => props.structure.grades.filter((grade) => grade.level === coordinatorForm.level));
const assignCoordinator = () => coordinatorForm.post(route('structure.coordinators.store', props.structure.schoolYearId), {
    preserveScroll: true,
    onSuccess: () => coordinatorForm.reset(),
});
const removeCoordinator = (coordinator) => send('delete', route('structure.coordinators.destroy', [props.structure.schoolYearId, coordinator.accountId, coordinator.level]));

const selectClass ='mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
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

                    <section v-if="structure.orphans.length" class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                        <p class="font-medium">Asignaciones de personas que ya no están activas en el plantel:</p>
                        <ul class="mt-2 list-disc space-y-1 ps-5">
                            <li v-for="orphan in structure.orphans" :key="`${orphan.sectionId}-${orphan.subjectId}`">
                                {{ personName(orphan.accountId) }} ·
                                {{ sectionById(orphan.sectionId)?.gradeName }} «{{ sectionById(orphan.sectionId)?.letter }}» ·
                                {{ orphan.subjectId === null ? 'docente guía' : subjectName(orphan.subjectId) }}
                            </li>
                        </ul>
                    </section>
                    <InputError :message="assignmentErrors.account_id || assignmentErrors.school_year || assignmentErrors.section" />

                    <section v-if="structure.grades.length" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900">Coordinadores</h3>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li v-for="coordinator in structure.coordinators" :key="`${coordinator.accountId}-${coordinator.level}`" class="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    <span class="font-medium text-gray-900">{{ personName(coordinator.accountId) }}</span>
                                    <span v-if="isInactive(coordinator.accountId)" class="text-red-700"> (inactivo)</span>
                                    · {{ levelLabels[coordinator.level] ?? coordinator.level }}
                                    <span v-if="coordinator.grades.length" class="text-gray-600">({{ coordinator.grades.map(gradeName).join(', ') }})</span>
                                </span>
                                <button v-if="editable" type="button" class="text-sm text-red-700" @click="removeCoordinator(coordinator)">Quitar</button>
                            </li>
                            <li v-if="!structure.coordinators.length" class="text-gray-500">Sin coordinadores.</li>
                        </ul>

                        <form v-if="editable" class="mt-4 grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-3 sm:items-end" @submit.prevent="assignCoordinator">
                            <div>
                                <InputLabel for="coordinator" value="Coordinador" />
                                <select id="coordinator" v-model="coordinatorForm.account_id" :class="selectClass" required>
                                    <option value="" disabled>Elige una persona</option>
                                    <option v-for="person in structure.coordinatorCandidates" :key="person.id" :value="person.id">{{ person.name }}</option>
                                </select>
                                <InputError :message="coordinatorForm.errors.account_id" />
                            </div>
                            <div>
                                <InputLabel for="coordinator_level" value="Nivel" />
                                <select id="coordinator_level" v-model="coordinatorForm.level" :class="selectClass" required @change="coordinatorForm.grades = []">
                                    <option value="" disabled>Elige un nivel</option>
                                    <option v-for="level in levels" :key="level" :value="level">{{ levelLabels[level] ?? level }}</option>
                                </select>
                                <InputError :message="coordinatorForm.errors.level" />
                            </div>
                            <PrimaryButton :disabled="coordinatorForm.processing">Asignar</PrimaryButton>
                            <fieldset v-if="coordinatorForm.level" class="sm:col-span-3">
                                <legend class="text-sm text-gray-700">Grados a su cargo (ninguno: todo el nivel)</legend>
                                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                    <label v-for="grade in gradesOfLevel" :key="grade.code" class="flex items-center gap-2 text-sm text-gray-800">
                                        <input v-model="coordinatorForm.grades" type="checkbox" :value="grade.code" class="rounded border-gray-300 text-indigo-600" />
                                        {{ grade.name }}
                                    </label>
                                </div>
                                <InputError :message="coordinatorForm.errors.grades" />
                            </fieldset>
                        </form>
                    </section>

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

                        <div v-for="section in sectionsOf(grade)" :key="`people-${section.id}`" class="mt-4 rounded-md border border-gray-100 p-3">
                            <h4 class="text-sm font-medium text-gray-900">Sección {{ section.letter }}</h4>
                            <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-gray-600">Docente guía</dt>
                                    <dd>
                                        <select v-if="editable" :value="section.guideAccountId ?? ''" :class="selectClass" :aria-label="`Docente guía de la sección ${section.letter}`" @change="setGuide(section, $event)">
                                            <option value="">Sin asignar</option>
                                            <option v-if="isInactive(section.guideAccountId)" :value="section.guideAccountId">{{ personName(section.guideAccountId) }} (inactivo)</option>
                                            <option v-for="person in structure.teacherCandidates" :key="person.id" :value="person.id">{{ person.name }}</option>
                                        </select>
                                        <span v-else :class="{ 'text-red-700': isInactive(section.guideAccountId) }">{{ section.guideAccountId ? personName(section.guideAccountId) : 'Sin asignar' }}</span>
                                    </dd>
                                </div>
                                <div v-for="subject in curriculumOf(grade)" :key="subject.id">
                                    <dt class="text-gray-600">{{ subject.name }}</dt>
                                    <dd>
                                        <select v-if="editable" :value="section.teachers[subject.id] ?? ''" :class="selectClass" :aria-label="`Docente de ${subject.name}, sección ${section.letter}`" @change="assignTeacher(section, subject, $event)">
                                            <option value="">Sin asignar</option>
                                            <option v-if="isInactive(section.teachers[subject.id])" :value="section.teachers[subject.id]">{{ personName(section.teachers[subject.id]) }} (inactivo)</option>
                                            <option v-for="person in structure.teacherCandidates" :key="person.id" :value="person.id">{{ person.name }}</option>
                                        </select>
                                        <span v-else :class="{ 'text-red-700': isInactive(section.teachers[subject.id]) }">{{ section.teachers[subject.id] ? personName(section.teachers[subject.id]) : 'Sin asignar' }}</span>
                                    </dd>
                                </div>
                            </dl>
                        </div>

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
