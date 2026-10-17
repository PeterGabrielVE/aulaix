<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({
    institutions: {
        type: Array,
        required: true,
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    code: '',
    password: '',
    password_confirmation: '',
});

const sendCode = () => {
    router.post(route('central.consolidation.code'), {}, { preserveScroll: true });
};

const submit = () => {
    form.post(route('central.consolidation.complete'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Unificar tus cuentas" />

        <h1 class="mb-1 text-lg font-semibold text-gray-900">Unifica tus cuentas</h1>
        <p class="text-sm text-gray-600">
            Tu correo tiene una cuenta en cada uno de estos planteles:
        </p>
        <ul class="mt-2 list-inside list-disc text-sm text-gray-800">
            <li v-for="institution in institutions" :key="institution">{{ institution }}</li>
        </ul>
        <p class="mt-2 text-sm text-gray-600">
            Al unificarlas tendrás una sola cuenta, con una única contraseña
            para todos tus planteles.
        </p>

        <div v-if="status" class="mt-4 text-sm font-medium text-green-600">{{ status }}</div>
        <InputError class="mt-2" :message="form.errors.code" />

        <SecondaryButton class="mt-4" @click="sendCode">Enviarme un código</SecondaryButton>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="code" value="Código del correo" />
                <TextInput id="code" v-model="form.code" type="text" inputmode="numeric" maxlength="6" class="mt-1 block w-full" required autocomplete="one-time-code" />
            </div>

            <div>
                <InputLabel for="password" value="Nueva contraseña" />
                <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirmar contraseña" />
                <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            </div>

            <div class="flex items-center justify-end gap-4">
                <Link :href="route('central.home')" class="text-sm text-gray-600 underline hover:text-gray-900">Ahora no</Link>
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Unificar mis cuentas
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
