<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { day, identificationLabels, relationshipLabels, sexLabels, statusLabels, withdrawalReasons } from './Partials/labels.js';

const props = defineProps({
    student: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const healthForm = useForm({
    allergies: props.student.health?.allergies ?? '',
    medical_conditions: props.student.health?.medicalConditions ?? '',
    disability: props.student.health?.disability ?? '',
});
const saveHealth = () => healthForm.put(route('students.health', props.student.id), { preserveScroll: true });

const withdrawing = ref(false);
const withdrawalForm = useForm({ withdrawn_on: '', reason: '', note: '' });
const withdraw = () => withdrawalForm.post(route('students.withdraw', props.student.id), {
    preserveScroll: true,
    onSuccess: () => (withdrawing.value = false),
});
const reinstateForm = useForm({});
const reinstate = () => reinstateForm.post(route('students.reinstate', props.student.id), { preserveScroll: true });

// One guardian corrected at a time (EST-R03.8): it changes for all its represented students.
const editingGuardian = ref(null);
const guardianForm = useForm({ given_names: '', family_names: '', document_type: 'national_id', document: '', phone: '', email: '', address: '' });
const editGuardian = (guardian) => {
    editingGuardian.value = guardian.guardianId;
    guardianForm.defaults({
        given_names: guardian.givenNames,
        family_names: guardian.familyNames,
        document_type: guardian.documentType,
        document: guardian.document,
        phone: guardian.phone,
        email: guardian.email ?? '',
        address: guardian.address ?? '',
    });
    guardianForm.reset();
    guardianForm.clearErrors();
};
const saveGuardian = () => guardianForm.put(route('guardians.update', editingGuardian.value), {
    preserveScroll: true,
    onSuccess: () => (editingGuardian.value = null),
});

const selectClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <Head :title="`${student.givenNames} ${student.familyNames}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ student.givenNames }} {{ student.familyNames }}</h1>
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="student.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                >
                    {{ statusLabels[student.status] }}
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
                <p v-if="student.hasHealthInfo && !student.health" class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">
                    Este estudiante tiene información médica registrada. Consúltala con la dirección del plantel.
                </p>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-lg font-medium text-gray-900">Datos del estudiante</h3>
                        <Link v-if="canManage" :href="route('students.edit', student.id)" class="text-sm font-medium text-indigo-700">Editar</Link>
                    </div>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-gray-500">{{ identificationLabels[student.identificationType] }}</dt><dd class="text-gray-900">{{ student.identification }}</dd></div>
                        <div v-if="student.previousSchoolIds.length"><dt class="text-gray-500">Cédula escolar anterior</dt><dd class="text-gray-900">{{ student.previousSchoolIds.join(', ') }}</dd></div>
                        <div><dt class="text-gray-500">Fecha de nacimiento</dt><dd class="text-gray-900">{{ day(student.birthDate) }}</dd></div>
                        <div><dt class="text-gray-500">Sexo</dt><dd class="text-gray-900">{{ sexLabels[student.sex] }}</dd></div>
                        <div><dt class="text-gray-500">Nacionalidad</dt><dd class="text-gray-900">{{ student.nationality === 'V' ? 'Venezolana' : 'Extranjera' }}</dd></div>
                        <div v-if="student.address"><dt class="text-gray-500">Dirección</dt><dd class="text-gray-900">{{ student.address }}</dd></div>
                        <div v-if="student.emergencyPhone"><dt class="text-gray-500">Teléfono de emergencia</dt><dd class="text-gray-900">{{ student.emergencyPhone }}</dd></div>
                    </dl>
                </section>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Representantes</h3>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="guardian in student.guardians" :key="guardian.guardianId" class="space-y-2 py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ guardian.givenNames }} {{ guardian.familyNames }}</span>
                                <span class="text-xs text-gray-600">
                                    {{ relationshipLabels[guardian.relationship] }}<span v-if="guardian.isLegal"> · representante legal</span><span v-if="guardian.isPrimary"> · contacto principal</span>
                                </span>
                            </div>
                            <p class="text-gray-600">{{ guardian.document }} · {{ guardian.phone }}<span v-if="guardian.email"> · {{ guardian.email }}</span></p>
                            <button v-if="canManage && editingGuardian !== guardian.guardianId" type="button" class="text-sm font-medium text-indigo-700" @click="editGuardian(guardian)">Corregir datos</button>

                            <form v-if="editingGuardian === guardian.guardianId" class="space-y-3 rounded-md bg-gray-50 p-3" @submit.prevent="saveGuardian">
                                <p class="text-xs text-gray-600">Los cambios se verán en todos sus representados.</p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div><InputLabel value="Nombres" /><TextInput v-model="guardianForm.given_names" class="mt-1 block w-full" /><InputError :message="guardianForm.errors.given_names" /></div>
                                    <div><InputLabel value="Apellidos" /><TextInput v-model="guardianForm.family_names" class="mt-1 block w-full" /><InputError :message="guardianForm.errors.family_names" /></div>
                                    <div>
                                        <InputLabel value="Documento" />
                                        <select v-model="guardianForm.document_type" :class="selectClass"><option value="national_id">Cédula</option><option value="passport">Pasaporte</option></select>
                                    </div>
                                    <div><InputLabel value="Número" /><TextInput v-model="guardianForm.document" class="mt-1 block w-full" /><InputError :message="guardianForm.errors.document" /></div>
                                    <div><InputLabel value="Teléfono" /><TextInput v-model="guardianForm.phone" type="tel" class="mt-1 block w-full" /><InputError :message="guardianForm.errors.phone" /></div>
                                    <div><InputLabel value="Correo" /><TextInput v-model="guardianForm.email" type="email" class="mt-1 block w-full" /><InputError :message="guardianForm.errors.email" /></div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <PrimaryButton :disabled="guardianForm.processing">Guardar</PrimaryButton>
                                    <SecondaryButton type="button" @click="editingGuardian = null">Cancelar</SecondaryButton>
                                </div>
                            </form>
                        </li>
                    </ul>
                </section>

                <section v-if="student.health" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-1 text-lg font-medium text-gray-900">Salud</h3>
                    <p class="mb-4 text-sm text-gray-600">Solo lo ven la dirección y la administración del plantel.</p>
                    <form class="space-y-3" @submit.prevent="saveHealth">
                        <div><InputLabel for="allergies" value="Alergias" /><textarea id="allergies" v-model="healthForm.allergies" rows="2" maxlength="1000" :class="selectClass" /><InputError :message="healthForm.errors.allergies" /></div>
                        <div><InputLabel for="conditions" value="Condiciones médicas" /><textarea id="conditions" v-model="healthForm.medical_conditions" rows="2" maxlength="1000" :class="selectClass" /><InputError :message="healthForm.errors.medical_conditions" /></div>
                        <div><InputLabel for="disability" value="Discapacidad" /><textarea id="disability" v-model="healthForm.disability" rows="2" maxlength="1000" :class="selectClass" /><InputError :message="healthForm.errors.disability" /></div>
                        <PrimaryButton :disabled="healthForm.processing">Guardar datos de salud</PrimaryButton>
                    </form>
                </section>

                <section v-if="canManage || student.withdrawals.length" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Retiros</h3>
                    <ul v-if="student.withdrawals.length" class="mb-4 space-y-1 text-sm text-gray-700">
                        <li v-for="(withdrawal, index) in student.withdrawals" :key="index">
                            {{ day(withdrawal.withdrawnOn) }}: {{ withdrawalReasons[withdrawal.reason] }}<span v-if="withdrawal.note"> ({{ withdrawal.note }})</span><span v-if="withdrawal.reinstatedOn"> · reincorporado el {{ day(withdrawal.reinstatedOn) }}</span>
                        </li>
                    </ul>

                    <template v-if="canManage">
                        <PrimaryButton v-if="student.status === 'withdrawn'" type="button" :disabled="reinstateForm.processing" @click="reinstate">Reincorporar</PrimaryButton>
                        <InputError :message="reinstateForm.errors.status" />

                        <DangerButton v-if="student.status === 'active' && !withdrawing" type="button" @click="withdrawing = true">Retirar del plantel</DangerButton>
                        <form v-if="withdrawing" class="space-y-3" @submit.prevent="withdraw">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div><InputLabel for="withdrawn_on" value="Fecha del retiro" /><TextInput id="withdrawn_on" v-model="withdrawalForm.withdrawn_on" type="date" class="mt-1 block w-full" required /><InputError :message="withdrawalForm.errors.withdrawn_on" /></div>
                                <div>
                                    <InputLabel for="reason" value="Motivo" />
                                    <select id="reason" v-model="withdrawalForm.reason" :class="selectClass" required>
                                        <option value="" disabled>Elige uno</option>
                                        <option v-for="(label, code) in withdrawalReasons" :key="code" :value="code">{{ label }}</option>
                                    </select>
                                    <InputError :message="withdrawalForm.errors.reason" />
                                </div>
                            </div>
                            <div><InputLabel for="note" value="Observación (opcional)" /><TextInput id="note" v-model="withdrawalForm.note" class="mt-1 block w-full" maxlength="500" /><InputError :message="withdrawalForm.errors.note" /></div>
                            <div class="flex flex-wrap gap-2">
                                <DangerButton :disabled="withdrawalForm.processing">Confirmar retiro</DangerButton>
                                <SecondaryButton type="button" @click="withdrawing = false">Cancelar</SecondaryButton>
                            </div>
                        </form>
                    </template>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
