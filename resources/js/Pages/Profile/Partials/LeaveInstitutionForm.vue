<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const confirming = ref(false);
const institution = usePage().props.institution;

const form = useForm({});

const leave = () => {
    form.post(route('profile.leave'), {
        preserveScroll: true,
        onError: () => (confirming.value = false),
    });
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900">Salir de este plantel</h2>
            <p class="mt-1 text-sm text-gray-600">
                Dejarás de tener acceso a {{ institution?.name }}. Tu cuenta y tus
                demás planteles no cambian, y los registros que dejaste aquí se
                conservan. Para eliminar tu cuenta, ve a «Gestionar mi cuenta».
            </p>
        </header>

        <InputError :message="form.errors.general || form.errors.roles" />

        <DangerButton @click="confirming = true">Salir de este plantel</DangerButton>

        <Modal :show="confirming" @close="confirming = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    ¿Deseas dejar de pertenecer a este plantel?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Para volver, el plantel tendrá que invitarte de nuevo.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="confirming = false">Cancelar</SecondaryButton>
                    <DangerButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="leave">
                        Salir del plantel
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
