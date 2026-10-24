<script setup>
import { Link } from '@inertiajs/vue3';

// What needs someone to act (SPEC-013 NAV-R05, R06): each item with its
// number of cases and a link to where it is solved (NAV-R04.3, R04.4).
defineProps({
    items: { type: Array, required: true },
});

const plural = (count, one, many) => (count === 1 ? one : many);

const texts = {
    no_active_year: (item) => ['No hay año escolar activo', item.detail === 'planned' ? 'Hay un año planificado: actívalo para empezar.' : 'Planifica el año escolar y sus lapsos.'],
    grades_without_sections: (item) => [`${item.count} ${plural(item.count, 'grado ofrecido sin secciones', 'grados ofrecidos sin secciones')}`, 'Ábrelas antes de inscribir.'],
    sections_without_guide: (item) => [`${item.count} ${plural(item.count, 'sección sin docente guía', 'secciones sin docente guía')}`, null],
    subjects_without_teacher: (item) => [`${item.count} ${plural(item.count, 'materia sin docente en una sección', 'materias sin docente en alguna sección')}`, null],
    assignments_of_inactive_people: (item) => [`${item.count} ${plural(item.count, 'asignación de alguien que ya no está activo', 'asignaciones de personas que ya no están activas')}`, 'Reasígnalas.'],
    pending_transfers: (item) => [`${item.count} ${plural(item.count, 'solicitud de traslado por responder', 'solicitudes de traslado por responder')}`, null],
    pending_corrections: (item) => [`${item.count} ${plural(item.count, 'corrección por atender', 'correcciones por atender')}`, null],
    pending_invitations: (item) => [`${item.count} ${plural(item.count, 'invitación sin aceptar', 'invitaciones sin aceptar')}`, null],
    expired_invitations: (item) => [`${item.count} ${plural(item.count, 'invitación vencida', 'invitaciones vencidas')}`, 'Reenvíalas o cancélalas.'],
    missing_levels: () => ['Faltan los niveles que ofrece el plantel', 'Impide organizar la estructura.'],
    missing_dea_code: () => ['Falta el código DEA', 'Impide emitir documentos oficiales.'],
    missing_director_signature: () => ['Falta la firma del Director titular', 'Impide emitir constancias y boletines.'],
    missing_rif: () => ['Falta el RIF del plantel', 'Impide cobrar a los representantes.'],
    missing_dependency: () => ['Falta la dependencia del plantel', 'Indica si es nacional, estadal o municipal.'],
};
</script>

<template>
    <section aria-labelledby="pendientes" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
        <h3 id="pendientes" class="text-lg font-medium text-gray-900">Pendientes</h3>
        <p v-if="!items.length" class="mt-2 text-sm text-gray-700">No hay pendientes.</p>
        <ul v-else class="mt-3 divide-y divide-gray-100">
            <li v-for="item in items" :key="item.key">
                <Link :href="item.href" class="flex flex-col gap-0.5 rounded-md px-2 py-3 hover:bg-gray-50 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600">
                    <span class="text-sm font-medium text-amber-900">{{ texts[item.key]?.(item)[0] ?? item.key }}</span>
                    <span v-if="texts[item.key]?.(item)[1]" class="text-sm text-gray-700">{{ texts[item.key](item)[1] }}</span>
                </Link>
            </li>
        </ul>
    </section>
</template>
