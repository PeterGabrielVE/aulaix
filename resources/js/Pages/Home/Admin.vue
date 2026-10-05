<script setup>
import HomeScreen from '@/Components/HomeScreen.vue';

const links = [
    {
        route: 'users.index',
        label: 'Usuarios',
        description: 'Crea cuentas, asigna roles y activa o desactiva usuarios.',
    },
];
</script>

<template>
    <HomeScreen
        title="Panel de administración"
        description="Gestiona la institución y sus usuarios."
        :links="links"
    />
</template>
