<template>
    <div class="position-relative">
        <Button
            :variant="buttonVariant"
            @click="toggle"
            :class="({ active: isOpen }, 'p-0')"
        >
            <slot name="trigger">
                <Icon name="dots-three-vertical" size="small" />
            </slot>
        </Button>

        <div
            v-show="isOpen"
            class="dropdown-menu dropdown-menu-end show"
            style="position: absolute; top: 100%; right: 0; z-index: 1000"
        >
            <slot></slot>
        </div>
    </div>
</template>

<script>
import Button from "@components/interactables/Button.vue";
import Icon from "@components/structure/Icon.vue";

// Dropdown Item component
const DropdownItem = {
    template: `
        <a
            class="dropdown-item"
            :class="{ 'text-danger': danger }"
            href="#"
            @click.prevent="handleClick"
        >
            <Icon v-if="icon" :name="icon" class="me-2" />
            <slot></slot>
        </a>
    `,
    components: {
        Icon,
    },
    inject: ["dropdown"],
    emits: ["click"],
    props: {
        icon: {
            type: String,
        },
        danger: {
            type: Boolean,
            default: false,
        },
    },
    methods: {
        handleClick() {
            this.$emit("click");
            this.dropdown.onItemClick();
        },
    },
};

export default {
    name: "Dropdown",
    components: {
        Button,
        Icon,
        DropdownItem,
    },
    props: {
        buttonVariant: {
            type: String,
            default: "secondary",
        },
    },
    data() {
        return {
            isOpen: false,
        };
    },
    methods: {
        toggle() {
            this.isOpen = !this.isOpen;
        },
        close() {
            this.isOpen = false;
        },
        handleClickOutside(event) {
            if (!this.$el.contains(event.target)) {
                this.close();
            }
        },
        onItemClick() {
            this.close();
        },
    },
    provide() {
        return {
            dropdown: {
                onItemClick: this.onItemClick,
            },
        };
    },
    mounted() {
        document.addEventListener("click", this.handleClickOutside);
    },
    beforeUnmount() {
        document.removeEventListener("click", this.handleClickOutside);
    },
};

// Export the DropdownItem for use as Dropdown.Item
export { DropdownItem };
</script>

<style scoped>
.dropdown-menu {
    border: 1px solid #dee2e6;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border-radius: 0.375rem;
    min-width: 120px;
}

:deep(.dropdown-item) {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    text-decoration: none;
}

:deep(.dropdown-item:hover) {
    background-color: #f8f9fa;
    text-decoration: none;
}
</style>
