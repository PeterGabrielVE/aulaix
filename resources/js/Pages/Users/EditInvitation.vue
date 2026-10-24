<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    invitation: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.invitation.name,
    email: props.invitation.email,
});

const submit = () => {
    form.put(route('invitations.update', props.invitation.id));
};

const resend = () => {
    router.post(route('invitations.resend', props.invitation.id), {}, { preserveScroll: true });
};

const cancel = () => {
    if (confirm('¿Anular esta invitación? Su enlace dejará de funcionar.')) {
        router.delete(route('invitations.destroy', props.invitation.id));
    }
};
</script>

<template>
    <Head :title="`Invitación a ${invitation.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                Invitación pendiente
            </h1>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-8">
                    <form class="max-w-xl space-y-6" @submit.prevent="submit">
                        <p class="text-sm text-gray-600">
                            Roles: {{ invitation.roles.join(', ') }}. Vence el
                            {{ invitation.expiresAt }}. Si corriges el nombre o
                            el correo, se envía un enlace nuevo y el anterior
                            deja de funcionar.
                        </p>

                        <div>
                            <InputLabel for="name" value="Nombre" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                autocomplete="off"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Correo electrónico" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                                autocomplete="off"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <Link
                                :href="route('users.index')"
                                class="rounded-md text-sm text-gray-600 underline hover:text-gray-900"
                            >
                                Volver
                            </Link>
                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                Corregir y reenviar
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <div class="flex max-w-xl flex-wrap gap-4 bg-white p-4 shadow-sm sm:rounded-lg sm:p-8">
                    <SecondaryButton @click="resend">Reenviar invitación</SecondaryButton>
                    <DangerButton @click="cancel">Anular invitación</DangerButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
