<script setup>
import { Link } from '@inertiajs/vue3';

// A guardian's own students and its requests still open (SPEC-013 NAV-R08).
defineProps({
    represented: { type: Object, required: true },
});
</script>

<template>
    <section aria-labelledby="mis-representados" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
        <h3 id="mis-representados" class="text-lg font-medium text-gray-900">Mis representados</h3>
        <p v-if="!represented.students.length" class="mt-2 text-sm text-gray-700">Aún no tienes representados en este plantel.</p>
        <ul v-else class="mt-3 divide-y divide-gray-100 text-sm">
            <li v-for="student in represented.students" :key="student.id">
                <Link :href="student.href" class="block rounded-md px-2 py-2 font-medium text-gray-900 hover:bg-gray-50 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600">
                    {{ student.name }}
                </Link>
            </li>
        </ul>
        <p v-if="represented.pendingCorrections" class="mt-3 text-sm text-amber-900">
            {{ represented.pendingCorrections === 1 ? 'Tienes 1 corrección sin atender.' : `Tienes ${represented.pendingCorrections} correcciones sin atender.` }}
        </p>
    </section>
</template>
