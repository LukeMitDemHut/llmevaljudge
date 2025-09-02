<template>
    <Modal
        :visible="visible"
        :title="`${title} - ${confirmationValue}`"
        @close="onCancel"
    >
        <div class="mb-3">
            <p>{{ message }}</p>
            <div v-if="requiresConfirmation" class="mt-3">
                <p class="text-muted small">
                    {{ confirmationText }}
                </p>
                <InputText
                    v-model="confirmationInput"
                    :placeholder="confirmationPlaceholder"
                    class="mt-2"
                    @keyup.enter="onConfirm"
                />
            </div>
        </div>

        <template v-slot:footer>
            <Button @click="onCancel" variant="secondary"> Cancel </Button>
            <Button @click="onConfirm" variant="danger" :disabled="!canConfirm">
                {{ confirmButtonText }}
            </Button>
        </template>
    </Modal>
</template>

<script>
import Modal from "@components/structure/Modal.vue";
import Button from "@components/interactables/Button.vue";
import InputText from "@components/interactables/inputs/InputText.vue";

export default {
    components: {
        Modal,
        Button,
        InputText,
    },
    emits: ["confirm", "cancel", "update:visible"],
    props: {
        visible: {
            type: Boolean,
            default: false,
        },
        title: {
            type: String,
            default: "Confirm Action",
        },
        message: {
            type: String,
            required: true,
        },
        confirmButtonText: {
            type: String,
            default: "Confirm",
        },
        requiresConfirmation: {
            type: Boolean,
            default: false,
        },
        confirmationText: {
            type: String,
            default: "Please type the name to confirm:",
        },
        confirmationValue: {
            type: String,
            default: "",
        },
        confirmationPlaceholder: {
            type: String,
            default: "Type here to confirm",
        },
    },
    data() {
        return {
            confirmationInput: "",
        };
    },
    computed: {
        canConfirm() {
            if (!this.requiresConfirmation) {
                return true;
            }
            return (
                this.confirmationInput.trim() === this.confirmationValue.trim()
            );
        },
    },
    watch: {
        visible(newVal) {
            if (!newVal) {
                this.confirmationInput = "";
            }
        },
    },
    methods: {
        onConfirm() {
            if (this.canConfirm) {
                this.$emit("confirm");
                this.$emit("update:visible", false);
                this.confirmationInput = "";
            }
        },
        onCancel() {
            this.$emit("cancel");
            this.$emit("update:visible", false);
            this.confirmationInput = "";
        },
    },
};
</script>
