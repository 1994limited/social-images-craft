<!--
    A control panel button, in Craft's own classes. Takes the props the
    shared pages use: text, icon, icon-append, variant, size, href, loading.
-->
<script>
import Icon from '@social-images/designer/components/designer/Icon.vue';

export default {
    components: { Icon },

    inheritAttrs: false,

    props: {
        text: { type: String, default: null },
        icon: { type: String, default: null },
        iconAppend: { type: String, default: null },
        variant: { type: String, default: 'default' },
        size: { type: String, default: null },
        href: { type: String, default: null },
        loading: { type: Boolean, default: false },
        disabled: { type: Boolean, default: false },
    },

    emits: ['click'],

    computed: {
        classes() {
            return [
                'btn',
                'si-btn',
                {
                    submit: this.variant === 'primary',
                    'si-btn--ghost': this.variant === 'ghost',
                    'si-btn--danger': this.variant === 'danger' || this.variant === 'destructive',
                    small: this.size === 'sm' || this.size === 'xs',
                    loading: this.loading,
                    disabled: this.disabled,
                    'si-btn--icon': !this.text && !this.$slots.default,
                },
            ];
        },
    },

    methods: {
        click(event) {
            if (this.disabled || this.loading) {
                event.preventDefault();

                return;
            }

            this.$emit('click', event);
        },
    },
};
</script>

<template>
    <a v-if="href" v-bind="$attrs" :href="href" :class="classes" :aria-disabled="disabled || undefined" @click="click">
        <Icon v-if="icon" :name="icon" />
        <span v-if="text || $slots.default" class="si-btn__label"><slot>{{ text }}</slot></span>
        <Icon v-if="iconAppend" :name="iconAppend" />
    </a>
    <button v-else v-bind="$attrs" type="button" :class="classes" :disabled="disabled || loading" @click="click">
        <Icon v-if="icon" :name="icon" />
        <span v-if="text || $slots.default" class="si-btn__label"><slot>{{ text }}</slot></span>
        <Icon v-if="iconAppend" :name="iconAppend" />
        <span v-if="loading" class="spinner spinner-absolute" />
    </button>
</template>
