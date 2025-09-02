<template>
    <i :class="iconClass" :style="iconStyle" aria-hidden="true"></i>
</template>

<script>
export default {
    name: "Icon",
    props: {
        name: {
            type: String,
            required: true,
        },
        size: {
            type: String,
            default: "1x",
        },
        weight: {
            type: String,
            default: "regular", // regular (default), thin, light, bold, fill, duotone
            validator: (value) =>
                [
                    "regular",
                    "thin",
                    "light",
                    "bold",
                    "fill",
                    "duotone",
                ].includes(value),
        },
        color: {
            type: String,
            default: "inherit",
        },
        fixedWidth: {
            type: Boolean,
            default: false,
        },
    },
    computed: {
        iconClass() {
            const classes = ["ph", "h-100"];

            classes[0] = `ph-${this.weight}`;

            classes.push(`ph-${this.name}`);

            classes.push(`ph-${this.size}`);

            if (this.fixedWidth) {
                classes.push("ph-fw");
            }

            return classes;
        },
        iconStyle() {
            return {
                color: this.color,
            };
        },
    },
};
</script>
<style>
i {
    transform: translateY(15%);
}
</style>
