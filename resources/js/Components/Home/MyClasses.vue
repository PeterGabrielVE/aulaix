<script setup>
import { Link } from '@inertiajs/vue3';

// A teacher's classes in the active year (SPEC-013 NAV-R07).
defineProps({
    classes: { type: Object, required: true },
});
</script>

<template>
    <section aria-labelledby="mis-clases" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 id="mis-clases" class="text-lg font-medium text-gray-900">Mis clases</h3>
            <Link :href="route('my-assignments')" class="text-sm font-medium text-indigo-700 hover:underline">Ver mis asignaciones</Link>
        </div>
        <p v-if="!classes.hasActiveYear" class="mt-2 text-sm text-gray-700">No hay año escolar activo.</p>
        <p v-else-if="!classes.teaching.length && !classes.guiding.length" class="mt-2 text-sm text-gray-700">Aún no tienes asignaciones este año.</p>
        <template v-else>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                <li v-for="item in classes.teaching" :key="`${item.section}-${item.subject}`" class="flex flex-wrap justify-between gap-2 py-2">
                    <span class="font-medium text-gray-900">{{ item.subject }}</span>
                    <span class="text-gray-700">{{ item.section }}</span>
                </li>
            </ul>
            <p v-if="classes.guiding.length" class="mt-3 text-sm text-gray-700">
                Docente guía de: <span class="font-medium text-gray-900">{{ classes.guiding.join(', ') }}</span>
            </p>
        </template>
    </section>
</template>
