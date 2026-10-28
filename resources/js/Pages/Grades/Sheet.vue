<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    sheet: { type: Object, required: true },
});

const flash = computed(() => usePage().props.flash?.success);
const canEdit = computed(() => props.sheet.canGrade && props.sheet.editable);
const termLink = (term) => route('grades.sheet', { year: props.sheet.schoolYearId, section: props.sheet.sectionId, subject: props.sheet.subjectId, term });
const stateText = { incomplete: 'Incompleta', not_applicable: 'No aplica' };

// The plan of activities (EVA-R01): weights add up to 100 %.
const editingPlan = ref(false);
const plan = useForm({ activities: [], confirm_removals: false });
const parseWeight = (weight) => Number(String(weight ?? '').replace(',', '.')) || 0;
const weightSum = computed(() => Math.round(plan.activities.reduce((sum, activity) => sum + parseWeight(activity.weight), 0) * 100) / 100);

const startPlan = () => {
    plan.activities = props.sheet.activities.map(({ id, name, date, weight }) => ({ id, name, date, weight }));
    if (!plan.activities.length) {
        plan.activities.push({ id: null, name: '', date: '', weight: '100' });
    }
    plan.confirm_removals = false;
    plan.clearErrors();
    editingPlan.value = true;
};
const addActivity = () => plan.activities.push({ id: null, name: '', date: '', weight: '' });
const removeActivity = (index) => plan.activities.splice(index, 1);
const savePlan = () => plan.put(route('grades.plan', { year: props.sheet.schoolYearId, section: props.sheet.sectionId, subject: props.sheet.subjectId, term: props.sheet.term }), {
    preserveScroll: true,
    onSuccess: () => { editingPlan.value = false; },
});

// The grades, one column (activity) at a time (EVA-R02).
const marks = reactive({});
const fill = () => {
    for (const activity of props.sheet.activities) {
        marks[activity.id] = {};
        for (const row of props.sheet.rows) {
            marks[activity.id][row.studentId] = row.marks[activity.id] ?? '';
        }
    }
};
fill();
watch(() => props.sheet, fill);

const grades = useForm({ grades: {} });
const savingActivity = ref(null);
const saveGrades = (activity) => {
    savingActivity.value = activity.id;
    grades.grades = Object.fromEntries(
        props.sheet.rows
            .filter((row) => row.marks[activity.id] !== 'NA')
            .map((row) => [row.studentId, marks[activity.id][row.studentId] === '' ? null : marks[activity.id][row.studentId]]),
    );
    grades.put(route('grades.activity', activity.id), { preserveScroll: true });
};
const cellError = (activityId, studentId) => (savingActivity.value === activityId ? grades.errors[`grades.${studentId}`] : null);

// Arrow keys move between the cells of the grid (EVA-NF05).
const move = (event, row, column) => {
    const step = { ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1] }[event.key];
    if (!step) {
        return;
    }
    const target = document.querySelector(`[data-cell="${row + step[0]}-${column + step[1]}"]`);
    if (target) {
        event.preventDefault();
        target.focus();
    }
};
</script>

