<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

// SPEC-014 PLT-NF04: a fresh code before a sensitive action.
const form = useForm({ code: '' });
const submit = () => form.post(route('platform.totp.confirm'), { onFinish: () => form.reset('code') });
</script>

<template>
    <Head title="Confirmar con un código" />

    <PlatformLayout title="Confirma con un código">
        <form class="flex max-w-sm flex-col gap-4 rounded-md bg-white p-4 shadow-xs" @submit.prevent="submit">
            <p class="text-sm text-gray-700">Esta acción necesita un código de tu app de autenticación.</p>
            <div>
                <InputLabel for="code" value="Código de 6 dígitos" />
                <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
                <InputError :message="form.errors.code" />
            </div>
            <PrimaryButton class="self-start" :disabled="form.processing">Confirmar</PrimaryButton>
        </form>
    </PlatformLayout>
</template>
