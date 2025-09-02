<template>
    <div class="btn-group d-flex" role="group" :aria-label="ariaLabel">
        <button
            v-for="button in buttons"
            :key="button.id"
            :type="button.type || 'button'"
            :class="getButtonClasses(button)"
            :title="button.title"
            :disabled="button.disabled"
            @click="handleButtonClick(button)"
        >
            <Icon v-if="button.icon" :name="button.icon" class="me-1" />{{
                button.label
            }}
        </button>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";

export default {
    name: "ButtonGroup",
    components: {
        Icon,
    },
    props: {
        /**
         * Array of button configurations
         * Each button should have: { id, label, icon?, action?, variant?, size?, disabled?, title?, type? }
         */
        buttons: {
            type: Array,
            required: true,
            validator(buttons) {
                return buttons.every(
                    (button) =>
                        button &&
                        typeof button === "object" &&
                        typeof button.id === "string" &&
                        typeof button.label === "string"
                );
            },
        },
        /**
         * Accessible label for the button group
         */
        ariaLabel: {
            type: String,
            default: "Button group",
        },
        /**
         * Default button variant (Bootstrap classes)
         */
        defaultVariant: {
            type: String,
            default: "outline-secondary",
        },
        /**
         * Default button size
         */
        defaultSize: {
            type: String,
            default: "sm",
        },
    },
    emits: ["button-click"],
    methods: {
        /**
         * Generate button CSS classes based on configuration
         */
        getButtonClasses(button) {
            const variant = button.variant || this.defaultVariant;
            const size = button.size || this.defaultSize;

            return [
                "btn",
                `btn-${variant}`,
                size ? `btn-${size}` : "",
                button.class || "",
            ]
                .filter(Boolean)
                .join(" ");
        },
        /**
         * Handle button click events
         */
        handleButtonClick(button) {
            if (button.disabled) return;

            // Emit event with button data
            this.$emit("button-click", button);

            // Call button's action if provided
            if (typeof button.action === "function") {
                button.action(button);
            }
        },
    },
};
</script>
