<template>
    <Card title="AI Providers" icon="plugs">
        <template v-slot:header-actions>
            <Button @click="modalVisible = true">
                <Icon name="tray" /> Add Model
            </Button>
        </template>
        <EmptyState
            v-if="settingsStore.models.length === 0"
            title="No Models Found"
            description="Add a model to get started."
            icon="warning-octagon"
        >
            <Button @click="modalVisible = true">
                <Icon name="plus" /> Add Model</Button
            >
        </EmptyState>
        <div v-else class="row g-3">
            <div
                v-for="model in settingsStore.models"
                :key="model.id"
                class="col-12 col-md-6 col-lg-4"
            >
                <Card class="h-100">
                    <div class="d-flex flex-column h-100">
                        <!-- Header row with name and dropdown -->
                        <div
                            class="d-flex justify-content-between align-items-center mb-3"
                        >
                            <h5
                                class="card-title mb-0 text-truncate me-2"
                                style="min-width: 0"
                            >
                                {{ model.name }}
                            </h5>
                            <Dropdown>
                                <DropdownItem
                                    icon="pencil"
                                    @click="editModel(model)"
                                >
                                    Edit
                                </DropdownItem>
                                <hr class="dropdown-divider" />
                                <DropdownItem
                                    icon="trash"
                                    :danger="true"
                                    @click="confirmDeleteModel(model)"
                                >
                                    Delete
                                </DropdownItem>
                            </Dropdown>
                        </div>

                        <!-- Details section -->
                        <div class="mb-3">
                            <small class="text-muted text-truncate d-block">
                                {{ model.provider.name }}
                            </small>
                        </div>

                        <!-- Footer info -->
                        <div class="mt-auto">
                            <div
                                class="d-flex align-items-center text-muted small"
                                title="Input Price (per 1K tokens)"
                            >
                                <Icon name="arrow-square-in" class="me-2" />
                                <span class="text-truncate">
                                    {{ model.inputPrice ?? 0 }} $
                                </span>
                            </div>
                            <div
                                class="d-flex align-items-center text-muted small"
                                title="Output Price (per 1K tokens)"
                            >
                                <Icon name="arrow-square-out" class="me-2" />
                                <span class="text-truncate">
                                    {{ model.outputPrice ?? 0 }} $
                                </span>
                            </div>
                            <div
                                class="d-flex align-items-center text-muted small"
                                title="Reasoning Price per 1K tokens"
                            >
                                <Icon name="brain" class="me-2" />
                                <span class="text-truncate">
                                    {{ model.reasonPrice ?? 0 }} $
                                </span>
                            </div>
                            <div
                                class="d-flex align-items-center text-muted small"
                                title="Fixed Price per API Request"
                            >
                                <Icon name="chat-text" class="me-2" />
                                <span class="text-truncate">
                                    {{ model.requestPrice ?? 0 }} $
                                </span>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </Card>
    <Modal
        v-model:visible="modalVisible"
        @close="onModalClose"
        :title="isEditMode ? 'Edit Model' : 'Add Model'"
    >
        <InputGroup
            label="Model Configuration"
            errorMessage="Please complete all required fields"
            ref="modelForm"
        >
            <InputText
                v-model="modelData.name"
                label="Model Name"
                placeholder="Enter model name"
                :maxLength="255"
                required
                helpText="The model Id at your provider."
            />

            <InputDropdown
                v-model="modelData.providerId"
                label="Provider"
                :options="providerOptions"
                placeholder="Select provider"
                required
                helpText="Select the AI provider"
                :searchable="true"
                :clearable="true"
            />

            <InputNumber
                v-model="modelData.inputPrice"
                label="Input Price (per token)"
                placeholder="0.001"
                required
                step="0.0001"
                helpText="Cost per input token"
            />

            <InputNumber
                v-model="modelData.outputPrice"
                label="Output Price (per token)"
                placeholder="0.002"
                step="0.0001"
                required
                helpText="Cost per output token"
            />

            <InputNumber
                v-model="modelData.requestPrice"
                label="Fixed Price (per API Request)"
                placeholder="0.003"
                step="0.0001"
                required
                helpText="Cost per API request"
            />

            <InputNumber
                v-model="modelData.reasonPrice"
                label="Reasoning Price"
                placeholder="0.001"
                step="0.0001"
                required
                helpText="Cost per reasoning token"
            />
        </InputGroup>

        <template v-slot:footer>
            <Button @click="onModalClose" variant="secondary"> Cancel </Button>
            <Button @click="saveModel" variant="primary">
                {{ isEditMode ? "Update Model" : "Add Model" }}
            </Button>
        </template>
    </Modal>

    <ConfirmationModal
        v-model:visible="deleteConfirmVisible"
        title="Delete Model"
        :message="`Deleting this model will permanently remove it from the system. This action cannot be undone.`"
        :requiresConfirmation="true"
        confirmationText="Please type the model name to confirm deletion:"
        :confirmationValue="modelToDelete?.name || ''"
        :confirmationPlaceholder="modelToDelete?.name || ''"
        confirmButtonText="Delete Model"
        @confirm="deleteModel"
        @cancel="cancelDelete"
    />
