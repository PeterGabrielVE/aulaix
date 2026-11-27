<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    overview: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
    canApprove: { type: Boolean, required: true },
});

const statusLabels = { planned: 'planificado', active: 'activo', closed: 'cerrado' };
const flash = computed(() => usePage().props.flash?.success);
const today = new Date().toISOString().slice(0, 10);

const chosen = ref(null);
const form = useForm({
    student: null,
    school_year: props.overview.schoolYearId,
    section: null,
    date: today,
    over_capacity_reason: '',
    age_exception: '',
});

const choose = (student) => {
    chosen.value = student;
    form.reset();
    form.clearErrors();
    form.student = student.id;
    form.school_year = props.overview.schoolYearId;
};

const enroll = () => {
    form.post(route('enrollments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            chosen.value = null;
        },
    });
};

const changeYear = (event) => router.get(route('enrollments.index'), { anio: event.target.value }, { preserveScroll: true });
</script>

<template>
    <Head title="Inscripciones" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">Inscripciones</h1>
                <label v-if="overview.years.length" class="flex items-center gap-2 text-sm text-gray-700">
                    Año escolar
                    <select class="rounded-md border-gray-300 text-sm" :value="overview.schoolYearId" @change="changeYear">
                        <option v-for="year in overview.years" :key="year.id" :value="year.id">
                            {{ year.name }} ({{ statusLabels[year.status] }})
                        </option>
                    </select>
                </label>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6">
                <p v-if="flash" role="status" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash }}</p>
                <InputError :message="usePage().props.errors?.school_year" />

                <p v-if="canManage && overview.status === 'active'" class="text-sm">
                    <Link :href="route('next-year.show', overview.schoolYearId)" class="font-medium text-indigo-700 hover:underline">Inscribir en el año siguiente según la prosecución</Link>
                </p>

                <p v-if="!overview.schoolYearId" class="rounded-md bg-white px-4 py-6 text-sm text-gray-600 shadow-sm">
                    El plantel aún no tiene años escolares.
                </p>

                <template v-else>
                    <section aria-labelledby="sections-title" class="bg-white shadow-sm sm:rounded-lg">
                        <h2 id="sections-title" class="px-4 pt-4 text-lg font-medium text-gray-900">Secciones</h2>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="section in overview.sections" :key="section.id">
                                <Link
                                    :href="route('enrollments.section', { year: overview.schoolYearId, section: section.id })"
                                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-gray-50"
                                >
                                    <span class="font-medium text-gray-900">{{ section.gradeName }} · Sección {{ section.letter }}</span>
                                    <span class="flex items-center gap-2 text-sm text-gray-700">
                                        {{ section.enrolled }} / {{ section.capacity }}
                                        <span v-if="section.overCapacity" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Sobrecupo</span>
                                    </span>
                                </Link>
                            </li>
                            <li v-if="!overview.sections.length" class="px-4 py-6 text-sm text-gray-600">Este año no tiene secciones todavía.</li>
                        </ul>
                    </section>

                    <section aria-labelledby="unenrolled-title" class="bg-white shadow-sm sm:rounded-lg">
                        <h2 id="unenrolled-title" class="px-4 pt-4 text-lg font-medium text-gray-900">Estudiantes sin inscripción</h2>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="student in overview.unenrolled" :key="student.id" class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span>
                                        <span class="block font-medium text-gray-900">{{ student.fullName }}</span>
                                        <span class="block text-sm text-gray-600">{{ student.identification }}</span>
                                    </span>
                                    <button
                                        v-if="canManage && overview.status !== 'closed'"
                                        type="button"
                                        class="text-sm font-medium text-indigo-700 hover:underline"
                                        :aria-expanded="chosen?.id === student.id"
                                        @click="choose(student)"
                                    >
                                        Inscribir
                                    </button>
                                </div>

                                <form v-if="chosen?.id === student.id" class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="enroll">
                                    <div>
                                        <InputLabel :for="`section-${student.id}`" value="Sección" />
                                        <select :id="`section-${student.id}`" v-model="form.section" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                            <option v-for="section in overview.sections" :key="section.id" :value="section.id">
                                                {{ section.gradeName }} · {{ section.letter }} ({{ section.enrolled }}/{{ section.capacity }})
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.section" />
                                    </div>
                                    <div>
                                        <InputLabel :for="`date-${student.id}`" value="Fecha de inscripción" />
                                        <TextInput :id="`date-${student.id}`" v-model="form.date" type="date" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.date" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <InputLabel :for="`capacity-${student.id}`" value="Motivo del sobrecupo (si la sección está llena)" />
                                        <TextInput :id="`capacity-${student.id}`" v-model="form.over_capacity_reason" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.over_capacity_reason" />
                                    </div>
                                    <div v-if="canApprove" class="sm:col-span-2">
                                        <InputLabel :for="`age-${student.id}`" value="Excepción de edad (solo si la edad está fuera del rango)" />
                                        <TextInput :id="`age-${student.id}`" v-model="form.age_exception" class="mt-1 block w-full" />
                                    </div>
                                    <InputError class="sm:col-span-2" :message="form.errors.age_exception || form.errors.student || form.errors.school_year" />
                                    <div class="sm:col-span-2">
                                        <PrimaryButton :disabled="form.processing">Inscribir</PrimaryButton>
                                    </div>
                                </form>
                            </li>
                            <li v-if="!overview.unenrolled.length" class="px-4 py-6 text-sm text-gray-600">Todos los estudiantes activos están inscritos.</li>
                        </ul>
                    </section>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
