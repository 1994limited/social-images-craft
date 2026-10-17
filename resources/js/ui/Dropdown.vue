<!--
    A menu opened from a trigger: the #trigger slot, then the menu. Closes
    on a click outside, Escape, or picking an item.
-->
<script>
export default {
    props: {
        align: { type: String, default: 'end' },
    },

    provide() {
        return { closeDropdown: () => this.close() };
    },

    data() {
        return { open: false };
    },

    beforeUnmount() {
        this.unlisten();
    },

    methods: {
        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            this.open = true;
            setTimeout(() => {
                document.addEventListener('click', this.outside);
                document.addEventListener('keydown', this.escape);
            });
        },

        close() {
            this.open = false;
            this.unlisten();
        },

        unlisten() {
            document.removeEventListener('click', this.outside);
            document.removeEventListener('keydown', this.escape);
        },

        outside(event) {
            if (!this.$el.contains(event.target)) {
                this.close();
            }
        },

        escape(event) {
            if (event.key === 'Escape') {
                this.close();
                this.$el.querySelector('button, a')?.focus();
            }
        },
    },
};
</script>

<template>
    <div class="si-dropdown">
        <div class="si-dropdown__trigger" :aria-expanded="open" @click="toggle">
            <slot name="trigger" />
        </div>
        <div v-if="open" class="si-dropdown__panel" :class="`si-dropdown__panel--${align}`">
            <slot />
        </div>
    </div>
</template>
