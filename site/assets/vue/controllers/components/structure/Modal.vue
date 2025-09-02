<template>
    <!-- Modal backdrop -->
    <div v-if="visible" class="modal-backdrop fade show"></div>

    <div
        :class="['modal', 'fade', { show: visible, 'd-block': visible }]"
        id="modal"
        tabindex="-1"
        aria-labelledby="modalLabel"
        :aria-hidden="!visible"
        @keydown.esc="closeModal"
        @click.self="closeModal"
        :style="{ display: visible ? 'block' : 'none' }"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div v-if="title || $slots.header" class="modal-header">
                    <slot name="header">
                        <h5 class="modal-title" id="modalLabel">{{ title }}</h5>
                        <button
                            type="button"
                            class="btn-close"
                            aria-label="Close"
                            @click="closeModal"
                        ></button>
                    </slot>
                </div>
                <div class="modal-body">
                    <slot></slot>
                </div>

                <div class="modal-footer" v-if="showFooter">
                    <slot name="footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            v-if="cancelable"
                            @click="closeModal"
                        >
                            {{ closeText }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="saveModal"
                        >
                            {{ saveText }}
                        </button>
                    </slot>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
export default {
    props: {
        title: {
            type: String,
        },
        cancelable: {
            type: Boolean,
            default: true,
        },
        visible: {
            type: Boolean,
            default: false,
        },
        saveText: {
            type: String,
            default: "Save changes",
        },
        closeText: {
            type: String,
            default: "Close",
        },
        showHeader: {
            type: Boolean,
            default: true,
        },
        showFooter: {
            type: Boolean,
            default: true,
        },
    },
    emits: ["update:visible", "close", "save"],
    methods: {
        closeModal() {
            if (this.cancelable) {
                this.$emit("update:visible", false);
                this.$emit("close");
            }
        },
        saveModal() {
            this.$emit("save");
        },
    },
};
</script>