<template>
    <Head :title="`Notas · ${sheet.subject} · ${sheet.section}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ sheet.subject }} · {{ sheet.section }}</h1>
                <Link :href="route('grades.index')" class="text-sm text-indigo-700 hover:underline">Volver a notas</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6">
                <nav aria-label="Lapsos" class="flex gap-2 text-sm">
                    <Link
                        v-for="term in [1, 2, 3]"
                        :key="term"
                        :href="termLink(term)"
                        :aria-current="term === sheet.term ? 'page' : undefined"
                        :class="term === sheet.term ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="rounded-md px-3 py-1.5 font-medium shadow-sm"
                    >
                        Lapso {{ term }}
                    </Link>
                </nav>

                <p v-if="flash" role="status" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash }}</p>
                <p v-if="!sheet.editable" class="rounded-md bg-gray-100 px-4 py-3 text-sm text-gray-700">Las notas de este lapso no se pueden cambiar ahora.</p>
                <InputError :message="grades.errors.term || plan.errors.term" />

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-medium text-gray-900">Plan de evaluación</h2>
                        <SecondaryButton v-if="canEdit && !editingPlan" type="button" @click="startPlan">
                            {{ sheet.activities.length ? 'Cambiar el plan' : 'Crear el plan' }}
                        </SecondaryButton>
                    </div>

                    <ul v-if="!editingPlan" class="mt-3 divide-y divide-gray-100 text-sm">
                        <li v-for="activity in sheet.activities" :key="activity.id" class="flex flex-wrap justify-between gap-2 py-2">
                            <span class="font-medium text-gray-900">{{ activity.name }}</span>
                            <span class="text-gray-600">{{ activity.date }} · {{ activity.weight }} %</span>
                        </li>
                        <li v-if="!sheet.activities.length" class="py-2 text-gray-600">Esta materia aún no tiene plan en este lapso.</li>
                    </ul>

                    <form v-else class="mt-3 space-y-3" @submit.prevent="savePlan">
                        <fieldset v-for="(activity, index) in plan.activities" :key="index" class="grid gap-2 sm:grid-cols-[1fr_10rem_6rem_auto] sm:items-end">
                            <legend class="sr-only">Actividad {{ index + 1 }}</legend>
                            <div>
                                <InputLabel :for="`name-${index}`" value="Actividad" />
                                <TextInput :id="`name-${index}`" v-model="activity.name" class="mt-1 block w-full" maxlength="100" />
                            </div>
                            <div>
                                <InputLabel :for="`date-${index}`" value="Fecha" />
                                <TextInput :id="`date-${index}`" v-model="activity.date" type="date" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel :for="`weight-${index}`" value="Peso (%)" />
                                <TextInput :id="`weight-${index}`" v-model="activity.weight" inputmode="decimal" class="mt-1 block w-full" />
                            </div>
                            <button type="button" class="text-sm font-medium text-red-700 hover:underline sm:pb-2" @click="removeActivity(index)">Quitar</button>
                        </fieldset>

                        <p class="text-sm" :class="weightSum === 100 ? 'text-gray-700' : 'text-amber-800'">Suma de pesos: {{ weightSum }} %</p>
                        <InputError :message="plan.errors.activities" />

                        <label v-if="plan.errors.confirm_removals" class="flex items-start gap-2 text-sm text-gray-800">
                            <input v-model="plan.confirm_removals" type="checkbox" class="mt-0.5 rounded border-gray-300" />
                            <span>{{ plan.errors.confirm_removals }}</span>
                        </label>

                        <div class="flex flex-wrap gap-3">
                            <SecondaryButton type="button" @click="addActivity">Añadir actividad</SecondaryButton>
                            <PrimaryButton :disabled="plan.processing">Guardar el plan</PrimaryButton>
                            <button type="button" class="text-sm text-gray-700 hover:underline" @click="editingPlan = false">Cancelar</button>
                        </div>
                    </form>
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg">
                    <h2 class="px-4 pt-4 text-lg font-medium text-gray-900 sm:px-6">Notas del lapso {{ sheet.term }}</h2>
                    <p class="px-4 pb-2 text-xs text-gray-600 sm:px-6">De 1 a 20 con hasta dos decimales, o NP. NA: la actividad es anterior a la incorporación del estudiante.</p>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Notas de {{ sheet.subject }}, {{ sheet.section }}, lapso {{ sheet.term }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-700">
                                <tr>
                                    <th scope="col" class="px-4 py-2 font-medium">Estudiante</th>
                                    <th v-for="activity in sheet.activities" :key="activity.id" scope="col" class="px-2 py-2 font-medium">
                                        <span class="block">{{ activity.name }}</span>
                                        <span class="block text-xs font-normal text-gray-600">{{ activity.weight }} %</span>
                                    </th>
                                    <th scope="col" class="px-2 py-2 font-medium">Lapso</th>
                                    <th scope="col" class="px-2 py-2 font-medium">Definitiva</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(row, rowIndex) in sheet.rows" :key="row.studentId">
                                    <th scope="row" class="px-4 py-2 text-left font-normal">
                                        <span class="block font-medium text-gray-900">{{ row.fullName }}</span>
                                        <span class="block text-xs text-gray-600">{{ row.identification }}</span>
                                    </th>
                                    <td v-for="(activity, columnIndex) in sheet.activities" :key="activity.id" class="px-2 py-1 align-top">
                                        <span v-if="row.marks[activity.id] === 'NA'" class="text-gray-500" title="No aplica">NA</span>
                                        <template v-else>
                                            <input
                                                v-model="marks[activity.id][row.studentId]"
                                                :data-cell="`${rowIndex}-${columnIndex}`"
                                                :aria-label="`${activity.name}, ${row.fullName}`"
                                                :aria-invalid="cellError(activity.id, row.studentId) ? 'true' : undefined"
                                                :disabled="!canEdit"
                                                inputmode="decimal"
                                                maxlength="5"
                                                class="w-16 rounded-md border-gray-300 px-2 py-1 text-sm disabled:bg-gray-50"
                                                @keydown="move($event, rowIndex, columnIndex)"
                                            />
                                            <InputError :message="cellError(activity.id, row.studentId)" />
                                        </template>
                                    </td>
                                    <td class="px-2 py-2">{{ row.termScore ?? stateText[row.termState] }}</td>
                                    <td class="px-2 py-2" :class="{ 'font-medium text-red-700': row.failed }">
                                        {{ row.finalScore ?? stateText[row.finalState] }}
                                        <span v-if="row.failed" class="sr-only">(reprobada)</span>
                                    </td>
                                </tr>
                                <tr v-if="!sheet.rows.length">
                                    <td :colspan="sheet.activities.length + 3" class="px-4 py-6 text-gray-600">No hay estudiantes inscritos en esta sección.</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="canEdit && sheet.activities.length && sheet.rows.length">
                                <tr>
                                    <td class="px-4 py-2 text-xs text-gray-600">Guarda cada actividad por separado.</td>
                                    <td v-for="activity in sheet.activities" :key="activity.id" class="px-2 py-2">
                                        <button
                                            type="button"
                                            class="text-sm font-medium text-indigo-700 hover:underline disabled:opacity-50"
                                            :disabled="grades.processing"
                                            :aria-label="`Guardar las notas de ${activity.name}`"
                                            @click="saveGrades(activity)"
                                        >
                                            Guardar
                                        </button>
                                    </td>
                                    <td colspan="2" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
