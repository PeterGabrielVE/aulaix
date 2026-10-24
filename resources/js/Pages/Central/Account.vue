<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    account: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        default: null,
    },
});

const nameForm = useForm({ name: props.account.name });
const emailForm = useForm({ email: '' });
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const deletionForm = useForm({ confirmation: false });
const confirmingDeletion = ref(false);

const saveName = () => nameForm.patch(route('central.account.name'), { preserveScroll: true });
const changeEmail = () => emailForm.post(route('central.account.email'), { preserveScroll: true, onSuccess: () => emailForm.reset() });
const changePassword = () =>
    passwordForm.put(route('central.account.password'), {
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
    });
const requestDeletion = () => deletionForm.post(route('central.account.delete'), { onError: () => (confirmingDeletion.value = false) });
const cancelDeletion = () => useForm({}).post(route('central.account.deletion.cancel'));
</script>

<template>
    <GuestLayout>
        <Head title="Mi cuenta" />

        <div class="flex items-start justify-between gap-4">
            <h1 class="text-lg font-semibold text-gray-900">Mi cuenta</h1>
            <Link :href="route('central.home')" class="text-sm text-gray-600 underline hover:text-gray-900">Mis planteles</Link>
        </div>

        <div v-if="status" class="mt-4 text-sm font-medium text-green-600">{{ status }}</div>

        <!-- During the grace period, only the date and the way back (IDG-R08.7). -->
        <section v-if="account.pendingDeletion" class="mt-4 space-y-4 text-sm text-gray-700">
            <p>
                Pediste eliminar tu cuenta. Se cerrará el <strong>{{ account.deletionDueAt }}</strong>.
                Hasta entonces no puedes entrar a ningún plantel.
            </p>
            <PrimaryButton @click="cancelDeletion">Cancelar la eliminación</PrimaryButton>
        </section>

        <template v-else>
            <form class="mt-6 space-y-2" @submit.prevent="saveName">
                <InputLabel for="name" value="Nombre" />
                <TextInput id="name" v-model="nameForm.name" type="text" class="block w-full" required autocomplete="name" />
                <InputError :message="nameForm.errors.name" />
                <PrimaryButton :disabled="nameForm.processing">Guardar nombre</PrimaryButton>
            </form>

            <form class="mt-8 space-y-2" @submit.prevent="changeEmail">
                <InputLabel for="email" value="Nuevo correo electrónico" />
                <p class="text-sm text-gray-600">
                    Tu correo actual es {{ account.email }}<span v-if="account.pendingEmail">;
                    esperamos que confirmes {{ account.pendingEmail }}</span>. Te enviaremos un enlace
                    al nuevo correo y el cambio se hará cuando lo uses.
                </p>
                <TextInput id="email" v-model="emailForm.email" type="email" class="block w-full" required autocomplete="email" />
                <InputError :message="emailForm.errors.email" />
                <PrimaryButton :disabled="emailForm.processing">Cambiar correo</PrimaryButton>
            </form>

            <form class="mt-8 space-y-2" @submit.prevent="changePassword">
                <InputLabel for="current_password" value="Contraseña actual" />
                <TextInput id="current_password" v-model="passwordForm.current_password" type="password" class="block w-full" autocomplete="current-password" />
                <InputError :message="passwordForm.errors.current_password" />
                <InputLabel for="password" value="Nueva contraseña" />
                <TextInput id="password" v-model="passwordForm.password" type="password" class="block w-full" autocomplete="new-password" />
                <InputError :message="passwordForm.errors.password" />
                <InputLabel for="password_confirmation" value="Confirmar contraseña" />
                <TextInput id="password_confirmation" v-model="passwordForm.password_confirmation" type="password" class="block w-full" autocomplete="new-password" />
                <PrimaryButton :disabled="passwordForm.processing">Cambiar contraseña</PrimaryButton>
            </form>

            <section class="mt-10 space-y-2">
                <h2 class="text-sm font-medium text-gray-900">Eliminar mi cuenta</h2>
                <p class="text-sm text-gray-600">
                    Afectará tu acceso a todos los planteles asociados. Tendrás 30 días para arrepentirte.
                    Para dejar solo un plantel, usa «Salir de este plantel» en tu perfil de ese plantel.
                </p>
                <InputError :message="deletionForm.errors.confirmation" />
                <DangerButton @click="confirmingDeletion = true">Eliminar mi cuenta</DangerButton>
            </section>

            <Modal :show="confirmingDeletion" @close="confirmingDeletion = false">
                <div class="space-y-4 p-6">
                    <h2 class="text-lg font-medium text-gray-900">¿Eliminar tu cuenta de AulaX?</h2>
                    <p class="text-sm text-gray-600">
                        Perderás el acceso a todos tus planteles. Tu cuenta se cerrará en 30 días;
                        hasta entonces puedes cancelarlo iniciando sesión.
                    </p>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="deletionForm.confirmation" type="checkbox" class="rounded-sm border-gray-300" />
                        Entiendo que afectará mi acceso a todos los planteles asociados.
                    </label>
                    <div class="flex justify-end gap-3">
                        <SecondaryButton @click="confirmingDeletion = false">Cancelar</SecondaryButton>
                        <DangerButton :disabled="!deletionForm.confirmation || deletionForm.processing" @click="requestDeletion">
                            Eliminar mi cuenta
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </template>
    </GuestLayout>
</template>
