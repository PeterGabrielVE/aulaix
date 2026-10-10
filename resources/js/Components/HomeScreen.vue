<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

// Shared shell for each role's home screen (Pages/Home/*). `links` are the
// shortcuts that role has so far; roles without modules yet pass none.
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    links: {
        type: Array,
        default: () => [],
    },
});

const user = usePage().props.auth.user;
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ title }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-xs sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <p class="text-lg font-medium">Hola, {{ user.name }}.</p>
                        <p class="mt-1 text-sm text-gray-600">{{ description }}</p>
                    </div>
                </div>

                <div v-if="links.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="link in links"
                        :key="link.route"
                        :href="route(link.route)"
                        class="block rounded-lg bg-white p-6 shadow-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                    >
                        <span class="block font-medium text-gray-900">{{ link.label }}</span>
                        <span class="mt-1 block text-sm text-gray-600">{{ link.description }}</span>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
