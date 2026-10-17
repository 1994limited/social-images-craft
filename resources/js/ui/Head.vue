<!--
    Sets the window's title, as Craft's own pages do: "Page - Site".
-->
<script>
export default {
    props: {
        title: { type: [String, Array], default: '' },
    },

    watch: {
        title: {
            immediate: true,
            handler(title) {
                const parts = Array.isArray(title) ? title : [title];
                const site = document.title.split(' - ').pop();

                document.title = [...parts.filter(Boolean), site].join(' - ');
            },
        },
    },

    render() {
        return null;
    },
};
</script>
