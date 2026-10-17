<!--
    The page's actions. Craft shows the page's title in its own header, so
    only the buttons are drawn here.
-->
<script>
export default {
    props: {
        title: { type: String, default: '' },
        icon: { type: String, default: null },
    },
};
</script>

<template>
    <div class="si-header">
        <slot />
    </div>
</template>
