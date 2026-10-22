<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { relationshipLabels } from './Partials/labels.js';

const props = defineProps({
    student: { type: Object, default: null },
    options: { type: Object, required: true },
});

const editing = props.student !== null;

const blankGuardian = (primary = false) => ({
    guardian_id: null,
    given_names: '',
    family_names: '',
    document_type: 'national_id',
    document: '',
    phone: '',
    email: '',
    address: '',
    relationship: 'mother',
    is_legal: true,
    is_primary: primary,
    found: null,
});

const form = useForm({
    given_names: props.student?.givenNames ?? '',
    family_names: props.student?.familyNames ?? '',
    sex: props.student?.sex ?? '',
    birth_date: props.student?.birthDate ?? '',
    nationality: props.student?.nationality ?? 'V',
    identification_type: props.student?.identificationType ?? 'school_id',
    identification: props.student?.identification ?? '',
    birth_country: props.student?.birthCountry ?? 'VE',
    birth_state_id: props.student?.birthStateId ?? null,
    parish_id: props.student?.parishId ?? null,
    address: props.student?.address ?? '',
    emergency_phone: props.student?.emergencyPhone ?? '',
    allergies: '',
    medical_conditions: '',
    disability: '',
    // Existing guardians travel by id; their data is corrected on the record page.
    guardians: props.student
        ? props.student.guardians.map((guardian) => ({
              ...blankGuardian(),
              guardian_id: guardian.guardianId,
              relationship: guardian.relationship,
              is_legal: guardian.isLegal,
              is_primary: guardian.isPrimary,
              found: `${guardian.givenNames} ${guardian.familyNames} (${guardian.document})`,
          }))
        : [blankGuardian(true)],
});

// The address parish walks the catalog: state → municipality → parish.
const fetchList = async (url) => (await (await fetch(url, { headers: { Accept: 'application/json' } })).json()).data;
const addressStateId = ref(props.options.location?.state_id ?? '');
const municipalityId = ref(props.options.location?.municipality_id ?? '');
const municipalities = ref(props.options.municipalities);
const parishes = ref(props.options.parishes);

const chooseAddressState = async () => {
    municipalityId.value = '';
    parishes.value = [];
    form.parish_id = null;
    municipalities.value = addressStateId.value ? await fetchList(route('geography.municipalities', addressStateId.value)) : [];
};

const chooseMunicipality = async () => {
    form.parish_id = null;
    parishes.value = municipalityId.value ? await fetchList(route('geography.parishes', municipalityId.value)) : [];
};

const addGuardian = () => form.guardians.push(blankGuardian(form.guardians.length === 0));
const removeGuardian = (index) => form.guardians.splice(index, 1);
const makePrimary = (index) => form.guardians.forEach((guardian, position) => (guardian.is_primary = position === index));

// A guardian the institution already has is linked, not typed again (EST-R03.3).
const findGuardian = async (guardian) => {
    const url = route('guardians.find', { document_type: guardian.document_type, document: guardian.document });
    const found = await fetchList(url);

    if (found) {
        guardian.guardian_id = found.id;
        guardian.found = `${found.given_names} ${found.family_names} (${found.document})`;
    } else {
        guardian.found = '';
    }
};

const unlinkFound = (guardian) => {
    guardian.guardian_id = null;
    guardian.found = null;
};

const submit = () => {
    const payload = (data) => ({ ...data, guardians: data.guardians.map(({ found, ...guardian }) => guardian) });

    editing
        ? form.transform(payload).put(route('students.update', props.student.id))
        : form.transform(payload).post(route('students.store'));
};

const selectClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <Head :title="editing ? 'Editar estudiante' : 'Registrar estudiante'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ editing ? 'Editar estudiante' : 'Registrar estudiante' }}</h2>
        </template>

        <div class="py-8">
            <form class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6" @submit.prevent="submit">
                <section class="space-y-4 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Datos del estudiante</h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><InputLabel for="given_names" value="Nombres" /><TextInput id="given_names" v-model="form.given_names" class="mt-1 block w-full" maxlength="120" /><InputError :message="form.errors.given_names" /></div>
                        <div><InputLabel for="family_names" value="Apellidos" /><TextInput id="family_names" v-model="form.family_names" class="mt-1 block w-full" maxlength="120" /><InputError :message="form.errors.family_names" /></div>
                        <div>
                            <InputLabel for="sex" value="Sexo" />
                            <select id="sex" v-model="form.sex" :class="selectClass"><option value="" disabled>Elige uno</option><option value="female">Femenino</option><option value="male">Masculino</option></select>
                            <InputError :message="form.errors.sex" />
                        </div>
                        <div><InputLabel for="birth_date" value="Fecha de nacimiento" /><TextInput id="birth_date" v-model="form.birth_date" type="date" class="mt-1 block w-full" /><InputError :message="form.errors.birth_date" /></div>
                        <div>
                            <InputLabel for="nationality" value="Nacionalidad" />
                            <select id="nationality" v-model="form.nationality" :class="selectClass"><option value="V">Venezolana (V)</option><option value="E">Extranjera (E)</option></select>
                            <InputError :message="form.errors.nationality" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="identification_type" value="Identificación" />
                            <select id="identification_type" v-model="form.identification_type" :class="selectClass">
                                <option value="school_id">Cédula escolar</option>
                                <option value="national_id">Cédula de identidad</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="identification" :value="form.identification_type === 'school_id' ? 'Cédula escolar' : 'Cédula de identidad'" />
                            <TextInput id="identification" v-model="form.identification" class="mt-1 block w-full" maxlength="20" />
                            <p v-if="form.identification_type === 'school_id'" class="mt-1 text-xs text-gray-500">
                                12 caracteres: V o E, tipo de parto (1 a 4), año de nacimiento (2 dígitos) y la cédula de la madre o el padre (8 dígitos). Ej.: V11915432100.
                            </p>
                            <InputError :message="form.errors.identification" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="birth_country" value="País de nacimiento" />
                            <select id="birth_country" v-model="form.birth_country" :class="selectClass">
                                <option :value="null">Sin indicar</option>
                                <option v-for="country in options.countries" :key="country.code" :value="country.code">{{ country.name }}</option>
                            </select>
                            <InputError :message="form.errors.birth_country" />
                        </div>
                        <div v-if="form.birth_country === 'VE'">
                            <InputLabel for="birth_state_id" value="Estado de nacimiento" />
                            <select id="birth_state_id" v-model="form.birth_state_id" :class="selectClass">
                                <option :value="null">Sin indicar</option>
                                <option v-for="state in options.states" :key="state.id" :value="state.id">{{ state.name }}</option>
                            </select>
                            <InputError :message="form.errors.birth_state_id" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel for="address_state" value="Estado (dirección)" />
                            <select id="address_state" v-model="addressStateId" :class="selectClass" @change="chooseAddressState">
                                <option value="">Sin indicar</option>
                                <option v-for="state in options.states" :key="state.id" :value="state.id">{{ state.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="municipality" value="Municipio" />
                            <select id="municipality" v-model="municipalityId" :class="selectClass" :disabled="!addressStateId" @change="chooseMunicipality">
                                <option value="">Sin indicar</option>
                                <option v-for="municipality in municipalities" :key="municipality.id" :value="municipality.id">{{ municipality.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="parish" value="Parroquia" />
                            <select id="parish" v-model="form.parish_id" :class="selectClass" :disabled="!municipalityId">
                                <option :value="null">Sin indicar</option>
                                <option v-for="parish in parishes" :key="parish.id" :value="parish.id">{{ parish.name }}</option>
                            </select>
                            <InputError :message="form.errors.parish_id" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><InputLabel for="address" value="Dirección" /><TextInput id="address" v-model="form.address" class="mt-1 block w-full" maxlength="500" /><InputError :message="form.errors.address" /></div>
                        <div><InputLabel for="emergency_phone" value="Teléfono de emergencia" /><TextInput id="emergency_phone" v-model="form.emergency_phone" type="tel" class="mt-1 block w-full" maxlength="30" /><InputError :message="form.errors.emergency_phone" /></div>
                    </div>
                </section>

                <section class="space-y-4 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-lg font-medium text-gray-900">Representantes</h3>
                        <SecondaryButton type="button" @click="addGuardian">Añadir representante</SecondaryButton>
                    </div>
                    <InputError :message="form.errors.guardians" />

                    <fieldset v-for="(guardian, index) in form.guardians" :key="index" class="space-y-3 rounded-md border border-gray-200 p-3">
                        <legend class="px-1 text-sm font-medium text-gray-700">Representante {{ index + 1 }}</legend>

                        <div v-if="guardian.guardian_id" class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="text-gray-900">{{ guardian.found }}</span>
                            <button v-if="!editing" type="button" class="text-indigo-700" @click="unlinkFound(guardian)">Cambiar</button>
                        </div>

                        <template v-else>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <InputLabel :for="`document_type_${index}`" value="Documento" />
                                    <select :id="`document_type_${index}`" v-model="guardian.document_type" :class="selectClass"><option value="national_id">Cédula</option><option value="passport">Pasaporte</option></select>
                                </div>
                                <div class="sm:col-span-2">
                                    <InputLabel :for="`document_${index}`" value="Número" />
                                    <div class="mt-1 flex gap-2">
                                        <TextInput :id="`document_${index}`" v-model="guardian.document" class="block w-full" />
                                        <SecondaryButton type="button" @click="findGuardian(guardian)">Buscar</SecondaryButton>
                                    </div>
                                    <p v-if="guardian.found === ''" class="mt-1 text-xs text-gray-500">No está registrado: completa sus datos.</p>
                                    <InputError :message="form.errors[`guardians.${index}.document`]" />
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div><InputLabel :for="`given_${index}`" value="Nombres" /><TextInput :id="`given_${index}`" v-model="guardian.given_names" class="mt-1 block w-full" /><InputError :message="form.errors[`guardians.${index}.given_names`]" /></div>
                                <div><InputLabel :for="`family_${index}`" value="Apellidos" /><TextInput :id="`family_${index}`" v-model="guardian.family_names" class="mt-1 block w-full" /><InputError :message="form.errors[`guardians.${index}.family_names`]" /></div>
                                <div><InputLabel :for="`phone_${index}`" value="Teléfono" /><TextInput :id="`phone_${index}`" v-model="guardian.phone" type="tel" class="mt-1 block w-full" /><InputError :message="form.errors[`guardians.${index}.phone`]" /></div>
                                <div><InputLabel :for="`email_${index}`" value="Correo (opcional)" /><TextInput :id="`email_${index}`" v-model="guardian.email" type="email" class="mt-1 block w-full" /><InputError :message="form.errors[`guardians.${index}.email`]" /></div>
                            </div>
                        </template>

                        <div class="grid gap-3 sm:grid-cols-3 sm:items-end">
                            <div>
                                <InputLabel :for="`relationship_${index}`" value="Parentesco" />
                                <select :id="`relationship_${index}`" v-model="guardian.relationship" :class="selectClass">
                                    <option v-for="(label, code) in relationshipLabels" :key="code" :value="code">{{ label }}</option>
                                </select>
                                <InputError :message="form.errors[`guardians.${index}.relationship`]" />
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="guardian.is_legal" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> Representante legal</label>
                            <label class="flex items-center gap-2 text-sm text-gray-700"><input type="radio" name="primary" :checked="guardian.is_primary" class="border-gray-300 text-indigo-600" @change="makePrimary(index)" /> Contacto principal</label>
                        </div>

                        <button v-if="form.guardians.length > 1" type="button" class="text-sm text-red-700" @click="removeGuardian(index)">Quitar</button>
                    </fieldset>
                </section>

                <section v-if="!editing" class="space-y-3 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Salud (opcional)</h3>
                    <p class="text-sm text-gray-600">Solo lo ven la dirección y la administración del plantel.</p>
                    <div><InputLabel for="allergies" value="Alergias" /><textarea id="allergies" v-model="form.allergies" rows="2" maxlength="1000" :class="selectClass" /><InputError :message="form.errors.allergies" /></div>
                    <div><InputLabel for="conditions" value="Condiciones médicas" /><textarea id="conditions" v-model="form.medical_conditions" rows="2" maxlength="1000" :class="selectClass" /><InputError :message="form.errors.medical_conditions" /></div>
                    <div><InputLabel for="disability" value="Discapacidad" /><textarea id="disability" v-model="form.disability" rows="2" maxlength="1000" :class="selectClass" /><InputError :message="form.errors.disability" /></div>
                </section>

                <div class="flex flex-wrap justify-end gap-3">
                    <Link :href="editing ? route('students.show', student.id) : route('students.index')" class="px-4 py-2 text-sm text-gray-700">Cancelar</Link>
                    <PrimaryButton :disabled="form.processing">{{ editing ? 'Guardar cambios' : 'Registrar estudiante' }}</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
