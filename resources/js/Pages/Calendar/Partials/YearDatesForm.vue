<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

// The dates of a year and its three terms. `form` is the parent's useForm();
// the dates of closed terms can not change (CAL-R03.3).
defineProps({
    form: {
        type: Object,
        required: true,
    },
    lockedTerms: {
        type: Array,
        default: () => [],
    },
    submitLabel: {
        type: String,
        required: true,
    },
});

defineEmits(['submit']);
</script>

<template>
    <form class="space-y-4" @submit.prevent="$emit('submit')">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <InputLabel value="Inicio del año" />
                <TextInput v-model="form.starts_on" type="date" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.starts_on" />
            </div>
            <div>
                <InputLabel value="Fin del año" />
                <TextInput v-model="form.ends_on" type="date" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.ends_on" />
            </div>
        </div>

        <fieldset v-for="(term, index) in form.terms" :key="index" class="rounded-md border border-gray-200 p-3">
            <legend class="px-1 text-sm font-medium text-gray-700">
                Lapso {{ index + 1 }}<span v-if="lockedTerms.includes(index + 1)"> (cerrado)</span>
            </legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <InputLabel value="Inicio" />
                    <TextInput v-model="term.starts_on" type="date" class="mt-1 block w-full" :disabled="lockedTerms.includes(index + 1)" required />
                    <InputError class="mt-1" :message="form.errors[`terms.${index}.starts_on`]" />
                </div>
                <div>
                    <InputLabel value="Fin" />
                    <TextInput v-model="term.ends_on" type="date" class="mt-1 block w-full" :disabled="lockedTerms.includes(index + 1)" required />
                    <InputError class="mt-1" :message="form.errors[`terms.${index}.ends_on`]" />
                </div>
            </div>
        </fieldset>

        <InputError :message="form.errors.status" />

        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            {{ submitLabel }}
        </PrimaryButton>
    </form>
</template>
