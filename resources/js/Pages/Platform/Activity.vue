<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

// SPEC-014 PLT-R07.2: the platform log, filtered by date, operator and
// event type. Never an institution's log (PLT-R07.4).
const props = defineProps({
    log: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const form = reactive({ ...props.filters });
const originNames = { platform: 'Panel', console: 'Consola', web: 'Web', system: 'Sistema' };
const lastPage = computed(() => Math.max(1, Math.ceil(props.log.total / props.log.perPage)));

const query = (page = 1) => Object.fromEntries(Object.entries({ ...form, page }).filter(([, value]) => value !== '' && value !== null));
const search = () => router.get(route('platform.activity'), query(), { preserveState: true, replace: true });
const goTo = (page) => router.get(route('platform.activity'), query(page), { preserveState: true });

const formatDate = (iso) => new Date(iso).toLocaleString('es-VE');
const formatValue = (value) => (value === null ? '—' : Array.isArray(value) ? value.join(', ') : String(value));
</script>

<template>
    <Head title="Bitácora de plataforma" />

    <PlatformLayout title="Bitácora de plataforma">
        <section aria-labelledby="filtros" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="filtros" class="sr-only">Filtros</h2>
            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(4,1fr)_auto] lg:items-end" role="search" @submit.prevent="search">
                <div>
                    <InputLabel for="from" value="Desde" />
                    <TextInput id="from" v-model="form.from" type="date" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="to" value="Hasta" />
                    <TextInput id="to" v-model="form.to" type="date" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="operator" value="Operador" />
                    <TextInput id="operator" v-model="form.operator" class="mt-1 block w-full" maxlength="255" placeholder="operador #3 o correo" />
                </div>
                <div>
                    <InputLabel for="type" value="Tipo de evento" />
                    <TextInput id="type" v-model="form.type" class="mt-1 block w-full" maxlength="100" placeholder="platform.institution" />
                </div>
                <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-600">Filtrar</button>
            </form>
        </section>

        <section aria-labelledby="entradas" class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6">
            <h2 id="entradas" class="text-lg font-medium text-gray-900">{{ log.total }} {{ log.total === 1 ? 'entrada' : 'entradas' }}</h2>
            <p v-if="log.entries.length === 0" class="mt-3 text-sm text-gray-700">Ninguna entrada coincide con los filtros.</p>
            <ul v-else class="mt-3 divide-y divide-gray-100 text-sm">
                <li v-for="entry in log.entries" :key="entry.id" class="py-3">
                    <p class="font-medium break-all text-gray-900">{{ entry.eventType }}</p>
                    <p class="text-gray-700">
                        {{ formatDate(entry.occurredAt) }} · {{ originNames[entry.origin] ?? entry.origin }}
                        <span v-if="entry.operator"> · {{ entry.operator }}</span>
                        <span v-if="entry.actorName"> · {{ entry.actorName }}</span>
                        <span v-if="entry.ip"> · {{ entry.ip }}</span>
                        · {{ entry.subjectType }} {{ entry.subjectId }}
                    </p>
                    <dl v-if="entry.changes.length" class="mt-1 grid gap-x-3 text-gray-700 sm:grid-cols-[auto_1fr]">
                        <template v-for="change in entry.changes" :key="change.field">
                            <dt class="font-medium">{{ change.field }}</dt>
                            <dd class="break-words">{{ formatValue(change.from) }} → {{ formatValue(change.to) }}</dd>
                        </template>
                    </dl>
                </li>
            </ul>
            <nav v-if="lastPage > 1" aria-label="Páginas" class="mt-4 flex items-center gap-3 text-sm">
                <button type="button" class="rounded-md px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-50 disabled:text-gray-400" :disabled="log.page <= 1" @click="goTo(log.page - 1)">Anterior</button>
                <span class="text-gray-700">Página {{ log.page }} de {{ lastPage }}</span>
                <button type="button" class="rounded-md px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-50 disabled:text-gray-400" :disabled="log.page >= lastPage" @click="goTo(log.page + 1)">Siguiente</button>
            </nav>
        </section>
    </PlatformLayout>
</template>
