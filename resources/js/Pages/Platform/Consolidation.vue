<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

// SPEC-014 PLT-R06.2 – R06.5: decide a case with a reason (and the proof of
// ownership to consolidate), see what would change, then confirm.
const props = defineProps({
    case: { type: Object, required: true },
    preview: { type: Object, default: null },
});

const accountIds = props.case.accounts.map((account) => account.id);
const form = useForm({
    accounts: accountIds,
    decision: props.preview?.input.decision ?? 'consolidate',
    reason: props.preview?.input.reason ?? '',
    verification_ref: props.preview?.input.verification_ref ?? '',
});

// The preview is only valid for what it was made with.
const previewMatches = computed(() => props.preview !== null
    && props.preview.input.decision === form.decision
    && props.preview.input.reason === form.reason
    && (props.preview.input.verification_ref ?? '') === (form.verification_ref ?? ''));

const nameOf = (id) => props.case.accounts.find((account) => account.id === id)?.name ?? `#${id}`;
const plan = computed(() => {
    const preview = props.preview?.plan;
    if (!preview) return '';
    const affected = preview.affectedIds.map((id) => `#${id} ${nameOf(id)}`).join(', ');

    return preview.decision === 'consolidate'
        ? `Todo pasa a #${preview.survivorId} ${nameOf(preview.survivorId)}: sus planteles y roles se mueven, y se borran ${affected}.`
        : `Se mantienen separadas ${affected}: no se unificarán nunca.`;
});

watch(() => form.decision, (decision) => {
    if (decision === 'separate') form.verification_ref = '';
});

const showPreview = () => form.post(route('platform.consolidations.preview', accountIds[0]), { preserveScroll: true });
const resolve = () => form.post(route('platform.consolidations.resolve', accountIds[0]), { preserveScroll: true });
</script>

<template>
    <Head title="Caso de consolidación" />

    <PlatformLayout :title="`Caso ${props.case.email}`">
        <p class="text-sm">
            <Link :href="route('platform.consolidations')" class="font-medium text-indigo-700 hover:underline">← Consolidaciones</Link>
        </p>

        <section aria-labelledby="cuentas" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="cuentas" class="text-lg font-medium text-gray-900">Cuentas con este correo</h2>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                <li v-for="account in props.case.accounts" :key="account.id" class="py-2">
                    <p class="font-medium text-gray-900">#{{ account.id }} {{ account.name }}</p>
                    <p class="text-gray-700">{{ props.case.email }} · {{ account.institutions.join(', ') || 'sin plantel' }}</p>
                </li>
            </ul>
        </section>

        <section aria-labelledby="decision" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="decision" class="text-lg font-medium text-gray-900">Decisión</h2>
            <InputError class="mt-2" :message="form.errors.general" />
            <form class="mt-3 grid gap-4" @submit.prevent="showPreview">
                <fieldset>
                    <legend class="text-sm font-medium text-gray-700">¿Qué hacer con estas cuentas?</legend>
                    <div class="mt-2 flex flex-col gap-2 text-sm sm:flex-row sm:gap-6">
                        <label class="flex items-center gap-2"><input v-model="form.decision" type="radio" value="consolidate" class="text-indigo-600 focus:ring-indigo-500"> Consolidar en la más antigua</label>
                        <label class="flex items-center gap-2"><input v-model="form.decision" type="radio" value="separate" class="text-indigo-600 focus:ring-indigo-500"> Mantener separadas</label>
                    </div>
                    <InputError :message="form.errors.decision" />
                </fieldset>
                <div>
                    <InputLabel for="reason" value="Motivo" />
                    <textarea id="reason" v-model="form.reason" rows="2" maxlength="500" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <InputError :message="form.errors.reason" />
                </div>
                <div v-if="form.decision === 'consolidate'">
                    <InputLabel for="verification_ref" value="Referencia de la verificación de titularidad" />
                    <TextInput id="verification_ref" v-model="form.verification_ref" class="mt-1 block w-full" maxlength="120" required />
                    <InputError :message="form.errors.verification_ref" />
                </div>
                <button type="submit" class="justify-self-start rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600 disabled:opacity-50" :disabled="form.processing">Ver los cambios</button>
            </form>
        </section>

        <section v-if="previewMatches" aria-labelledby="vista-previa" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="vista-previa" class="text-lg font-medium text-gray-900">Cambios que se aplicarán</h2>
            <p class="mt-2 text-sm text-gray-900">{{ plan }}</p>
            <p class="mt-1 text-sm text-gray-700">No se puede deshacer.</p>
            <button type="button" class="mt-3 rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600 disabled:opacity-50" :disabled="form.processing" @click="resolve">Confirmar y aplicar</button>
        </section>
    </PlatformLayout>
</template>
