<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
    // CAL-R05.4: only an Administrador grants the special permission.
    canGrantClosedTermEditing: {
        type: Boolean,
        default: false,
    },
    // SPEC-010 MAT-R11.3: so is managing enrollments, person by person.
    canGrantEnrollmentManagement: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    roles: [...props.user.roles],
    status: props.user.status,
});

const isSelf = computed(() => usePage().props.auth.user.id === props.user.id);

const closedTermForm = useForm({ allowed: props.user.canEditClosedTermGrades });

const saveClosedTermPermission = () => {
    closedTermForm.put(route('users.closed-term-grades', props.user.id), { preserveScroll: true });
};

const enrollmentForm = useForm({ allowed: props.user.canManageEnrollments });

const saveEnrollmentPermission = () => {
    enrollmentForm.put(route('users.enrollment-management', props.user.id), { preserveScroll: true });
};

const submit = () => {
    form.put(route('users.update', props.user.id));
};
</script>

<template>
    <Head :title="`Editar ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                Editar usuario
            </h1>
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

                <div v-if="canGrantClosedTermEditing" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-8">
                    <form class="max-w-xl space-y-3" @submit.prevent="saveClosedTermPermission">
                        <h3 class="text-lg font-medium text-gray-900">Notas de lapsos cerrados</h3>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="closedTermForm.allowed" type="checkbox" class="rounded-sm border-gray-300" />
                            Puede editar notas de lapsos cerrados en este plantel
                        </label>
                        <p class="text-sm text-gray-600">
                            Es una excepción personal: concédela solo a quien deba corregir
                            notas ya entregadas, y retírala al terminar.
                        </p>
                        <InputError :message="closedTermForm.errors.closed_term_grades" />
                        <PrimaryButton :disabled="closedTermForm.processing">Guardar permiso</PrimaryButton>
                    </form>
                </div>

                <div v-if="canGrantEnrollmentManagement" class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-8">
                    <form class="max-w-xl space-y-3" @submit.prevent="saveEnrollmentPermission">
                        <h3 class="text-lg font-medium text-gray-900">Inscripciones</h3>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="enrollmentForm.allowed" type="checkbox" class="rounded-sm border-gray-300" />
                            Puede inscribir estudiantes en este plantel
                        </label>
                        <p class="text-sm text-gray-600">
                            Además de Administrador y Director. Las excepciones (edad fuera
                            de rango, cambio de grado) siguen siendo de un directivo.
                        </p>
                        <InputError :message="enrollmentForm.errors.enrollment_management" />
                        <PrimaryButton :disabled="enrollmentForm.processing">Guardar permiso</PrimaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
