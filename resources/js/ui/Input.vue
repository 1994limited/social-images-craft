<script>
export default {
    inheritAttrs: false,

    props: {
        modelValue: { type: [String, Number], default: '' },
        type: { type: String, default: 'text' },
        icon: { type: String, default: null },
        clearable: { type: Boolean, default: false },
    },

    emits: ['update:modelValue'],
};
</script>

<template>
    <div class="si-input" :class="$attrs.class">
        <input
            v-bind="{ ...$attrs, class: undefined }"
            :type="type === 'search' ? 'search' : type"
            :value="modelValue"
            class="text fullwidth"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <button v-if="clearable && modelValue" type="button" class="si-input__clear" :aria-label="__('Clear')" @click="$emit('update:modelValue', '')">×</button>
    </div>
</template>
