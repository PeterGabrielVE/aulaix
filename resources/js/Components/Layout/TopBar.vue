<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { computed } from 'vue';

// Where and as whom the person is working (SPEC-013 NAV-R02): institution,
// school year and term, name and primary role, and the account menu.
const props = defineProps({
    institution: { type: Object, default: null },
    layout: { type: Object, default: null },
    userName: { type: String, required: true },
    switchInstitutionUrl: { type: String, default: null },
    menuOpen: { type: Boolean, default: false },
});

defineEmits(['open-menu']);

const ordinals = { 1: '1.er', 2: '2.º', 3: '3.er' };
const calendarText = computed(() => {
    const calendar = props.layout?.calendar;

    if (!calendar?.year) {
        return 'Sin año escolar activo';
    }

    return `Año ${calendar.year} · ${calendar.term ? `${ordinals[calendar.term]} lapso` : 'sin lapso abierto'}`;
});
</script>

<template>
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6">
        <button
            type="button"
            class="-ms-2 rounded-md p-2 text-gray-600 hover:bg-gray-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600 lg:hidden"
            aria-controls="mobile-menu"
            :aria-expanded="menuOpen ? 'true' : 'false'"
            aria-label="Abrir el menú"
            @click="$emit('open-menu')"
        >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <div v-if="institution" class="flex min-w-0 items-center gap-2">
            <img v-if="institution.logoUrl" :src="institution.logoUrl" alt="" class="h-8 w-auto shrink-0 object-contain" />
            <span class="truncate text-sm font-semibold text-gray-900">{{ institution.name }}</span>
        </div>

        <p v-if="layout" class="hidden text-sm text-gray-700 md:block" :class="{ 'text-amber-800': !layout.calendar.year }">
            {{ calendarText }}
        </p>

        <div class="ms-auto">
            <Dropdown align="right" width="48">
                <template #trigger>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-md px-2 py-1 text-start text-sm hover:bg-gray-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600"
                    >
                        <span class="flex flex-col leading-tight">
                            <span class="max-w-[10rem] truncate font-medium text-gray-900">{{ userName }}</span>
                            <span v-if="layout?.person.primaryRole" class="text-xs text-gray-600">{{ layout.person.primaryRole }}</span>
                        </span>
                        <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </template>

                <template #content>
                    <p v-if="layout" class="border-b border-gray-100 px-4 py-2 text-xs text-gray-700 md:hidden">{{ calendarText }}</p>
                    <DropdownLink :href="route('profile.edit')">Mi perfil</DropdownLink>
                    <!-- Another domain: a full page load, not an Inertia visit. -->
                    <a
                        v-if="switchInstitutionUrl"
                        :href="switchInstitutionUrl"
                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-hidden"
                    >
                        Cambiar de plantel
                    </a>
                    <DropdownLink :href="route('logout')" method="post" as="button">Cerrar sesión</DropdownLink>
                </template>
            </Dropdown>
        </div>
    </header>
</template>
