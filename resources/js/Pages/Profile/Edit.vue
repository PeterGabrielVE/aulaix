<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LeaveInstitutionForm from './Partials/LeaveInstitutionForm.vue';
import { Head, usePage } from '@inertiajs/vue3';

defineProps({
    // The account is managed on the central domain (IDG-R03.1).
    accountUrl: {
        type: String,
        required: true,
    },
    status: {
        type: String,
        default: null,
    },
});

const user = usePage().props.auth.user;
</script>

<template>
    <Head title="Mi perfil" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                Mi perfil
            </h1>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-8">
                    <section class="max-w-xl">
                        <h3 class="text-lg font-medium text-gray-900">Tu cuenta de AulaX</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ user.name }} · {{ user.email }}
                        </p>
                        <p class="mt-2 text-sm text-gray-600">
                            Tu nombre, tu correo y tu contraseña son los mismos en
                            todos tus planteles. Se gestionan desde tu cuenta.
                        </p>
                        <!-- Another domain: a full page load. -->
                        <a
                            :href="accountUrl"
                            class="mt-4 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                        >
                            Gestionar mi cuenta
                        </a>
                    </section>
                </div>

                <div class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-8">
                    <LeaveInstitutionForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
