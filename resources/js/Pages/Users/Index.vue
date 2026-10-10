<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const search = ref(props.filters.search);

let debounceTimer = null;

watch(search, (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            route('users.index'),
            value ? { search: value } : {},
            { preserveState: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Usuarios
                </h2>
                <Link
                    :href="route('users.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Nuevo usuario
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <TextInput
                    v-model="search"
                    type="search"
                    placeholder="Buscar por nombre o correo..."
                    class="block w-full sm:max-w-sm"
                />

                <div class="overflow-x-auto bg-white shadow-xs sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th scope="col" class="px-6 py-3">Nombre</th>
                                <th scope="col" class="px-6 py-3">Roles</th>
                                <th scope="col" class="px-6 py-3">Estado</th>
                                <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="user in users.data" :key="user.id">
                                <td class="px-6 py-4">
                                    <span class="block font-medium text-gray-900">{{ user.name }}</span>
                                    <span class="block text-gray-500">{{ user.email }}</span>
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    {{ user.roles.length ? user.roles.join(', ') : 'Sin rol' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-if="user.status === 'active'"
                                            class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800"
                                        >Activo</span>
                                        <span
                                            v-else
                                            class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700"
                                        >Inactivo</span>
                                        <span
                                            v-if="user.invitation_pending"
                                            class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                        >Invitación pendiente</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link
                                        :href="route('users.edit', user.id)"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Editar
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!users.data.length">
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                    No se encontraron usuarios.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="users.last_page > 1"
                    class="flex flex-wrap gap-1"
                    aria-label="Paginación"
                >
                    <template v-for="link in users.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            class="rounded-md px-3 py-1 text-sm"
                            :class="link.active ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        ><span v-html="link.label" /></Link>
                        <span
                            v-else
                            class="rounded-md px-3 py-1 text-sm text-gray-400"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
