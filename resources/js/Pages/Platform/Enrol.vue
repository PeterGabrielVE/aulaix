<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

// SPEC-014 PLT-R01.6, R03.1, R03.7: the operator scans the QR, confirms it
// with a first code and, when joining, chooses its password.
const props = defineProps({
    token: { type: String, required: true },
    enrolment: { type: Object, required: true },
});

const form = useForm({ password: '', password_confirmation: '', code: '' });

const submit = () => form
    .transform((data) => (props.enrolment.needsPassword ? data : { code: data.code }))
    .post(route('platform.invitation.store', { token: props.token }), { onFinish: () => form.reset('code') });
</script>

<template>
    <Head title="Activar acceso al panel" />

    <PlatformLayout :title="enrolment.needsPassword ? 'Activa tu acceso' : 'Configura tu segundo factor'">
        <p class="text-sm text-gray-700">{{ enrolment.name }} · {{ enrolment.email }}</p>

        <form class="mt-4 flex flex-col gap-4" @submit.prevent="submit">
            <template v-if="enrolment.needsPassword">
                <div>
                    <InputLabel for="password" value="Contraseña" />
                    <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
                    <InputError :message="form.errors.password" />
                </div>
                <div>
                    <InputLabel for="password_confirmation" value="Repite la contraseña" />
                    <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
                </div>
            </template>

            <div>
                <p class="text-sm text-gray-700">Escanea este código con tu app de autenticación (Google Authenticator, Authy…):</p>
                <img :src="enrolment.qrImage" alt="Código QR para la app de autenticación" class="mt-2 h-48 w-48 rounded-md border border-gray-200 bg-white p-2" />
                <p class="mt-2 text-sm text-gray-700">
                    ¿No puedes escanearlo? Escribe esta clave: <code class="break-all rounded bg-gray-100 px-1 font-mono">{{ enrolment.secret }}</code>
                </p>
            </div>

            <div>
                <InputLabel for="code" value="Código de 6 dígitos que muestra la app" />
                <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                <InputError :message="form.errors.code" />
            </div>

            <PrimaryButton class="self-start" :disabled="form.processing">Confirmar</PrimaryButton>
        </form>
    </PlatformLayout>
</template>
