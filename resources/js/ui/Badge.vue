<script>
export default {
    props: {
        text: { type: String, default: '' },
        color: { type: String, default: 'gray' },
        size: { type: String, default: null },
    },
};
</script>

<template>
    <span class="si-badge" :class="`si-badge--${color}`">{{ text }}</span>
</template>
