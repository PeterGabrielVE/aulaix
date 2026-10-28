<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    sheet: { type: Object, required: true },
});

const flash = computed(() => usePage().props.flash?.success);
const isLiteral = computed(() => props.sheet.kind === 'literal');
const periods = computed(() => (isLiteral.value ? ['1', '2', '3', 'final'] : ['1', '2', '3']));
const periodName = (period) => (period === 'final' ? 'Final' : `Lapso ${period}`);
const canEdit = (period) => (period === 'final' ? props.sheet.finalOpen : props.sheet.editable[period]);
const markText = (mark) => {
    if (!mark) {
        return 'Sin registrar';
    }
    if (mark.incomplete) {
        return 'Incompleto';
    }
    return isLiteral.value ? mark.literal : 'Escrito';
};

// The period shown: the first one that takes changes, else the first.
const period = ref(periods.value.find((candidate) => canEdit(candidate)) ?? '1');

const drafts = reactive({});
const fill = () => {
    for (const row of props.sheet.rows) {
        const mark = row.marks[period.value];
        drafts[row.studentId] = {
            literal: mark?.literal ?? '',
            description: mark?.description ?? '',
        };
    }
};
fill();
watch([period, () => props.sheet], fill);

const form = useForm({ kind: props.sheet.kind, period: '1', literal: '', description: '', report: '' });
const savingStudent = ref(null);
const save = (studentId) => {
    savingStudent.value = studentId;
    form.period = period.value;
    form.literal = drafts[studentId].literal;
    form.description = isLiteral.value ? drafts[studentId].description : '';
    form.report = isLiteral.value ? '' : drafts[studentId].description;
    form.put(route('grades.marks.update', { year: props.sheet.schoolYearId, section: props.sheet.sectionId, student: studentId }), { preserveScroll: true });
};
const errorOf = (studentId) => (savingStudent.value === studentId
    ? form.errors.literal || form.errors.description || form.errors.report || form.errors.period || form.errors.term || form.errors.student
    : null);
</script>

<template>
    <Head :title="`${isLiteral ? 'Literales' : 'Informes'} · ${sheet.section}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ isLiteral ? 'Literales' : 'Informes' }} · {{ sheet.section }}</h1>
                <Link :href="route('grades.index')" class="text-sm text-indigo-700 hover:underline">Volver a notas</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6">
                <nav aria-label="Periodos" class="flex flex-wrap gap-2 text-sm">
                    <button
                        v-for="candidate in periods"
                        :key="candidate"
                        type="button"
                        :aria-pressed="candidate === period"
                        :class="candidate === period ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="rounded-md px-3 py-1.5 font-medium shadow-sm"
                        @click="period = candidate"
                    >
                        {{ periodName(candidate) }}
                    </button>
                </nav>

                <p v-if="flash" role="status" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash }}</p>
                <p v-if="!canEdit(period)" class="rounded-md bg-gray-100 px-4 py-3 text-sm text-gray-700">
                    <template v-if="!sheet.canGrade">Solo el guía de la sección los registra.</template>
                    <template v-else-if="period === 'final'">El literal final se registra con el tercer lapso cerrado.</template>
                    <template v-else>Este lapso no se puede cambiar ahora.</template>
                </p>

                <ul class="divide-y divide-gray-100 bg-white shadow-sm sm:rounded-lg">
                    <li v-for="row in sheet.rows" :key="row.studentId" class="px-4 py-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span>
                                <span class="block font-medium text-gray-900">{{ row.fullName }}</span>
                                <span class="block text-xs text-gray-600">{{ row.identification }}</span>
                            </span>
                            <span class="text-sm text-gray-700">{{ periodName(period) }}: {{ markText(row.marks[period]) }}</span>
                        </div>

                        <form v-if="canEdit(period)" class="mt-3 grid gap-3 sm:grid-cols-[8rem_1fr]" @submit.prevent="save(row.studentId)">
                            <div v-if="isLiteral">
                                <InputLabel :for="`literal-${row.studentId}`" value="Literal" />
                                <select :id="`literal-${row.studentId}`" v-model="drafts[row.studentId].literal" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    <option v-for="literal in ['A', 'B', 'C', 'D', 'E']" :key="literal" :value="literal">{{ literal }}</option>
                                </select>
                            </div>
                            <div :class="{ 'sm:col-span-2': !isLiteral }">
                                <InputLabel :for="`text-${row.studentId}`" :value="isLiteral ? 'Descripción' : 'Informe descriptivo'" />
                                <textarea
                                    :id="`text-${row.studentId}`"
                                    v-model="drafts[row.studentId].description"
                                    :rows="isLiteral ? 2 : 5"
                                    maxlength="5000"
                                    :required="!isLiteral"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                                />
                            </div>
                            <InputError class="sm:col-span-2" :message="errorOf(row.studentId)" />
                            <div class="sm:col-span-2">
                                <PrimaryButton :disabled="form.processing">Guardar</PrimaryButton>
                            </div>
                        </form>
                        <p v-else-if="row.marks[period]?.description" class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ row.marks[period].description }}</p>
                    </li>
                    <li v-if="!sheet.rows.length" class="px-4 py-6 text-sm text-gray-600">No hay estudiantes inscritos en esta sección.</li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
