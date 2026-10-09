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
        title="Inicio de la dirección"
        description="Supervisa el plantel: personal, estudiantes y rendimiento académico."
        :links="links"
    />
</template>
