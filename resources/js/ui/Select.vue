<script>
export default {
    inheritAttrs: false,

    props: {
        modelValue: { type: [String, Number, null], default: null },
        options: { type: Array, default: () => [] },
        optionLabel: { type: String, default: 'label' },
        optionValue: { type: String, default: 'value' },
    },

    emits: ['update:modelValue'],
};
</script>

<template>
    <div class="select" :class="$attrs.class">
        <select v-bind="{ ...$attrs, class: undefined }" :value="modelValue ?? ''" @change="$emit('update:modelValue', $event.target.value || null)">
            <option v-for="option in options" :key="option[optionValue] ?? ''" :value="option[optionValue] ?? ''">{{ option[optionLabel] }}</option>
        </select>
    </div>
</template>
