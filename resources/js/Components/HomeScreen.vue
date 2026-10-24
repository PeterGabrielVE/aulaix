<script setup>
import IndicatorGrid from '@/Components/Home/IndicatorGrid.vue';
import MyClasses from '@/Components/Home/MyClasses.vue';
import MyStudents from '@/Components/Home/MyStudents.vue';
import PendingList from '@/Components/Home/PendingList.vue';
import RecentActivity from '@/Components/Home/RecentActivity.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Shared shell for each role's home screen (Pages/Home/*): the blocks
// Workspace decided for this person (SPEC-013 NAV-R04), in its order.
defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
});

const page = usePage();
const user = page.props.auth.user;
const home = computed(() => page.props.home ?? null);

const noticeTexts = {
    student: 'Pronto podrás consultar aquí tus datos y tus notas.',
    no_active_year: 'No hay año escolar activo: cuando se active, verás aquí las cifras de tus grados.',
    no_coordinated_grades: 'Aún no tienes grados a tu cargo este año. La dirección los asigna en «Estructura».',
};
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ title }}</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
                    <p class="text-lg font-medium text-gray-900">Hola, {{ user.name }}.</p>
                    <p class="mt-1 text-sm text-gray-700">{{ description }}</p>
                </div>

                <template v-if="home">
                    <p v-for="notice in home.notices" :key="notice" class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-900">
                        {{ noticeTexts[notice] }}
                    </p>

                    <PendingList v-if="home.pending" :items="home.pending" />
                    <IndicatorGrid v-if="home.indicators?.length" :indicators="home.indicators" />
                    <MyClasses v-if="home.myClasses" :classes="home.myClasses" />
                    <MyStudents v-if="home.myStudents" :represented="home.myStudents" />
                    <RecentActivity v-if="home.recentActivity" :entries="home.recentActivity" />

                    <section v-if="home.shortcuts.length" aria-labelledby="accesos">
                        <h3 id="accesos" class="sr-only">Accesos directos</h3>
                        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <li v-for="shortcut in home.shortcuts" :key="shortcut.key">
                                <Link
                                    :href="shortcut.href"
                                    class="block h-full rounded-lg bg-white p-5 shadow-xs hover:bg-gray-50 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600"
                                >
                                    <span class="block font-medium text-gray-900">{{ shortcut.label }}</span>
                                    <span class="mt-1 block text-sm text-gray-700">{{ shortcut.description }}</span>
                                </Link>
                            </li>
                        </ul>
                    </section>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
