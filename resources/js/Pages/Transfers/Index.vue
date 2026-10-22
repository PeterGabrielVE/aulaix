<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { day } from '../Students/Partials/labels.js';

defineProps({
    incoming: { type: Array, required: true },
    outgoing: { type: Array, required: true },
});

const statusLabels = { pending: 'Pendiente', approved: 'Aprobada', rejected: 'Rechazada', expired: 'Caducada' };
const statusClasses = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    expired: 'bg-gray-100 text-gray-700',
};

const requestForm = useForm({ identification_type: 'school_id', identification: '' });
const ask = () => requestForm.post(route('transfers.store'), { preserveScroll: true, onSuccess: () => requestForm.reset('identification') });

const answerForm = useForm({ reason: '' });
const rejecting = ref(null);
const approve = (transfer) => answerForm.post(route('transfers.approve', transfer.id), { preserveScroll: true });
const reject = (transfer) => answerForm.post(route('transfers.reject', transfer.id), {
    preserveScroll: true,
    onSuccess: () => {
        rejecting.value = null;
        answerForm.reset();
    },
});

const selectClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <Head title="Traslados" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Traslados</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-1 text-lg font-medium text-gray-900">Pedir un estudiante</h3>
                    <p class="mb-4 text-sm text-gray-600">
                        Si el estudiante está activo en otro plantel de AulaX, ese plantel recibirá la solicitud y tendrá 15 días para responderla.
                    </p>
                    <form class="grid gap-3 sm:grid-cols-3 sm:items-end" @submit.prevent="ask">
                        <div>
                            <InputLabel for="identification_type" value="Identificación" />
                            <select id="identification_type" v-model="requestForm.identification_type" :class="selectClass">
                                <option value="school_id">Cédula escolar</option>
                                <option value="national_id">Cédula de identidad</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="identification" value="Número" />
                            <TextInput id="identification" v-model="requestForm.identification" class="mt-1 block w-full" maxlength="20" required />
                        </div>
                        <PrimaryButton :disabled="requestForm.processing">Enviar solicitud</PrimaryButton>
                    </form>
                    <InputError class="mt-2" :message="requestForm.errors.identification || requestForm.errors.identification_type" />
                </section>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Solicitudes recibidas</h3>
                    <InputError class="mb-2" :message="answerForm.errors.status" />
                    <ul class="divide-y divide-gray-100">
                        <li v-for="transfer in incoming" :key="transfer.id" class="space-y-2 py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ transfer.studentName ?? transfer.identification }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClasses[transfer.status]">{{ statusLabels[transfer.status] }}</span>
                            </div>
                            <p class="text-gray-600">
                                {{ transfer.identification }} · lo pide {{ transfer.destinationName }} el {{ day(transfer.requestedOn) }}
                                <span v-if="transfer.status === 'pending'"> · responde hasta el {{ day(transfer.lastDayToAnswer) }}</span>
                            </p>

                            <div v-if="transfer.status === 'pending'" class="flex flex-wrap gap-2">
                                <PrimaryButton type="button" :disabled="answerForm.processing" @click="approve(transfer)">Aprobar traslado</PrimaryButton>
                                <SecondaryButton v-if="rejecting !== transfer.id" type="button" @click="rejecting = transfer.id">Rechazar</SecondaryButton>
                            </div>
                            <form v-if="rejecting === transfer.id" class="space-y-2" @submit.prevent="reject(transfer)">
                                <InputLabel :for="`reason_${transfer.id}`" value="Motivo del rechazo (lo verá el otro plantel)" />
                                <TextInput :id="`reason_${transfer.id}`" v-model="answerForm.reason" class="block w-full" maxlength="500" required />
                                <InputError :message="answerForm.errors.rejection_reason" />
                                <div class="flex flex-wrap gap-2">
                                    <DangerButton :disabled="answerForm.processing">Confirmar rechazo</DangerButton>
                                    <SecondaryButton type="button" @click="rejecting = null">Cancelar</SecondaryButton>
                                </div>
                            </form>
                        </li>
                        <li v-if="!incoming.length" class="py-3 text-sm text-gray-600">No hay solicitudes de otros planteles.</li>
                    </ul>
                </section>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Solicitudes enviadas</h3>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="transfer in outgoing" :key="transfer.id" class="space-y-1 py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ transfer.identification }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClasses[transfer.status]">{{ statusLabels[transfer.status] }}</span>
                            </div>
                            <p class="text-gray-600">
                                Enviada el {{ day(transfer.requestedOn) }}<span v-if="transfer.originName"> · respondió {{ transfer.originName }}</span>
                            </p>
                            <p v-if="transfer.rejectionReason" class="text-gray-700">Motivo: {{ transfer.rejectionReason }}</p>
                        </li>
                        <li v-if="!outgoing.length" class="py-3 text-sm text-gray-600">Todavía no has pedido ningún estudiante.</li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
