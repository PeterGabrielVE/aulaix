<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import UserForm from './Partials/UserForm.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    roles: [...props.user.roles],
    status: props.user.status,
});

const isSelf = computed(() => usePage().props.auth.user.id === props.user.id);

const submit = () => {
    form.put(route('users.update', props.user.id));
};

const resendInvitation = () => {
    router.post(route('users.invitation', props.user.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Editar ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Editar usuario
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-8">
                    <div class="max-w-xl">
                        <p v-if="isSelf" class="mb-6 text-sm text-gray-600">
                            Estás editando tu propia cuenta: no puedes
                            desactivarla, quitarte el rol de Administrador ni
                            quedarte sin acceso a la gestión de usuarios.
                        </p>

                        <UserForm
                            :form="form"
                            :roles="roles"
                            show-status
                            submit-label="Guardar cambios"
                            @submit="submit"
                        />
                    </div>
                </div>

                <div
                    v-if="user.invitation_pending"
                    class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-8"
                >
                    <div class="max-w-xl">
                        <h3 class="text-lg font-medium text-gray-900">
                            Invitación pendiente
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ user.name }} todavía no ha activado su cuenta.
                            Si el enlace se perdió o venció, envía uno nuevo; el
                            anterior dejará de funcionar.
                        </p>
                        <SecondaryButton class="mt-4" @click="resendInvitation">
                            Reenviar invitación
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
