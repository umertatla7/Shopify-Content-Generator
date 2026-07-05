<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    src: {
        type: String,
        default: '',
    },
    alt: {
        type: String,
        default: 'placeholder.png',
    },
    fallbackLabel: {
        type: String,
        default: '',
    },
    imageClass: {
        type: String,
        default: 'h-10 w-10 rounded-xl object-contain',
    },
    wrapperClass: {
        type: String,
        default: 'grid place-items-center rounded-xl border border-zinc-200 bg-zinc-50',
    },
});

const placeholderSrc = '/images/placeholders/icon-placeholder.svg';
const currentSrc = ref(props.src || placeholderSrc);

watch(() => props.src, (value) => {
    currentSrc.value = value || placeholderSrc;
});

const initials = computed(() => {
    if (props.fallbackLabel) return props.fallbackLabel;

    return props.alt
        .replace(/\.[a-z0-9]+$/i, '')
        .split(/[\s-_]+/)
        .filter(Boolean)
        .map((chunk) => chunk[0]?.toUpperCase())
        .join('')
        .slice(0, 2) || 'AI';
});

const handleError = () => {
    if (currentSrc.value !== placeholderSrc) {
        currentSrc.value = placeholderSrc;
    }
};
</script>

<template>
    <div :class="wrapperClass">
        <img :src="currentSrc" :alt="alt" :class="imageClass" loading="lazy" @error="handleError" />
        <span class="sr-only">{{ initials }}</span>
    </div>
</template>
