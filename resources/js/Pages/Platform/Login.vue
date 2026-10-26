<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

// SPEC-014 PLT-R01.2: email, password and code in one step, so a failure
// never tells which of them was wrong (PLT-R01.3).
const form = useForm({ email: '', password: '', code: '' });

const submit = () => form.post(route('platform.login'), {
    onFinish: () => form.reset('password', 'code'),
});
</script>

<template>
    <Head title="Panel de plataforma" />

    <PlatformLayout title="Entrar al panel">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Correo" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" autocomplete="username" required autofocus />
            </div>
            <div>
                <InputLabel for="password" value="Contraseña" />
                <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" autocomplete="current-password" required />
            </div>
            <div>
                <InputLabel for="code" value="Código de la app de autenticación, o un código de recuperación" />
                <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" autocomplete="one-time-code" inputmode="text" maxlength="32" required />
            </div>
            <InputError :message="form.errors.email" />
            <PrimaryButton class="self-start" :disabled="form.processing">Entrar</PrimaryButton>
        </form>
    </PlatformLayout>
</template>
