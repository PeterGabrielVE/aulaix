<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// The frame of the platform panel (SPEC-014), apart from the institutions'
// layout: no institution, no institution menu. Without a session it is a
// centred card (login, enrolment); with one, a top bar and the content.
defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const operator = computed(() => page.props.platform?.operator ?? null);
const flashSuccess = computed(() => page.props.flash?.success);
const roleNames = { support: 'Soporte', super_admin: 'Superadministrador' };
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <a
            href="#contenido"
            class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-indigo-800 focus:shadow-lg focus:ring-2 focus:ring-indigo-600"
        >
            Saltar al contenido
        </a>

        <header v-if="operator" class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                <Link :href="route('platform.home')" class="flex items-center gap-2" aria-label="Inicio del panel">
                    <ApplicationLogo class="h-8 w-auto fill-current text-gray-800" />
                    <span class="text-sm font-semibold text-gray-900">Plataforma</span>
                </Link>
                <div class="ms-auto flex items-center gap-3 text-sm">
                    <span class="text-gray-800">
                        {{ operator.name }} · <span class="text-gray-600">{{ roleNames[operator.role] }}</span>
                    </span>
                    <Link
                        :href="route('platform.logout')"
                        method="post"
                        as="button"
                        class="rounded-md px-2 py-1 font-medium text-indigo-700 hover:bg-gray-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600"
                    >
                        Cerrar sesión
                    </Link>
                </div>
            </div>
        </header>

        <main id="contenido" tabindex="-1" class="focus:outline-hidden" :class="operator ? 'py-8' : 'flex min-h-screen flex-col items-center px-4 pt-10 sm:justify-center sm:pt-0'">
            <div :class="operator ? 'mx-auto flex max-w-5xl flex-col gap-6 px-4 sm:px-6' : 'w-full sm:max-w-md'">
                <div v-if="!operator" class="mb-6 flex flex-col items-center gap-2">
                    <ApplicationLogo class="h-16 w-16 fill-current text-gray-500" />
                    <p class="text-sm font-medium text-gray-700">Plataforma AulaX</p>
                </div>

                <h1 class="text-xl font-semibold text-gray-900">{{ title }}</h1>

                <div v-if="flashSuccess" role="status" class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
                    {{ flashSuccess }}
                </div>

                <div :class="operator ? '' : 'mt-4 bg-white px-6 py-5 shadow-md sm:rounded-lg'">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
