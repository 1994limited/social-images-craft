<script>
export default {
    inheritAttrs: false,

    props: {
        modelValue: { type: String, default: '' },
        rows: { type: Number, default: 4 },
    },

    emits: ['update:modelValue'],
};
</script>

<template>
    <textarea v-bind="$attrs" :rows="rows" :value="modelValue" class="text fullwidth" @input="$emit('update:modelValue', $event.target.value)" />
</template>
