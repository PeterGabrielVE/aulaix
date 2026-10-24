<script setup>
import { Link } from '@inertiajs/vue3';

// The institution's figures (SPEC-013 NAV-R05.1, R06.1), each linked to its
// screen when the person may open it.
defineProps({
    indicators: { type: Array, required: true },
});

const ordinals = { 1: '1.er lapso', 2: '2.º lapso', 3: '3.er lapso' };
const labels = {
    school_year: 'Año escolar',
    open_term: 'Lapso abierto',
    sections: 'Secciones',
    capacity: 'Cupos',
    active_students: 'Estudiantes activos',
    staff: 'Personal activo',
};
const staffRoles = { Administrador: 'Administración', Director: 'Dirección', Coordinador: 'Coordinación', Docente: 'Docentes' };

const display = (indicator) => {
    switch (indicator.key) {
        case 'school_year':
            return indicator.value ?? 'Sin año activo';
        case 'open_term':
            return ordinals[indicator.value] ?? 'Ninguno';
        default:
            return indicator.value;
    }
};
</script>

<template>
    <section aria-labelledby="indicadores" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
        <h2 id="indicadores" class="text-lg font-medium text-gray-900">El plantel en cifras</h2>
        <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div v-for="indicator in indicators" :key="indicator.key" class="rounded-md border border-gray-100 p-3" :class="{ 'col-span-2 sm:col-span-3': indicator.key === 'staff' }">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-600">{{ labels[indicator.key] ?? indicator.key }}</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-900">
                    <ul v-if="indicator.key === 'staff'" class="flex flex-wrap gap-x-6 gap-y-1 text-sm font-normal">
                        <li v-for="(name, role) in staffRoles" :key="role"><span class="font-semibold">{{ indicator.value[role] ?? 0 }}</span> {{ name }}</li>
                    </ul>
                    <Link v-else-if="indicator.href" :href="indicator.href" class="rounded-sm hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600">{{ display(indicator) }}</Link>
                    <span v-else>{{ display(indicator) }}</span>
                </dd>
            </div>
        </dl>
    </section>
</template>
