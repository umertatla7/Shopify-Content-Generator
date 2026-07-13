<script setup>
import { CircleHelp } from 'lucide-vue-next';

defineProps({
    text: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <span class="inline-flex align-middle text-zinc-400" :title="text">
        <CircleHelp class="size-4" />
    </span>
</template>
