<template>
    <transition name="fade" appear>
        <div
            v-if="isVisible"
            :class="[
                'alert',
                'd-flex',
                'align-items-center',
                'justify-content-between',
                alertTypeClass,
            ]"
            role="alert"
        >
            <div class="flex-grow-1">
                <slot>
                    {{ message }}
                </slot>
            </div>
            <button
                v-if="dismissible"
                type="button"
                class="btn-close ms-3"
                aria-label="Close"
                @click="close"
            ></button>
        </div>
    </transition>
</template>
<script>
export default {
    props: {
        visible: {
            type: Boolean,
            default: true,
        },
        level: {
            type: String,
            default: "info",
            validator: (value) => {
                return ["info", "success", "warning", "error"].includes(value);
            },
        },
        dismissible: {
            type: Boolean,
            default: true,
        },
        message: {
            type: String,
        },
    },
    emits: ["update:visible"],
    data() {
        return {
            isVisible: this.visible,
        };
    },
    watch: {
        visible(newValue) {
            this.isVisible = newValue;
        },
    },
    methods: {
        close() {
            this.isVisible = false;
            this.$emit("update:visible", false);
        },
    },
    computed: {
        alertTypeClass() {
            return `alert-${this.level}`;
        },
    },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
