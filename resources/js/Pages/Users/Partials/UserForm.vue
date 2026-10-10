<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// Fields shared by Users/Create and Users/Edit. `form` is the parent's
// useForm() instance; status is only editable once the user exists.
const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
    showStatus: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        required: true,
    },
});

defineEmits(['submit']);

// Errors on the list itself ("roles") or on one entry ("roles.1").
const rolesError = computed(
    () =>
        props.form.errors.roles ??
        Object.entries(props.form.errors).find(([key]) =>
            key.startsWith('roles.'),
        )?.[1],
);

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="name" value="Nombre" />
            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                required
                autofocus
                autocomplete="off"
            />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <div>
            <InputLabel for="email" value="Correo electrónico" />
            <TextInput
                id="email"
                v-model="form.email"
                type="email"
                class="mt-1 block w-full"
                required
                autocomplete="off"
            />
            <InputError class="mt-2" :message="form.errors.email" />
        </div>

        <fieldset>
            <legend class="block text-sm font-medium text-gray-700">Roles</legend>
            <p class="mt-1 text-sm text-gray-500">
                Puedes marcar varios, p. ej. Docente y Representante.
            </p>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <label
                    v-for="role in roles"
                    :key="role"
                    class="flex items-center gap-2 text-sm text-gray-700"
                >
                    <input
                        v-model="form.roles"
                        type="checkbox"
                        :value="role"
                        class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                    />
                    {{ role }}
                </label>
            </div>
            <InputError class="mt-2" :message="rolesError" />
        </fieldset>

        <div v-if="showStatus">
            <InputLabel for="status" value="Estado" />
            <select id="status" v-model="form.status" :class="selectClass">
                <option value="active">Activo</option>
                <option value="inactive">Inactivo (no puede iniciar sesión)</option>
            </select>
            <InputError class="mt-2" :message="form.errors.status" />
        </div>

        <div class="flex items-center justify-end gap-4">
            <Link
                :href="route('users.index')"
                class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                Cancelar
            </Link>
            <PrimaryButton
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                {{ submitLabel }}
            </PrimaryButton>
        </div>
    </form>
</template>
