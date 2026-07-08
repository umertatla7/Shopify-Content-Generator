<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: {
        type: Number,
        default: 1,
    },
    lastPage: {
        type: Number,
        default: 1,
    },
    from: {
        type: Number,
        default: 0,
    },
    to: {
        type: Number,
        default: 0,
    },
    total: {
        type: Number,
        default: 0,
    },
    perPage: {
        type: Number,
        default: 15,
    },
    perPageOptions: {
        type: Array,
        default: () => [10, 15, 25, 50],
    },
});

const emit = defineEmits(['page', 'per-page']);

const pages = computed(() => {
    const total = props.lastPage;
    const current = props.currentPage;

    if (total <= 1) {
        return [1];
    }

    const windowStart = Math.max(1, current - 2);
    const windowEnd = Math.min(total, current + 2);
    const items = [];

    if (windowStart > 1) {
        items.push(1);
    }

    if (windowStart > 2) {
        items.push('left-gap');
    }

    for (let page = windowStart; page <= windowEnd; page += 1) {
        items.push(page);
    }

    if (windowEnd < total - 1) {
        items.push('right-gap');
    }

    if (windowEnd < total) {
        items.push(total);
    }

    return items;
});

const changePage = (page) => {
    if (page < 1 || page > props.lastPage || page === props.currentPage) {
        return;
    }

    emit('page', page);
};
</script>

<template>
    <div class="flex flex-col gap-3 border-t border-zinc-200 p-4 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500">
            <div>
                Showing <span class="font-semibold text-zinc-800">{{ from }}</span> to
                <span class="font-semibold text-zinc-800">{{ to }}</span> of
                <span class="font-semibold text-zinc-800">{{ total }}</span>
            </div>
            <div class="flex items-center gap-2">
                <label class="!text-[11px]">Rows</label>
                <select class="!w-24" :value="perPage" @change="emit('per-page', Number($event.target.value))">
                    <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button class="btn btn-secondary" type="button" :disabled="currentPage <= 1" @click="changePage(currentPage - 1)">
                Prev
            </button>

            <template v-for="page in pages" :key="page">
                <span v-if="typeof page !== 'number'" class="px-1 text-sm text-zinc-400">...</span>
                <button
                    v-else
                    class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm font-semibold transition"
                    :class="page === currentPage ? 'border-teal-700 bg-teal-700 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-100'"
                    type="button"
                    @click="changePage(page)"
                >
                    {{ page }}
                </button>
            </template>

            <button class="btn btn-secondary" type="button" :disabled="currentPage >= lastPage" @click="changePage(currentPage + 1)">
                Next
            </button>
        </div>
    </div>
</template>
