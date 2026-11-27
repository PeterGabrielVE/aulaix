<script setup>
import { Link } from '@inertiajs/vue3';

// The menu Workspace decides (SPEC-013 NAV-R03): groups with their entries,
// the current one marked for sight and for screen readers (NAV-R01.4).
defineProps({
    navigation: { type: Array, required: true },
});

defineEmits(['navigate']);
</script>

<template>
    <nav aria-label="Menú principal" class="flex flex-col gap-6 px-3 py-4 text-sm">
        <div v-for="group in navigation" :key="group.group ?? 'home'" class="flex flex-col gap-1">
            <h2 v-if="group.label" class="px-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                {{ group.label }}
            </h2>
            <ul class="flex flex-col gap-1">
                <li v-for="entry in group.entries" :key="entry.key">
                    <Link
                        :href="entry.href"
                        :aria-current="entry.active ? 'page' : undefined"
                        class="block rounded-md px-3 py-2 font-medium focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600"
                        :class="entry.active ? 'bg-indigo-50 text-indigo-800' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'"
                        @click="$emit('navigate')"
                    >
                        {{ entry.label }}
                    </Link>
                </li>
            </ul>
        </div>
    </nav>
</template>
