<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// SPEC-014 PLT-R04.3, R04.4, R05: one institution and its usage, figures
// only. A SuperAdmin switches it off or on, with a reason.
const props = defineProps({
    institution: { type: Object, required: true },
    usage: { type: Object, required: true },
});

const isSuperAdmin = computed(() => usePage().props.platform?.operator?.role === 'super_admin');
const isActive = computed(() => props.institution.status === 'active');
const roleNames = { Administrador: 'Administradores', Director: 'Directores', Coordinador: 'Coordinadores', Docente: 'Docentes', Representante: 'Representantes', Estudiante: 'Estudiantes' };

const storage = computed(() => {
    const bytes = props.usage.usedBytes;
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;

    return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
});
const lastActivity = computed(() => (props.usage.lastActivity ? new Date(props.usage.lastActivity).toLocaleString('es-VE') : 'Sin actividad'));

const form = useForm({ active: false, reason: '' });
const submit = () => {
    form.active = !isActive.value;
    form.put(route('platform.institutions.active', props.institution.id), { preserveScroll: true, onSuccess: () => form.reset() });
};
</script>

<template>
    <Head :title="institution.name" />

    <PlatformLayout :title="institution.name">
        <p class="text-sm">
            <Link :href="route('platform.institutions')" class="font-medium text-indigo-700 hover:underline">← Planteles</Link>
        </p>

        <section aria-labelledby="datos" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="datos" class="text-lg font-medium text-gray-900">Plantel</h2>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-gray-700">Subdominio</dt>
                    <dd class="font-medium text-gray-900">{{ institution.subdomain }}</dd>
                </div>
                <div>
                    <dt class="text-gray-700">Estado</dt>
                    <dd class="font-medium" :class="isActive ? 'text-green-800' : 'text-red-800'">{{ isActive ? 'Activo' : 'Inactivo' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-700">Alta</dt>
                    <dd class="font-medium text-gray-900">{{ institution.createdAt }}</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="uso" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="uso" class="text-lg font-medium text-gray-900">Uso</h2>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-gray-700">Estudiantes activos</dt>
                    <dd class="text-2xl font-semibold text-gray-900">{{ usage.activeStudents }}</dd>
                </div>
                <div>
                    <dt class="text-gray-700">Año escolar activo</dt>
                    <dd class="font-medium text-gray-900">{{ usage.activeYear ?? 'Sin año activo' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-700">Última actividad</dt>
                    <dd class="font-medium text-gray-900">{{ lastActivity }}</dd>
                </div>
                <div>
                    <dt class="text-gray-700">Espacio de sus archivos</dt>
                    <dd class="font-medium text-gray-900">{{ storage }}</dd>
                </div>
            </dl>
            <h3 class="mt-5 text-sm font-medium text-gray-900">Miembros activos por rol</h3>
            <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-3">
                <div v-for="(count, role) in usage.members" :key="role" class="flex justify-between rounded-md bg-gray-50 px-3 py-2">
                    <dt class="text-gray-700">{{ roleNames[role] ?? role }}</dt>
                    <dd class="font-medium text-gray-900">{{ count }}</dd>
                </div>
            </dl>
        </section>

        <section v-if="isSuperAdmin" aria-labelledby="estado" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="estado" class="text-lg font-medium text-gray-900">{{ isActive ? 'Desactivar el plantel' : 'Reactivar el plantel' }}</h2>
            <p class="mt-1 text-sm text-gray-700">
                {{ isActive
                    ? 'Nadie podrá entrar mientras siga inactivo; no se borra ningún dato. Sus Administradores recibirán un correo con el motivo.'
                    : 'Sus miembros podrán entrar de nuevo. Sus Administradores recibirán un correo.' }}
            </p>
            <form class="mt-3 grid gap-3" @submit.prevent="submit">
                <div>
                    <InputLabel for="reason" :value="isActive ? 'Motivo' : 'Motivo (opcional)'" />
                    <textarea id="reason" v-model="form.reason" rows="2" maxlength="500" :required="isActive" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <InputError :message="form.errors.reason" />
                </div>
                <button
                    type="submit"
                    class="justify-self-start rounded-md px-4 py-2 text-sm font-semibold text-white focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600 disabled:opacity-50"
                    :class="isActive ? 'bg-red-700 hover:bg-red-600' : 'bg-indigo-700 hover:bg-indigo-600'"
                    :disabled="form.processing"
                >
                    {{ isActive ? 'Desactivar' : 'Reactivar' }}
                </button>
            </form>
        </section>
    </PlatformLayout>
</template>
