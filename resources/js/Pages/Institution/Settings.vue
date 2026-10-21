<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';

const props = defineProps({
    details: { type: Object, required: true },
    options: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const logoUrl = computed(() => usePage().props.institution?.logoUrl);

const form = useForm({
    name: props.details.name,
    dea_code: props.details.deaCode ?? '',
    rif: props.details.rif ?? '',
    parish_id: props.details.location?.parish_id ?? null,
    address: props.details.address ?? '',
    phone: props.details.phone ?? '',
    email: props.details.email ?? '',
    type: props.details.type ?? '',
    levels: [...props.details.levels],
});

// The location selectors walk the catalog: state → municipality → parish.
const fetchList = async (url) => (await (await fetch(url, { headers: { Accept: 'application/json' } })).json()).data;
const stateId = ref(props.details.location?.state_id ?? '');
const municipalityId = ref(props.details.location?.municipality_id ?? '');
const municipalities = ref(props.options.municipalities);
const parishes = ref(props.options.parishes);

const chooseState = async () => {
    municipalityId.value = '';
    parishes.value = [];
    form.parish_id = null;
    municipalities.value = stateId.value
        ? await fetchList(route('geography.municipalities', stateId.value))
        : [];
};

const chooseMunicipality = async () => {
    form.parish_id = null;
    parishes.value = municipalityId.value
        ? await fetchList(route('geography.parishes', municipalityId.value))
        : [];
};

const save = () => form.put(route('institution.update'), { preserveScroll: true });

const logoForm = useForm({ file: null });
const uploadLogo = () => logoForm.post(route('institution.logo.store'), { preserveScroll: true, forceFormData: true, onSuccess: () => logoForm.reset() });
const removeLogo = () => router.delete(route('institution.logo.destroy'), { preserveScroll: true });

const roleNames = { director: 'Director(a)', secretary: 'Secretario(a)' };
const signatureOf = (role) => props.details.signatures?.find((signature) => signature.role === role);
const signatureForms = Object.fromEntries(Object.keys(roleNames).map((role) => [role, useForm({
    signer_name: signatureOf(role)?.signerName ?? '',
    signer_title: signatureOf(role)?.signerTitle ?? '',
    file: null,
})]));
const uploadSignature = (role) => signatureForms[role].post(route('institution.signatures.store', role), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => signatureForms[role].reset('file'),
});
const removeSignature = (role) => router.delete(route('institution.signatures.destroy', role), { preserveScroll: true });
// Signature images change under the same URL: a new version after each save.
const signatureVersion = ref(Date.now());
onUnmounted(router.on('success', () => (signatureVersion.value = Date.now())));

const selectClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50';
const typeName = computed(() => props.options.types.find((type) => type.code === props.details.type)?.name);
</script>

<template>
    <Head title="Plantel" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Plantel</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
                <p v-if="canManage && (!details.type || !details.levels.length)" class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">
                    Completa el tipo de plantel y los niveles que ofrece.
                </p>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-1 text-lg font-medium text-gray-900">Datos del plantel</h3>
                    <p class="mb-4 text-sm text-gray-600">Dirección del plantel: {{ details.subdomain }} (no se puede cambiar).</p>

                    <form class="space-y-4" @submit.prevent="save">
                        <fieldset :disabled="!canManage" class="space-y-4">
                            <div>
                                <InputLabel for="name" value="Nombre" />
                                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" maxlength="255" required />
                                <InputError class="mt-1" :message="form.errors.name" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="dea_code" value="Código DEA" />
                                    <TextInput id="dea_code" v-model="form.dea_code" type="text" class="mt-1 block w-full" maxlength="20" />
                                    <InputError class="mt-1" :message="form.errors.dea_code" />
                                </div>
                                <div>
                                    <InputLabel for="rif" value="RIF" />
                                    <TextInput id="rif" v-model="form.rif" type="text" class="mt-1 block w-full" maxlength="12" />
                                    <InputError class="mt-1" :message="form.errors.rif" />
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <InputLabel for="state" value="Estado" />
                                    <select id="state" v-model="stateId" :class="selectClass" @change="chooseState">
                                        <option value="">Sin indicar</option>
                                        <option v-for="state in options.states" :key="state.id" :value="state.id">{{ state.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="municipality" value="Municipio" />
                                    <select id="municipality" v-model="municipalityId" :class="selectClass" :disabled="!canManage || !stateId" @change="chooseMunicipality">
                                        <option value="">Sin indicar</option>
                                        <option v-for="municipality in municipalities" :key="municipality.id" :value="municipality.id">{{ municipality.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="parish" value="Parroquia" />
                                    <select id="parish" v-model="form.parish_id" :class="selectClass" :disabled="!canManage || !municipalityId">
                                        <option :value="null">Sin indicar</option>
                                        <option v-for="parish in parishes" :key="parish.id" :value="parish.id">{{ parish.name }}</option>
                                    </select>
                                    <InputError class="mt-1" :message="form.errors.parish_id" />
                                </div>
                            </div>

                            <div>
                                <InputLabel for="address" value="Dirección" />
                                <TextInput id="address" v-model="form.address" type="text" class="mt-1 block w-full" maxlength="500" />
                                <InputError class="mt-1" :message="form.errors.address" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="phone" value="Teléfono" />
                                    <TextInput id="phone" v-model="form.phone" type="tel" class="mt-1 block w-full" maxlength="30" />
                                    <InputError class="mt-1" :message="form.errors.phone" />
                                </div>
                                <div>
                                    <InputLabel for="email" value="Correo" />
                                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" maxlength="255" />
                                    <InputError class="mt-1" :message="form.errors.email" />
                                </div>
                            </div>

                            <div>
                                <InputLabel for="type" value="Tipo de plantel" />
                                <select v-if="canManage" id="type" v-model="form.type" :class="selectClass" required>
                                    <option value="" disabled>Elige uno</option>
                                    <option v-for="type in options.types" :key="type.code" :value="type.code">{{ type.name }}</option>
                                </select>
                                <p v-else class="mt-1 text-sm text-gray-800">{{ typeName ?? 'Sin indicar' }}</p>
                                <InputError class="mt-1" :message="form.errors.type" />
                            </div>

                            <fieldset>
                                <legend class="block text-sm font-medium text-gray-700">Niveles que ofrece</legend>
                                <div class="mt-2 space-y-2">
                                    <label v-for="level in options.levels" :key="level.code" class="flex items-center gap-2 text-sm text-gray-800">
                                        <input v-model="form.levels" type="checkbox" :value="level.code" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        {{ level.name }}
                                    </label>
                                </div>
                                <InputError class="mt-1" :message="form.errors.levels" />
                            </fieldset>
                        </fieldset>

                        <div v-if="canManage" class="flex justify-end">
                            <PrimaryButton :disabled="form.processing">Guardar datos</PrimaryButton>
                        </div>
                    </form>
                </section>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Logo</h3>
                    <img v-if="logoUrl" :src="logoUrl" alt="Logo del plantel" class="mb-4 h-24 w-auto object-contain" />
                    <p v-else class="mb-4 text-sm text-gray-600">El plantel no tiene logo.</p>

                    <form v-if="canManage" class="space-y-3" @submit.prevent="uploadLogo">
                        <input type="file" accept="image/png,image/jpeg" class="block w-full text-sm" @input="logoForm.file = $event.target.files[0]" />
                        <p class="text-xs text-gray-500">PNG o JPEG de hasta 1 MB.</p>
                        <InputError :message="logoForm.errors.file" />
                        <div class="flex flex-wrap gap-2">
                            <PrimaryButton :disabled="logoForm.processing || !logoForm.file">Subir logo</PrimaryButton>
                            <DangerButton v-if="logoUrl" type="button" @click="removeLogo">Quitar logo</DangerButton>
                        </div>
                    </form>
                </section>

                <section v-if="details.signatures" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="mb-1 text-lg font-medium text-gray-900">Firmas</h3>
                    <p class="mb-4 text-sm text-gray-600">Salen en los documentos del plantel. Solo quien administra el plantel las ve.</p>

                    <form v-for="(roleName, role) in roleNames" :key="role" class="space-y-3 border-t border-gray-100 py-4 first-of-type:border-t-0" @submit.prevent="uploadSignature(role)">
                        <h4 class="font-medium text-gray-800">{{ roleName }}</h4>
                        <img v-if="signatureOf(role)" :src="route('institution.signatures.show', role) + '?v=' + signatureVersion" :alt="'Firma de ' + roleName" class="h-16 w-auto object-contain" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel :for="role + '_name'" value="Nombre de quien firma" />
                                <TextInput :id="role + '_name'" v-model="signatureForms[role].signer_name" type="text" class="mt-1 block w-full" maxlength="120" required />
                                <InputError class="mt-1" :message="signatureForms[role].errors.signer_name" />
                            </div>
                            <div>
                                <InputLabel :for="role + '_title'" value="Cargo" />
                                <TextInput :id="role + '_title'" v-model="signatureForms[role].signer_title" type="text" class="mt-1 block w-full" maxlength="120" required />
                                <InputError class="mt-1" :message="signatureForms[role].errors.signer_title" />
                            </div>
                        </div>

                        <input type="file" accept="image/png,image/jpeg" class="block w-full text-sm" @input="signatureForms[role].file = $event.target.files[0]" />
                        <InputError :message="signatureForms[role].errors.file" />

                        <div class="flex flex-wrap gap-2">
                            <PrimaryButton :disabled="signatureForms[role].processing || !signatureForms[role].file">Guardar firma</PrimaryButton>
                            <DangerButton v-if="signatureOf(role)" type="button" @click="removeSignature(role)">Quitar firma</DangerButton>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
