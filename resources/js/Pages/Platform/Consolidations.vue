<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// SPEC-014 PLT-R06.1: the accounts marked for review, grouped by email.
defineProps({
    cases: { type: Array, required: true },
});
</script>

<template>
    <Head title="Consolidaciones" />

    <PlatformLayout title="Consolidaciones dudosas">
        <section aria-labelledby="casos" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="casos" class="text-lg font-medium text-gray-900">{{ cases.length }} {{ cases.length === 1 ? 'caso en revisión' : 'casos en revisión' }}</h2>
            <p v-if="cases.length === 0" class="mt-3 text-sm text-gray-700">No hay cuentas marcadas para revisión.</p>
            <ul v-else class="mt-3 divide-y divide-gray-100">
                <li v-for="item in cases" :key="item.email" class="py-3">
                    <Link :href="route('platform.consolidations.show', item.accounts[0].id)" class="font-medium text-indigo-700 hover:underline">{{ item.email }}</Link>
                    <ul class="mt-1 text-sm text-gray-700">
                        <li v-for="account in item.accounts" :key="account.id">#{{ account.id }} {{ account.name }} ({{ account.institutions.join(', ') || 'sin plantel' }})</li>
                    </ul>
                </li>
            </ul>
        </section>
    </PlatformLayout>
</template>
