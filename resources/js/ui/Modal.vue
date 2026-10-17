<!--
    A dialog over the page, opened with v-model:open.
-->
<script>
import Icon from '@social-images/designer/components/designer/Icon.vue';

export default {
    components: { Icon },

    props: {
        open: { type: Boolean, default: false },
        title: { type: String, default: '' },
    },

    emits: ['update:open'],

    watch: {
        open: {
            immediate: true,
            handler(open) {
                if (open) {
                    document.addEventListener('keydown', this.escape);
                    this.$nextTick(() => this.$refs.dialog?.querySelector('input, textarea, select, button:not(.si-modal__close)')?.focus());
                } else {
                    document.removeEventListener('keydown', this.escape);
                }
            },
        },
    },

    beforeUnmount() {
        document.removeEventListener('keydown', this.escape);
    },

    methods: {
        close() {
            this.$emit('update:open', false);
        },

        escape(event) {
            if (event.key === 'Escape') {
                this.close();
            }
        },
    },
};
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="si-app si-modal" @mousedown.self="close">
            <div ref="dialog" class="si-modal__dialog" role="dialog" aria-modal="true" :aria-label="title">
                <header class="si-modal__header">
                    <h2>{{ title }}</h2>
                    <button type="button" class="si-modal__close" :aria-label="__('Close')" @click="close"><Icon name="close" /></button>
                </header>
                <div class="si-modal__body"><slot /></div>
            </div>
        </div>
    </Teleport>
</template>
