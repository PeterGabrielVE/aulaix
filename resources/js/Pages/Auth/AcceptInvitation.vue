<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
    subdomain: {
        type: String,
        required: true,
    },
    // Who is logged in on the central domain, if anyone (IDG-R04.5).
    authenticatedEmail: {
        type: String,
        default: null,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const join = () => {
    router.post(route('invitations.join', props.subdomain), { email: props.email });
};

const submit = () => {
    form.post(route('invitations.store', props.subdomain), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Activar cuenta" />

        <h1 class="mb-1 text-lg font-semibold text-gray-900">
            Acepta la invitación
        </h1>
        <template v-if="authenticatedEmail">
            <template v-if="authenticatedEmail === email">
                <p class="mb-4 text-sm text-gray-600">
                    Únete al plantel con tu cuenta de AulaX ({{ authenticatedEmail }}).
                </p>
                <PrimaryButton @click="join">Aceptar la invitación</PrimaryButton>
            </template>
            <p v-else class="text-sm text-red-600">
                Esta invitación es para {{ email }}, pero iniciaste sesión como
                {{ authenticatedEmail }}. Cierra sesión y entra con el correo invitado.
            </p>
        </template>

        <template v-else>
        <p class="mb-4 text-sm text-gray-600">
            Crea tu cuenta de AulaX: elige la contraseña con la que vas a iniciar sesión.
            ¿Ya tienes cuenta?
            <Link :href="route('central.login')" class="text-indigo-600 underline hover:text-indigo-800">Inicia sesión</Link>
            para aceptarla.
        </p>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Correo electrónico" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full bg-gray-50"
                    v-model="form.email"
                    required
                    readonly
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Contraseña" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autofocus
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar contraseña"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Crear cuenta y aceptar
                </PrimaryButton>
            </div>
        </form>
        </template>
    </GuestLayout>
</template>
