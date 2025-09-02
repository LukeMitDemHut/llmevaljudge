<template>
    <Card title="AI Providers" icon="plugs">
        <template v-slot:header-actions>
            <Button @click="modalVisible = true">
                <Icon name="plus" /> Add Provider
            </Button>
        </template>
        <EmptyState
            v-if="settingsStore.providers.length === 0"
            title="No Providers Found"
            description="Add a provider to get started."
            icon="warning-octagon"
        >
            <Button @click="modalVisible = true">
                <Icon name="plus" /> Add Provider</Button
            >
        </EmptyState>
        <div v-else class="row g-3">
            <div
                v-for="provider in settingsStore.providers"
                :key="provider.id"
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
                                {{ provider.name }}
                            </h5>
                            <Dropdown>
                                <DropdownItem
                                    icon="pencil"
                                    @click="editProvider(provider)"
                                >
                                    Edit
                                </DropdownItem>
                                <hr class="dropdown-divider" />
                                <DropdownItem
                                    icon="trash"
                                    :danger="true"
                                    @click="confirmDeleteProvider(provider)"
                                >
                                    Delete
                                </DropdownItem>
                            </Dropdown>
                        </div>

                        <!-- Details section -->
                        <div class="mb-3">
                            <small class="text-muted text-truncate d-block">
                                {{ provider.apiUrl }}
                            </small>
                        </div>

                        <!-- Footer info -->
                        <div class="mt-auto">
                            <div
                                class="d-flex align-items-center text-muted small mb-2"
                            >
                                <Icon name="key" class="me-2" />
                                <span>API Key configured</span>
                            </div>
                            <div
                                class="d-flex align-items-center text-muted small"
                            >
                                <Icon name="globe" class="me-2" />
                                <span class="text-truncate">{{
                                    provider.apiUrl
                                }}</span>
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
        :title="isEditMode ? 'Edit Provider' : 'Add Provider'"
    >
        <InputGroup
            label="Provider Configuration"
            errorMessage="Please complete all required fields"
            ref="providerForm"
        >
            <InputText
                v-model="providerData.name"
                label="Provider Name"
                placeholder="Enter provider name"
                :maxLength="255"
                required
                helpText="A name for you to identify this provider"
            />

            <InputPassword
                v-model="providerData.apiKey"
                label="API Key"
                placeholder="Enter your API key"
                required
                :validator="(value) => (value ? true : false)"
                errorMessage="API key is required"
            />

            <InputText
                v-model="providerData.apiUrl"
                label="API URL"
                placeholder="https://api.provider.com/v1"
                required
            />
        </InputGroup>

        <template v-slot:footer>
            <Button @click="onModalClose" variant="secondary"> Cancel </Button>
            <Button @click="saveProvider" variant="primary">
                {{ isEditMode ? "Update Provider" : "Add Provider" }}
            </Button>
        </template>
    </Modal>

    <ConfirmationModal
        v-model:visible="deleteConfirmVisible"
        title="Delete Provider"
        :message="`Deleting this provider will permanently remove all associated models. This action cannot be undone.`"
        :requiresConfirmation="true"
        confirmationText="Please type the provider name to confirm deletion:"
        :confirmationValue="providerToDelete?.name || ''"
        :confirmationPlaceholder="providerToDelete?.name || ''"
        confirmButtonText="Delete Provider"
        @confirm="deleteProvider"
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
import InputPassword from "@components/interactables/inputs/InputPassword.vue";
import InputToggle from "@components/interactables/inputs/InputToggle.vue";
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
        InputPassword,
        InputToggle,
        ConfirmationModal,
        Dropdown,
        DropdownItem,
    },
    data() {
        return {
            modalVisible: false,
            deleteConfirmVisible: false,
            isEditMode: false,
            providerToDelete: null,
            editingProviderId: null,
            providerData: {
                name: "",
                apiKey: "",
                apiUrl: "",
            },
        };
    },
    computed: {
        settingsStore() {
            return useSettingsStore();
        },
    },
    methods: {
        onModalClose() {
            this.modalVisible = false;
            this.resetForm();
        },

        resetForm() {
            this.providerData = {
                name: "",
                apiKey: "",
                apiUrl: "",
            };
            this.isEditMode = false;
            this.editingProviderId = null;
            // Reset validation state
            if (this.$refs.providerForm) {
                this.$refs.providerForm.reset();
            }
        },

        editProvider(provider) {
            this.isEditMode = true;
            this.editingProviderId = provider.id;
            this.providerData = {
                name: provider.name,
                apiKey: provider.apiKey,
                apiUrl: provider.apiUrl,
            };
            this.modalVisible = true;
        },

        confirmDeleteProvider(provider) {
            this.providerToDelete = provider;
            this.deleteConfirmVisible = true;
        },

        async deleteProvider() {
            try {
                if (this.providerToDelete) {
                    await this.settingsStore.deleteProvider(
                        this.providerToDelete.id
                    );
                    this.cancelDelete();
                }
            } catch (error) {
                console.error("Failed to delete provider:", error);
            }
        },

        cancelDelete() {
            this.deleteConfirmVisible = false;
            this.providerToDelete = null;
        },

        async saveProvider() {
            try {
                if (this.$refs.providerForm.validate()) {
                    if (this.isEditMode) {
                        await this.settingsStore.updateProvider(
                            this.editingProviderId,
                            this.providerData
                        );
                    } else {
                        await this.settingsStore.createProvider(
                            this.providerData
                        );
                    }
                    this.onModalClose();
                }
            } catch (error) {
                // Error handling is done in the store
                console.error("Failed to save provider:", error);
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
