<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import YearDatesForm from './Partials/YearDatesForm.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    years: {
        type: Array,
        required: true,
    },
});

const statusLabels = { planned: 'Planificado', active: 'Activo', closed: 'Cerrado' };
const termLabels = { pending: 'Sin abrir', open: 'Abierto', closed: 'Cerrado' };

const blankTerms = () => [1, 2, 3].map(() => ({ starts_on: '', ends_on: '' }));
const createForm = useForm({ starts_on: '', ends_on: '', terms: blankTerms() });
const canPlan = computed(() => !props.years.some((year) => year.status === 'planned'));

const editing = ref(null);
const editForm = useForm({ starts_on: '', ends_on: '', terms: blankTerms() });

const startEditing = (year) => {
    editing.value = year.id;
    editForm.starts_on = year.startsOn;
    editForm.ends_on = year.endsOn;
    editForm.terms = year.terms.map((term) => ({ starts_on: term.startsOn, ends_on: term.endsOn }));
    editForm.clearErrors();
};

const createYear = () => createForm.post(route('calendar.years.store'), { preserveScroll: true, onSuccess: () => createForm.reset() });
const saveDates = (year) => editForm.put(route('calendar.years.update', year.id), { preserveScroll: true, onSuccess: () => (editing.value = null) });

// One modal for every action that needs a confirmation or a reason (CAL-R06.2).
const pending = ref(null);
const actionForm = useForm({ confirmation: true, reason: '' });

const ask = (action, year, term = null) => {
    pending.value = { action, year, term };
    actionForm.reset();
    actionForm.clearErrors();
};

const confirmations = {
    activate: { title: '¿Activar este año escolar?', text: 'Se abrirá su lapso 1. Solo puede haber un año activo.', route: 'calendar.years.activate', method: 'post' },
    closeTerm: { title: '¿Cerrar el lapso abierto?', text: 'Sus notas ya no podrán cambiarse salvo con el permiso especial. Se abrirá el lapso siguiente.', route: 'calendar.terms.close', method: 'post' },
    closeYear: { title: '¿Cerrar el año escolar?', text: 'Es definitivo: un año cerrado no se reabre.', route: 'calendar.years.close', method: 'post' },
    delete: { title: '¿Eliminar este año planificado?', text: 'Se borrará con sus lapsos.', route: 'calendar.years.destroy', method: 'delete' },
    reopen: { title: 'Reabrir el lapso', text: 'Indica el motivo: quedará en la bitácora.', route: 'calendar.terms.reopen', method: 'post' },
};

const confirmAction = () => {
    const { action, year, term } = pending.value;
    const config = confirmations[action];
    const params = action === 'reopen' ? [year.id, term] : year.id;

    actionForm[config.method](route(config.route, params), {
        preserveScroll: true,
        onSuccess: () => (pending.value = null),
    });
};

const openTerm = (year) => year.terms.find((term) => term.status === 'open');
const lastClosedTerm = (year) => [...year.terms].reverse().find((term) => term.status === 'closed');
const allTermsClosed = (year) => year.terms.every((term) => term.status === 'closed');
const closedTermNumbers = (year) => year.terms.filter((term) => term.status === 'closed').map((term) => term.number);
const day = (date) => date.split('-').reverse().join('/');
</script>

<template>
    <Head title="Año escolar" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">Año escolar</h1>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
                <section v-if="canPlan" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-1 text-lg font-medium text-gray-900">Planificar un año escolar</h3>
                    <p class="mb-4 text-sm text-gray-600">
                        El nombre sale de las fechas (p. ej. 2026-2027). Dura como máximo 11 meses.
                    </p>
                    <YearDatesForm :form="createForm" submit-label="Crear año escolar" @submit="createYear" />
                </section>

                <section v-for="year in years" :key="year.id" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-lg font-medium text-gray-900">Año escolar {{ year.name }}</h3>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ statusLabels[year.status] }}</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-600">Del {{ day(year.startsOn) }} al {{ day(year.endsOn) }}</p>

                    <ul class="mt-4 divide-y divide-gray-100 text-sm">
                        <li v-for="term in year.terms" :key="term.number" class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span class="text-gray-800">Lapso {{ term.number }}: {{ day(term.startsOn) }} – {{ day(term.endsOn) }}</span>
                            <span class="text-xs font-medium" :class="term.status === 'open' ? 'text-green-700' : 'text-gray-500'">{{ termLabels[term.status] }}</span>
                        </li>
                    </ul>

                    <YearDatesForm
                        v-if="editing === year.id"
                        class="mt-4"
                        :form="editForm"
                        :locked-terms="closedTermNumbers(year)"
                        submit-label="Guardar fechas"
                        @submit="saveDates(year)"
                    />

                    <div v-if="year.status !== 'closed'" class="mt-4 flex flex-wrap gap-2">
                        <SecondaryButton v-if="editing !== year.id" @click="startEditing(year)">Editar fechas</SecondaryButton>
                        <PrimaryButton v-if="year.status === 'planned'" @click="ask('activate', year)">Activar</PrimaryButton>
                        <DangerButton v-if="year.status === 'planned'" @click="ask('delete', year)">Eliminar</DangerButton>
                        <PrimaryButton v-if="year.status === 'active' && openTerm(year)" @click="ask('closeTerm', year)">
                            Cerrar lapso {{ openTerm(year).number }}
                        </PrimaryButton>
                        <SecondaryButton v-if="year.status === 'active' && lastClosedTerm(year)" @click="ask('reopen', year, lastClosedTerm(year).number)">
                            Reabrir lapso {{ lastClosedTerm(year).number }}
                        </SecondaryButton>
                        <DangerButton v-if="year.status === 'active' && allTermsClosed(year)" @click="ask('closeYear', year)">Cerrar el año</DangerButton>
                    </div>
                </section>

                <p v-if="!years.length && !canPlan" class="text-sm text-gray-600">No hay años escolares todavía.</p>
            </div>
        </div>

        <Modal :show="pending !== null" @close="pending = null">
            <div v-if="pending" class="space-y-4 p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ confirmations[pending.action].title }}</h2>
                <p class="text-sm text-gray-600">{{ confirmations[pending.action].text }}</p>

                <div v-if="pending.action === 'reopen'">
                    <InputLabel for="reason" value="Motivo" />
                    <TextInput id="reason" v-model="actionForm.reason" type="text" class="mt-1 block w-full" maxlength="500" required />
                    <InputError class="mt-1" :message="actionForm.errors.reason" />
                </div>

                <InputError :message="actionForm.errors.status || actionForm.errors.term || actionForm.errors.confirmation" />

                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="pending = null">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="actionForm.processing" @click="confirmAction">Confirmar</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
