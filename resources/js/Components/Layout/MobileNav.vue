<script setup>
import SideNav from '@/Components/Layout/SideNav.vue';
import { useFocusTrap } from '@/Composables/useFocusTrap';
import { ref, toRef } from 'vue';

// The menu as a panel below 1024 px (SPEC-013 NAV-R01.2, R01.3): it keeps
// the focus while open and closes with its button, Escape or a navigation.
const props = defineProps({
    open: { type: Boolean, required: true },
    navigation: { type: Array, required: true },
});

const emit = defineEmits(['close']);

const panel = ref(null);
useFocusTrap(panel, toRef(props, 'open'), () => emit('close'));
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-40 lg:hidden">
        <div class="absolute inset-0 bg-gray-900/50" aria-hidden="true" @click="emit('close')" />
        <div
            id="mobile-menu"
            ref="panel"
            role="dialog"
            aria-modal="true"
            aria-label="Menú"
            class="relative flex h-full w-72 max-w-[85vw] flex-col overflow-y-auto bg-white shadow-xl"
        >
            <div class="flex items-center justify-end border-b border-gray-100 p-2">
                <button
                    type="button"
                    class="rounded-md p-2 text-gray-600 hover:bg-gray-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600"
                    aria-label="Cerrar el menú"
                    @click="emit('close')"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <SideNav :navigation="navigation" @navigate="emit('close')" />
        </div>
    </div>
</template>
