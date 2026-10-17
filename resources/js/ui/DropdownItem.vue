<script>
import Icon from '@social-images/designer/components/designer/Icon.vue';

export default {
    components: { Icon },

    inject: { closeDropdown: { default: () => () => {} } },

    props: {
        text: { type: String, required: true },
        icon: { type: String, default: null },
        href: { type: String, default: null },
        variant: { type: String, default: null },
    },

    emits: ['click'],

    methods: {
        click(event) {
            this.$emit('click', event);
            this.closeDropdown();
        },
    },
};
</script>

<template>
    <li role="none">
        <component :is="href ? 'a' : 'button'" :href="href" :type="href ? null : 'button'" role="menuitem" class="si-menu__item" :class="{ 'si-menu__item--danger': variant === 'destructive' }" @click="click">
            <Icon v-if="icon" :name="icon" />
            <span>{{ text }}</span>
        </component>
    </li>
</template>
