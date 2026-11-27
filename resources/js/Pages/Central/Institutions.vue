<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    name: {
        type: String,
        required: true,
    },
    institutions: {
        type: Array,
        required: true,
    },
    invitations: {
        type: Array,
        required: true,
    },
    canConsolidate: {
        type: Boolean,
        required: true,
    },
});

const join = (subdomain) => {
    router.post(route('invitations.join', subdomain));
};

const open = (subdomain) => {
    router.post(route('central.open', subdomain));
};
</script>

<template>
    <GuestLayout>
        <Head title="Tus planteles" />

        <div class="flex items-start justify-between gap-4">
            <h1 class="mb-1 text-lg font-semibold text-gray-900">Hola, {{ name }}</h1>
            <Link
                :href="route('central.logout')"
                method="post"
                as="button"
                class="text-sm text-gray-600 underline hover:text-gray-900"
            >
                Cerrar sesión
            </Link>
        </div>

        <section v-if="institutions.length" class="mt-4">
            <h2 class="text-sm font-medium text-gray-700">Selecciona tu plantel</h2>
            <ul class="mt-2 space-y-2">
                <li v-for="institution in institutions" :key="institution.subdomain">
                    <button
                        type="button"
                        class="w-full rounded-md border border-gray-200 px-4 py-3 text-left text-sm font-medium text-gray-900 hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                        @click="open(institution.subdomain)"
                    >
                        {{ institution.name }}
                    </button>
                </li>
            </ul>
        </section>

        <section v-else class="mt-4 text-sm text-gray-600">
            <p>Todavía no perteneces a ningún plantel activo.</p>
            <p class="mt-1">
                Cuando un plantel te invite, verás aquí su invitación. También
                puedes
                <Link :href="route('institutions.select')" class="text-indigo-600 underline hover:text-indigo-800">
                    buscar tu plantel en el directorio</Link>.
            </p>
        </section>

        <section v-if="invitations.length" class="mt-6">
            <h2 class="text-sm font-medium text-gray-700">Invitaciones pendientes</h2>
            <ul class="mt-2 space-y-2">
                <li
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm"
                >
                    <p class="font-medium text-gray-900">{{ invitation.institution }}</p>
                    <p class="text-gray-600">
                        {{ invitation.roles.join(', ') }} · vence el {{ invitation.expiresAt }}
                    </p>
                    <PrimaryButton class="mt-2" @click="join(invitation.subdomain)">Aceptar</PrimaryButton>
                </li>
            </ul>
        </section>

        <section v-if="canConsolidate" class="mt-6 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
            Tienes cuentas en varios planteles con este mismo correo. Puedes
            unificarlas en una sola, con una única contraseña.
            <Link :href="route('central.consolidation')" class="mt-2 block font-medium underline">Unificar mis cuentas</Link>
        </section>
    </GuestLayout>
</template>
