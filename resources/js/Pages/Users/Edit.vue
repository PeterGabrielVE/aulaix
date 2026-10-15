<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserForm from './Partials/UserForm.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
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
    roles: [...props.user.roles],
    status: props.user.status,
});

const isSelf = computed(() => usePage().props.auth.user.id === props.user.id);

const submit = () => {
    form.put(route('users.update', props.user.id));
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
                        <div class="mb-6">
                            <p class="font-medium text-gray-900">{{ user.name }}</p>
                            <p class="text-sm text-gray-500">{{ user.email }}</p>
                            <p class="mt-2 text-sm text-gray-600">
                                El nombre y el correo los gestiona la propia
                                persona desde su cuenta.
                            </p>
                        </div>

                        <p v-if="isSelf" class="mb-6 text-sm text-gray-600">
                            Estás editando tu propia membresía: no puedes
                            suspenderla, quitarte el rol de Administrador ni
                            quedarte sin acceso a la gestión de usuarios.
                        </p>

                        <UserForm
                            :form="form"
                            :roles="roles"
                            :show-identity="false"
                            show-status
                            submit-label="Guardar cambios"
                            @submit="submit"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
