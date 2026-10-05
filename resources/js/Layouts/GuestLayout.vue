<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const institution = computed(() => usePage().props.institution);
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div class="flex flex-col items-center">
            <!-- `login` only exists on tenant subdomains; the central domain
                 (institution selector) has no {tenant} to build it with. -->
            <Link :href="institution ? route('login') : route('institutions.select')">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
            </Link>
            <p v-if="institution" class="mt-2 text-sm font-medium text-gray-600">
                {{ institution.name }}
            </p>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
