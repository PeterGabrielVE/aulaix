<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    entry: {
        type: Object,
        required: true,
    },
});

const ORIGINS = {
    web: 'Web',
    console: 'Consola',
    system: 'Sistema',
};

function display(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return Array.isArray(value) ? value.join(', ') : String(value);
}
</script>

<template>
    <Head title="Registro de la bitácora" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Registro #{{ entry.id }}
                </h2>
                <Link :href="route('audit.index')" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    Volver a la bitácora
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                <dl class="grid grid-cols-1 gap-4 bg-white p-6 text-sm shadow-xs sm:grid-cols-2 sm:rounded-lg">
                    <div>
                        <dt class="text-gray-500">Fecha (hora de Venezuela)</dt>
                        <dd class="font-medium text-gray-900">{{ entry.occurredAt }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Evento</dt>
                        <dd class="font-mono text-xs text-gray-900">{{ entry.eventType }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Objeto</dt>
                        <dd class="font-medium text-gray-900">{{ entry.subject }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Autor</dt>
                        <dd class="font-medium text-gray-900">{{ entry.actor }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Origen</dt>
                        <dd class="font-medium text-gray-900">
                            {{ ORIGINS[entry.origin] ?? entry.origin }}<span v-if="entry.ip"> · {{ entry.ip }}</span>
                        </dd>
                    </div>
                </dl>

                <div class="overflow-x-auto bg-white shadow-xs sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th scope="col" class="px-6 py-3">Campo</th>
                                <th scope="col" class="px-6 py-3">Antes</th>
                                <th scope="col" class="px-6 py-3">Después</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="change in entry.changes" :key="change.field">
                                <td class="px-6 py-4 font-mono text-xs text-gray-900">{{ change.field }}</td>
                                <td class="px-6 py-4 text-gray-700">{{ display(change.from) }}</td>
                                <td class="px-6 py-4 text-gray-700">{{ display(change.to) }}</td>
                            </tr>
                            <tr v-if="!entry.changes.length">
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                    Este evento no guarda valores: solo deja constancia de que ocurrió.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
