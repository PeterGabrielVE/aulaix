<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserForm from './Partials/UserForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    roles: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    email: '',
    role: '',
});

const submit = () => {
    form.post(route('users.store'));
};
</script>

<template>
    <Head title="Nuevo usuario" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Nuevo usuario
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <div class="max-w-xl">
                        <p class="mb-6 text-sm text-gray-600">
                            Le enviaremos un correo con un enlace para que elija
                            su contraseña y active su cuenta.
                        </p>

                        <UserForm
                            :form="form"
                            :roles="roles"
                            submit-label="Crear y enviar invitación"
                            @submit="submit"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
