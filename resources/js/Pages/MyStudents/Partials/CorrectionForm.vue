<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    // Null when the correction is about the guardian's own data.
    studentId: { type: Number, default: null },
});

const form = useForm({ student_id: props.studentId, text: '' });
const send = () => form.post(route('corrections.store'), { preserveScroll: true, onSuccess: () => form.reset('text') });
</script>

<template>
    <form class="space-y-2" @submit.prevent="send">
        <InputLabel :for="`correction_${studentId ?? 'own'}`" value="¿Qué dato hay que corregir?" />
        <textarea
            :id="`correction_${studentId ?? 'own'}`"
            v-model="form.text"
            rows="3"
            maxlength="1000"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            required
        />
        <InputError :message="form.errors.text" />
        <PrimaryButton :disabled="form.processing">Enviar al plantel</PrimaryButton>
    </form>
</template>
