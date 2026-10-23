<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import MobileNav from '@/Components/Layout/MobileNav.vue';
import SideNav from '@/Components/Layout/SideNav.vue';
import TopBar from '@/Components/Layout/TopBar.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

// The frame of every page of an institution (SPEC-013 NAV-R01): side menu
// from 1024 px, a panel below it, the top bar and the content.
const page = usePage();
const layout = computed(() => page.props.layout);
const navigation = computed(() => layout.value?.navigation ?? []);
const flashSuccess = computed(() => page.props.flash?.success);

const menuOpen = ref(false);
const stopListening = router.on('navigate', () => {
    menuOpen.value = false;
});
onBeforeUnmount(stopListening);
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <a
            href="#contenido"
            class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-indigo-800 focus:shadow-lg focus:ring-2 focus:ring-indigo-600"
        >
            Saltar al contenido
        </a>

        <aside class="fixed inset-y-0 start-0 hidden w-64 flex-col overflow-y-auto border-e border-gray-200 bg-white lg:flex">
            <Link :href="route('dashboard')" class="flex h-16 shrink-0 items-center border-b border-gray-100 px-6" aria-label="Inicio">
                <ApplicationLogo class="h-9 w-auto fill-current text-gray-800" />
            </Link>
            <SideNav :navigation="navigation" />
        </aside>

        <MobileNav :open="menuOpen" :navigation="navigation" @close="menuOpen = false" />

        <div class="flex min-h-screen flex-col lg:ps-64">
            <TopBar
                :institution="page.props.institution"
                :layout="layout"
                :user-name="page.props.auth.user.name"
                :switch-institution-url="page.props.switchInstitutionUrl"
                :menu-open="menuOpen"
                @open-menu="menuOpen = true"
            />

            <main id="contenido" tabindex="-1" class="flex-1 focus:outline-hidden">
                <div v-if="$slots.header" class="border-b border-gray-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <slot name="header" />
                    </div>
                </div>

                <div v-if="flashSuccess" class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div role="status" class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
                        {{ flashSuccess }}
                    </div>
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
