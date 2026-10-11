<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    entries: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const form = reactive({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    event_type: props.filters.event_type ?? '',
    subject_type: props.filters.subject_type ?? '',
    subject_id: props.filters.subject_id ?? '',
});

function activeFilters() {
    return Object.fromEntries(Object.entries(form).filter(([, value]) => value !== ''));
}

function search() {
    router.get(route('audit.index'), activeFilters(), { preserveState: true });
}

function clear() {
    Object.keys(form).forEach((key) => (form[key] = ''));
    router.get(route('audit.index'));
}

function pageUrl(page) {
    return route('audit.index', { ...activeFilters(), page });
}
</script>

<template>
    <Head title="Bitácora" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Bitácora
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <form
                    class="grid grid-cols-1 gap-4 bg-white p-4 shadow-xs sm:grid-cols-2 sm:rounded-lg lg:grid-cols-5"
                    @submit.prevent="search"
                >
                    <div>
                        <InputLabel for="from" value="Desde" />
                        <TextInput id="from" v-model="form.from" type="date" class="mt-1 block w-full" />
                        <InputError :message="errors.from" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="to" value="Hasta" />
                        <TextInput id="to" v-model="form.to" type="date" class="mt-1 block w-full" />
                        <InputError :message="errors.to" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="event_type" value="Evento" />
                        <TextInput
                            id="event_type"
                            v-model="form.event_type"
                            placeholder="evaluation.grade.changed"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <div>
                        <InputLabel for="subject_type" value="Objeto" />
                        <TextInput id="subject_type" v-model="form.subject_type" placeholder="grade" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="subject_id" value="Identificador" />
                        <TextInput id="subject_id" v-model="form.subject_id" class="mt-1 block w-full" />
                    </div>
                    <div class="flex gap-2 sm:col-span-2 lg:col-span-5">
                        <PrimaryButton type="submit">Filtrar</PrimaryButton>
                        <SecondaryButton type="button" @click="clear">Limpiar</SecondaryButton>
                    </div>
                </form>

                <div class="overflow-x-auto bg-white shadow-xs sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th scope="col" class="px-6 py-3">Fecha (hora de Venezuela)</th>
                                <th scope="col" class="px-6 py-3">Evento</th>
                                <th scope="col" class="px-6 py-3">Objeto</th>
                                <th scope="col" class="px-6 py-3">Autor</th>
                                <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="entry in entries.data" :key="entry.id">
                                <td class="whitespace-nowrap px-6 py-4 text-gray-700">{{ entry.occurredAt }}</td>
                                <td class="px-6 py-4 font-mono text-xs text-gray-900">{{ entry.eventType }}</td>
                                <td class="px-6 py-4 text-gray-700">{{ entry.subject }}</td>
                                <td class="px-6 py-4 text-gray-700">{{ entry.actor }}</td>
                                <td class="px-6 py-4 text-right">
                                    <Link
                                        :href="route('audit.show', entry.id)"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Ver
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!entries.data.length">
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    No hay registros para estos filtros.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="entries.lastPage > 1"
                    class="flex items-center justify-between text-sm text-gray-700"
                    aria-label="Paginación"
                >
                    <Link
                        v-if="entries.page > 1"
                        :href="pageUrl(entries.page - 1)"
                        preserve-state
                        class="rounded-md bg-white px-3 py-1 hover:bg-gray-50"
                    >Anterior</Link>
                    <span v-else />
                    <span>Página {{ entries.page }} de {{ entries.lastPage }} · {{ entries.total }} registros</span>
                    <Link
                        v-if="entries.page < entries.lastPage"
                        :href="pageUrl(entries.page + 1)"
                        preserve-state
                        class="rounded-md bg-white px-3 py-1 hover:bg-gray-50"
                    >Siguiente</Link>
                    <span v-else />
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
