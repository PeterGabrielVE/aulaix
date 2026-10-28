<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    roster: { type: Object, required: true },
    sections: { type: Array, required: true },
    annulmentReasons: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
    canApprove: { type: Boolean, required: true },
});

const flash = computed(() => usePage().props.flash?.success);
const today = new Date().toISOString().slice(0, 10);
const sameGrade = computed(() => props.sections.filter((section) => section.grade === props.roster.section.grade && section.id !== props.roster.section.id));
const otherGrades = computed(() => props.sections.filter((section) => section.grade !== props.roster.section.grade));

const open = ref(null);
const change = useForm({ section: null, date: today, reason: '', over_capacity_reason: '', age_exception: '' });
const annulment = useForm({ reason: '', note: '' });

const start = (enrollmentId, action) => {
    open.value = { enrollmentId, action };
    change.reset();
    change.clearErrors();
    annulment.reset();
    annulment.clearErrors();
};

const isOpen = (enrollmentId, action) => open.value?.enrollmentId === enrollmentId && open.value?.action === action;
const done = { preserveScroll: true, onSuccess: () => { open.value = null; } };

const submitChange = (enrollmentId) => {
    const name = open.value.action === 'grade' ? 'enrollments.change-grade' : 'enrollments.change-section';
    change.post(route(name, enrollmentId), done);
};

const submitAnnulment = (enrollmentId) => annulment.post(route('enrollments.annul', enrollmentId), done);
</script>

<template>
    <Head :title="`${roster.section.gradeName} · Sección ${roster.section.letter}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ roster.section.gradeName }} · Sección {{ roster.section.letter }}</h1>
                <span class="flex flex-wrap gap-4 text-sm">
                    <Link v-if="canManage" :href="route('promotion.section', { year: roster.schoolYearId, section: roster.section.id })" class="text-indigo-700 hover:underline">Prosecución</Link>
                    <Link :href="route('enrollments.index', { anio: roster.schoolYearId })" class="text-indigo-700 hover:underline">Volver a inscripciones</Link>
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6">
                <p v-if="flash" role="status" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash }}</p>

                <p class="text-sm text-gray-700">
                    {{ roster.section.enrolled }} inscritos · cupo {{ roster.section.capacity }}
                    <span v-if="roster.section.overCapacity" class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Sobrecupo</span>
                </p>

                <ul class="divide-y divide-gray-100 bg-white shadow-sm sm:rounded-lg">
                    <li v-for="student in roster.students" :key="student.enrollmentId" class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Link :href="route('students.show', student.studentId)" class="hover:underline">
                                <span class="block font-medium text-gray-900">{{ student.fullName }}</span>
                                <span class="block text-sm text-gray-600">{{ student.identification }} · desde {{ student.startsOn }}</span>
                                <span v-if="student.overCapacityReason" class="block text-xs text-amber-800">Sobrecupo: {{ student.overCapacityReason }}</span>
                            </Link>
                            <span v-if="canManage" class="flex flex-wrap gap-3 text-sm">
                                <button type="button" class="font-medium text-indigo-700 hover:underline" @click="start(student.enrollmentId, 'section')">Cambiar de sección</button>
                                <button v-if="canApprove" type="button" class="font-medium text-indigo-700 hover:underline" @click="start(student.enrollmentId, 'grade')">Cambiar de grado</button>
                                <button type="button" class="font-medium text-red-700 hover:underline" @click="start(student.enrollmentId, 'annul')">Anular</button>
                            </span>
                        </div>

                        <form
                            v-if="isOpen(student.enrollmentId, 'section') || isOpen(student.enrollmentId, 'grade')"
                            class="mt-3 grid gap-3 sm:grid-cols-2"
                            @submit.prevent="submitChange(student.enrollmentId)"
                        >
                            <div>
                                <InputLabel :for="`to-${student.enrollmentId}`" value="Nueva sección" />
                                <select :id="`to-${student.enrollmentId}`" v-model="change.section" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    <option v-for="section in (open.action === 'grade' ? otherGrades : sameGrade)" :key="section.id" :value="section.id">
                                        {{ section.gradeName }} · {{ section.letter }} ({{ section.enrolled }}/{{ section.capacity }})
                                    </option>
                                </select>
                                <InputError :message="change.errors.section" />
                            </div>
                            <div>
                                <InputLabel :for="`on-${student.enrollmentId}`" value="Desde" />
                                <TextInput :id="`on-${student.enrollmentId}`" v-model="change.date" type="date" class="mt-1 block w-full" />
                                <InputError :message="change.errors.date" />
                            </div>
                            <div class="sm:col-span-2">
                                <InputLabel :for="`why-${student.enrollmentId}`" value="Motivo" />
                                <TextInput :id="`why-${student.enrollmentId}`" v-model="change.reason" class="mt-1 block w-full" />
                                <InputError :message="change.errors.reason" />
                            </div>
                            <div class="sm:col-span-2">
                                <InputLabel :for="`over-${student.enrollmentId}`" value="Motivo del sobrecupo (si la sección está llena)" />
                                <TextInput :id="`over-${student.enrollmentId}`" v-model="change.over_capacity_reason" class="mt-1 block w-full" />
                                <InputError :message="change.errors.over_capacity_reason" />
                            </div>
                            <div v-if="open.action === 'grade'" class="sm:col-span-2">
                                <InputLabel :for="`age-${student.enrollmentId}`" value="Excepción de edad (solo si la edad está fuera del rango)" />
                                <TextInput :id="`age-${student.enrollmentId}`" v-model="change.age_exception" class="mt-1 block w-full" />
                                <InputError :message="change.errors.age_exception" />
                            </div>
                            <InputError class="sm:col-span-2" :message="change.errors.enrollment || change.errors.school_year" />
                            <div class="sm:col-span-2">
                                <PrimaryButton :disabled="change.processing">Guardar cambio</PrimaryButton>
                            </div>
                        </form>

                        <form v-if="isOpen(student.enrollmentId, 'annul')" class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submitAnnulment(student.enrollmentId)">
                            <div>
                                <InputLabel :for="`annul-${student.enrollmentId}`" value="Motivo de la anulación" />
                                <select :id="`annul-${student.enrollmentId}`" v-model="annulment.reason" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    <option v-for="reason in annulmentReasons" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
                                </select>
                                <InputError :message="annulment.errors.reason" />
                            </div>
                            <div>
                                <InputLabel :for="`note-${student.enrollmentId}`" value="Observación" />
                                <TextInput :id="`note-${student.enrollmentId}`" v-model="annulment.note" class="mt-1 block w-full" />
                                <InputError :message="annulment.errors.note" />
                            </div>
                            <InputError class="sm:col-span-2" :message="annulment.errors.enrollment" />
                            <div class="sm:col-span-2">
                                <PrimaryButton :disabled="annulment.processing">Anular inscripción</PrimaryButton>
                            </div>
                        </form>
                    </li>
                    <li v-if="!roster.students.length" class="px-4 py-6 text-sm text-gray-600">No hay estudiantes inscritos en esta sección.</li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
