<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    proposal: { type: Object, required: true },
});

const sectionsById = Object.fromEntries(props.proposal.sections.map((section) => [section.id, section]));
const form = useForm({
    placements: Object.fromEntries(props.proposal.entries.map((entry) => [entry.studentId, entry.sectionIds[0]])),
    over_capacity_reasons: {},
});

// MAT-R07.5: a section that the chosen students take over its capacity needs a reason.
const overCapacity = computed(() => props.proposal.sections.filter((section) => {
    const chosen = Object.values(form.placements).filter((id) => id === section.id).length;

    return chosen > 0 && section.enrolled + chosen > section.capacity;
}));

const enroll = () => form.post(route('next-year.store', props.proposal.fromSchoolYearId), { preserveScroll: true });
</script>

<template>
    <Head title="Inscribir en el año siguiente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">Inscribir en el año siguiente</h1>
                <Link :href="route('enrollments.index', { anio: proposal.fromSchoolYearId })" class="text-sm text-indigo-700 hover:underline">Volver a inscripciones</Link>
            </div>
        </template>

        <div class="py-8">
            <form class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6" @submit.prevent="enroll">
                <section aria-labelledby="entries-title" class="bg-white shadow-sm sm:rounded-lg">
                    <h2 id="entries-title" class="px-4 pt-4 text-lg font-medium text-gray-900">Se inscribirán ({{ proposal.entries.length }})</h2>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="entry in proposal.entries" :key="entry.studentId" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <span class="font-medium text-gray-900">{{ entry.fullName }}</span>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <span class="sr-only">Sección de {{ entry.fullName }}</span>
                                <select v-model="form.placements[entry.studentId]" class="rounded-md border-gray-300 text-sm">
                                    <option v-for="id in entry.sectionIds" :key="id" :value="id">{{ entry.toGradeName }} · {{ sectionsById[id].letter }}</option>
                                </select>
                            </label>
                        </li>
                        <li v-if="!proposal.entries.length" class="px-4 py-6 text-sm text-gray-600">No hay estudiantes para inscribir.</li>
                    </ul>
                </section>

                <section v-if="overCapacity.length" aria-labelledby="capacity-title" class="space-y-3 bg-white p-4 shadow-sm sm:rounded-lg">
                    <h2 id="capacity-title" class="text-lg font-medium text-gray-900">Secciones en sobrecupo</h2>
                    <div v-for="section in overCapacity" :key="section.id">
                        <InputLabel :for="`over-${section.id}`" :value="`Motivo del sobrecupo de ${section.gradeName} · ${section.letter} (cupo ${section.capacity})`" />
                        <TextInput :id="`over-${section.id}`" v-model="form.over_capacity_reasons[section.id]" class="mt-1 block w-full" />
                    </div>
                </section>

                <InputError :message="form.errors.placements || form.errors.over_capacity_reason || form.errors.school_year" />
                <PrimaryButton v-if="proposal.entries.length" :disabled="form.processing">Inscribir a {{ proposal.entries.length }} estudiantes</PrimaryButton>

                <section v-if="proposal.excluded.length" aria-labelledby="excluded-title" class="bg-white shadow-sm sm:rounded-lg">
                    <h2 id="excluded-title" class="px-4 pt-4 text-lg font-medium text-gray-900">No se inscribirán ({{ proposal.excluded.length }})</h2>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="student in proposal.excluded" :key="student.studentId" class="px-4 py-3">
                            <span class="block font-medium text-gray-900">{{ student.fullName }}</span>
                            <span class="block text-sm text-gray-600">{{ student.reason }}</span>
                        </li>
                    </ul>
                </section>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
