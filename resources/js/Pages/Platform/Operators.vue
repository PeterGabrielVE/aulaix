<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// SPEC-014 PLT-R03: the platform's operators, for a SuperAdmin. Every
// change asks for a fresh TOTP when the last one is older than 10 minutes.
defineProps({
    operators: { type: Array, required: true },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const roleNames = { support: 'Soporte', super_admin: 'Superadministrador' };
const statusNames = { pending: 'Pendiente', active: 'Activo', inactive: 'Desactivado' };

const invite = useForm({ name: '', email: '', role: 'support' });
const submitInvite = () => invite.post(route('platform.operators.store'), { preserveScroll: true, onSuccess: () => invite.reset() });

const changeRole = (operator, role) => router.put(route('platform.operators.role', operator.id), { role }, { preserveScroll: true });
const setActive = (operator, active) => router.put(route('platform.operators.active', operator.id), { active }, { preserveScroll: true });
const resetMfa = (operator) => router.post(route('platform.operators.reset-mfa', operator.id), {}, { preserveScroll: true });

const selectClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <Head title="Operadores" />

    <PlatformLayout title="Operadores de la plataforma">
        <section aria-labelledby="lista" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="lista" class="text-lg font-medium text-gray-900">Operadores</h2>
            <InputError class="mt-2" :message="errors.operator" />
            <ul class="mt-3 divide-y divide-gray-100">
                <li v-for="operator in operators" :key="operator.id" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm">
                        <p class="font-medium text-gray-900">{{ operator.name }}</p>
                        <p class="text-gray-700">{{ operator.email }} · {{ roleNames[operator.role] }} · {{ statusNames[operator.status] }}<span v-if="!operator.hasTotp && operator.status !== 'pending'"> · sin segundo factor</span></p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <label class="sr-only" :for="`role-${operator.id}`">Nivel de {{ operator.name }}</label>
                        <select :id="`role-${operator.id}`" :value="operator.role" class="rounded-md border-gray-300 text-sm" @change="changeRole(operator, $event.target.value)">
                            <option value="support">Soporte</option>
                            <option value="super_admin">Superadministrador</option>
                        </select>
                        <button v-if="operator.status !== 'inactive'" type="button" class="rounded-md px-2 py-1 font-medium text-red-700 hover:bg-red-50" @click="setActive(operator, false)">Desactivar</button>
                        <button v-else type="button" class="rounded-md px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-50" @click="setActive(operator, true)">Reactivar</button>
                        <button v-if="operator.hasTotp" type="button" class="rounded-md px-2 py-1 font-medium text-amber-800 hover:bg-amber-50" @click="resetMfa(operator)">Restablecer segundo factor</button>
                    </div>
                </li>
            </ul>
        </section>

        <section aria-labelledby="invitar" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="invitar" class="text-lg font-medium text-gray-900">Invitar a un operador</h2>
            <form class="mt-3 grid gap-3 sm:grid-cols-3 sm:items-end" @submit.prevent="submitInvite">
                <div>
                    <InputLabel for="name" value="Nombre" />
                    <TextInput id="name" v-model="invite.name" class="mt-1 block w-full" maxlength="120" required />
                    <InputError :message="invite.errors.name" />
                </div>
                <div>
                    <InputLabel for="email" value="Correo" />
                    <TextInput id="email" v-model="invite.email" type="email" class="mt-1 block w-full" required />
                    <InputError :message="invite.errors.email" />
                </div>
                <div>
                    <InputLabel for="role" value="Nivel" />
                    <select id="role" v-model="invite.role" :class="selectClass">
                        <option value="support">Soporte</option>
                        <option value="super_admin">Superadministrador</option>
                    </select>
                </div>
                <PrimaryButton class="sm:col-span-3 sm:justify-self-start" :disabled="invite.processing">Enviar invitación</PrimaryButton>
            </form>
        </section>
    </PlatformLayout>
</template>
