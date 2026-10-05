<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';

// Fields shared by Users/Create and Users/Edit. `form` is the parent's
// useForm() instance; status is only editable once the user exists.
defineProps({
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

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
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

        <div>
            <InputLabel for="role" value="Rol" />
            <select id="role" v-model="form.role" :class="selectClass" required>
                <option value="" disabled>Selecciona un rol</option>
                <option v-for="role in roles" :key="role" :value="role">
                    {{ role }}
                </option>
            </select>
            <InputError class="mt-2" :message="form.errors.role" />
        </div>

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
                class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
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