</template>
<script>
import { useSettingsStore } from "@stores/SettingsStore.js";
import EmptyState from "@components/structure/EmptyState.vue";
import Button from "@components/interactables/Button.vue";
import Icon from "@components/structure/Icon.vue";
import Modal from "@components/structure/Modal.vue";
import Card from "@components/structure/Card.vue";
import InputGroup from "@components/interactables/inputs/InputGroup.vue";
import InputText from "@components/interactables/inputs/InputText.vue";
import InputNumber from "@components/interactables/inputs/InputNumber.vue";
import InputPassword from "@components/interactables/inputs/InputPassword.vue";
import InputToggle from "@components/interactables/inputs/InputToggle.vue";
import InputDropdown from "@components/interactables/inputs/InputDropdown.vue";
import ConfirmationModal from "@components/interactables/ConfirmationModal.vue";
import Dropdown, { DropdownItem } from "@components/interactables/Dropdown.vue";

export default {
    components: {
        EmptyState,
        Button,
        Modal,
        Icon,
        Card,
        InputGroup,
        InputText,
        InputNumber,
        InputPassword,
        InputToggle,
        InputDropdown,
        ConfirmationModal,
        Dropdown,
        DropdownItem,
    },
    data() {
        return {
            modalVisible: false,
            deleteConfirmVisible: false,
            isEditMode: false,
            modelToDelete: null,
            editingModelId: null,
            modelData: {
                name: "",
                providerId: "",
                inputPrice: null,
                outputPrice: null,
                reasonPrice: null,
                requestPrice: null,
            },
        };
    },
    computed: {
        settingsStore() {
            return useSettingsStore();
        },
        // Create provider options from store data
        providerOptions() {
            return this.settingsStore.providers.map((provider) => ({
                value: provider.id,
                label: provider.name,
            }));
        },
    },
    methods: {
        onModalClose() {
            this.modalVisible = false;
            this.resetForm();
        },

        resetForm() {
            this.modelData = {
                name: "",
                providerId: "",
                inputPrice: null,
                outputPrice: null,
                reasonPrice: null,
                requestPrice: null,
            };
            this.isEditMode = false;
            this.editingModelId = null;
            // Reset validation state
            if (this.$refs.modelForm) {
                this.$refs.modelForm.reset();
            }
        },

        editModel(model) {
            this.isEditMode = true;
            this.editingModelId = model.id;
            this.modelData = {
                name: model.name,
                providerId: model.provider.id,
                inputPrice: model.inputPrice,
                outputPrice: model.outputPrice,
                reasonPrice: model.reasonPrice,
                requestPrice: model.requestPrice,
            };
            this.modalVisible = true;
        },

        confirmDeleteModel(model) {
            this.modelToDelete = model;
            this.deleteConfirmVisible = true;
        },

        async deleteModel() {
            try {
                if (this.modelToDelete) {
                    await this.settingsStore.deleteModel(this.modelToDelete.id);
                    this.cancelDelete();
                }
            } catch (error) {
                console.error("Failed to delete model:", error);
            }
        },

        cancelDelete() {
            this.deleteConfirmVisible = false;
            this.modelToDelete = null;
        },

        async saveModel() {
            try {
                if (this.$refs.modelForm.validate()) {
                    if (this.isEditMode) {
                        await this.settingsStore.updateModel(
                            this.editingModelId,
                            this.modelData
                        );
                    } else {
                        await this.settingsStore.createModel(this.modelData);
                    }
                    this.onModalClose();
                }
            } catch (error) {
                // Error handling is done in the store
                console.error("Failed to save model:", error);
            }
        },
    },
};
</script>

<style scoped>
.text-truncate {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.card-title.text-truncate {
    flex: 1;
    min-width: 0;
}
</style>
