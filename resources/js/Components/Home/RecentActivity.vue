<script setup>
import { Link } from '@inertiajs/vue3';

// The latest entries of the audit log (SPEC-013 NAV-R05.7).
defineProps({
    entries: { type: Array, required: true },
});
</script>

<template>
    <section aria-labelledby="actividad" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="actividad" class="text-lg font-medium text-gray-900">Actividad reciente</h2>
            <Link :href="route('audit.index')" class="text-sm font-medium text-indigo-700 hover:underline">Ver la bitácora</Link>
        </div>
        <p v-if="!entries.length" class="mt-2 text-sm text-gray-700">Aún no hay actividad registrada.</p>
        <ul v-else class="mt-3 divide-y divide-gray-100 text-sm">
            <li v-for="(entry, index) in entries" :key="index" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                <span class="font-mono text-xs text-gray-900">{{ entry.event }}</span>
                <span class="text-gray-700">{{ entry.actor ?? 'Sistema' }} · {{ entry.occurredAt }}</span>
            </li>
        </ul>
    </section>
</template>
