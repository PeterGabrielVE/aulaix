<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    schoolYearId: { type: Number, default: null },
    groups: { type: Array, required: true },
    teachers: { type: Array, required: true },
});

const flash = computed(() => usePage().props.flash?.success);
const keyOf = (group) => `${group.subjectId}:${group.originGradeCode}`;
const choices = ref(Object.fromEntries(props.groups.map((group) => [keyOf(group), group.teacherAccountId ?? ''])));
const form = useForm({ subject: null, origin_grade: '', teacher: null });
const saving = ref(null);

const assign = (group) => {
    saving.value = keyOf(group);
    form.subject = group.subjectId;
    form.origin_grade = group.originGradeCode;
    form.teacher = choices.value[keyOf(group)] === '' ? null : choices.value[keyOf(group)];
    form.put(route('grades.pending.teacher'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Grupos de pendientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">Grupos de pendientes</h1>
                <Link :href="route('grades.index')" class="text-sm text-indigo-700 hover:underline">Volver a notas</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6">
                <p v-if="flash" role="status" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash }}</p>
                <p v-if="!schoolYearId" class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">No hay un año escolar activo.</p>
                <p v-else-if="!groups.length" class="text-sm text-gray-600">Nadie debe materias pendientes este año.</p>

                <section v-for="group in groups" :key="keyOf(group)" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-lg font-medium text-gray-900">{{ group.subject }} · {{ group.originGrade }}</h2>
                        <span v-if="!group.teacher" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Sin docente</span>
                    </div>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-700">
                        <li v-for="member in group.members" :key="member.studentId">{{ member.fullName }} <span class="text-gray-500">({{ member.identification }})</span></li>
                    </ul>

                    <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="assign(group)">
                        <div>
                            <InputLabel :for="`teacher-${keyOf(group)}`" value="Docente" />
                            <select :id="`teacher-${keyOf(group)}`" v-model="choices[keyOf(group)]" class="mt-1 block rounded-md border-gray-300 text-sm">
                                <option value="">Sin docente</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                            </select>
                        </div>
                        <PrimaryButton :disabled="form.processing">Asignar</PrimaryButton>
                        <Link
                            v-if="group.groupId"
                            :href="route('grades.pending.sheet', { group: group.groupId, term: 1 })"
                            class="text-sm text-indigo-700 hover:underline"
                        >
                            Ver la planilla
                        </Link>
                        <InputError v-if="saving === keyOf(group)" class="w-full" :message="form.errors.teacher" />
                    </form>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
