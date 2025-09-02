<template>
    <button type="button" :class="buttonClass" :disabled="disabled">
        <slot></slot>
    </button>
</template>
<script>
export default {
    props: {
        disabled: {
            type: Boolean,
            default: false,
        },
        variant: {
            type: String,
            default: "primary",
            validator: function (value) {
                return [
                    "primary",
                    "secondary",
                    "success",
                    "danger",
                    "warning",
                    "info",
                    "light",
                    "dark",
                ].includes(value);
            },
        },
    },
    computed: {
        buttonClass() {
            return `btn btn-${this.variant}`;
        },
    },
};
</script>
