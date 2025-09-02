<template>
    <div>
        <!-- Test Case Basic Info -->
        <div class="mb-4">
            <h5>{{ testCase.name }}</h5>
            <p v-if="testCase.description" class="text-muted">
                {{ testCase.description }}
            </p>
        </div>

        <!-- Prompts Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6>Prompts</h6>
            <Button variant="primary" size="sm" @click="addPrompt">
                <Icon name="plus" class="me-1" />
                Add Prompt
            </Button>
        </div>

        <div v-if="loading" class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <EmptyState
            v-else-if="prompts.length === 0"
            title="No prompts found"
            description="Add prompts to define test cases for evaluation."
            icon="chat-text"
            size="sm"
        >
            <Button variant="outline-primary" @click="addPrompt">
                Add First Prompt
            </Button>
        </EmptyState>

        <div v-else>
            <div
                v-for="(prompt, index) in prompts"
                :key="prompt.id || `new-${index}`"
                class="card mb-3"
            >
                <div class="card-body">
                    <div
                        class="d-flex justify-content-between align-items-start mb-2"
                    >
                        <h6 class="card-title mb-0">Prompt {{ index + 1 }}</h6>
                        <Dropdown @click.stop>
                            <DropdownItem @click="editPrompt(prompt, index)">
                                <Icon name="pencil" class="me-2" />
                                Edit
                            </DropdownItem>
                            <DropdownItem @click="deletePrompt(prompt, index)">
                                <Icon name="trash" class="me-2" />
                                Delete
                            </DropdownItem>
                        </Dropdown>
                    </div>

                    <div class="mb-2">
                        <strong>Input:</strong>
                        <div class="bg-light p-2 rounded mt-1">
                            <small>{{
                                prompt.input || "No input defined"
                            }}</small>
                        </div>
                    </div>

                    <div class="mb-2">
                        <strong>Expected Output:</strong>
                        <div class="bg-light p-2 rounded mt-1">
                            <small>{{
                                prompt.expectedOutput ||
                                "No expected output defined"
                            }}</small>
                        </div>
                    </div>

                    <div v-if="prompt.context" class="mb-2">
                        <strong>Context:</strong>
                        <div class="bg-light p-2 rounded mt-1">
                            <small>{{ prompt.context }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Prompt Modal -->
        <Modal
            v-if="showEditPromptModal"
            :visible="true"
            :title="editingPromptIndex !== null ? 'Edit Prompt' : 'Add Prompt'"
            @close="closeEditPromptModal"
            :showFooter="true"
        >
            <EditPromptModal
                ref="editPromptModal"
                :prompt="currentPrompt"
                :testCaseId="testCase.id"
            />

            <!-- Show store errors if any -->
            <div
                v-if="testCasesStore.hasErrors"
                class="alert alert-danger mt-3"
            >
                <div v-if="testCasesStore.errors.general">
                    {{ testCasesStore.errors.general }}
                </div>
                <div v-else>
                    <strong>Validation errors:</strong>
                    <ul class="mb-0">
                        <li
                            v-for="(error, field) in testCasesStore.errors"
                            :key="field"
                        >
                            {{ field }}:
                            {{
                                Array.isArray(error) ? error.join(", ") : error
                            }}
                        </li>
                    </ul>
                </div>
            </div>

            <template v-slot:footer>
                <Button
                    @click="closeEditPromptModal"
                    variant="secondary"
                    :disabled="saving"
                >
                    Cancel
                </Button>
                <Button
                    @click="savePrompt"
                    variant="primary"
                    :disabled="saving"
                >
                    <span
                        v-if="saving"
                        class="spinner-border spinner-border-sm me-2"
                        role="status"
                    ></span>
                    {{
                        saving
                            ? "Saving..."
                            : editingPromptIndex !== null
                            ? "Update"
                            : "Add"
                    }}
                </Button>
            </template>
        </Modal>

        <!-- Delete Prompt Confirmation -->
        <ConfirmationModal
            v-if="showDeletePromptModal"
            :visible="true"
            title="Delete Prompt"
            message="Are you sure you want to delete this prompt? This action cannot be undone."
            :requiresConfirmation="true"
            confirmationText="Please type 'DELETE' to confirm deletion:"
            confirmationValue="DELETE"
            confirmationPlaceholder="DELETE"
            confirmButtonText="Delete Prompt"
            @confirm="confirmDeletePrompt"
            @cancel="cancelDeletePrompt"
        />
    </div>
</template>

<script>
import { useTestCasesStore } from "@stores/TestCasesStore.js";
import Button from "@components/interactables/Button.vue";
import Icon from "@components/structure/Icon.vue";
import EmptyState from "@components/structure/EmptyState.vue";
import Modal from "@components/structure/Modal.vue";
import Dropdown, { DropdownItem } from "@components/interactables/Dropdown.vue";
import ConfirmationModal from "@components/interactables/ConfirmationModal.vue";
import EditPromptModal from "./EditPromptModal.vue";

export default {
    name: "DetailsTestCaseModal",
    components: {
        Button,
        Icon,
        EmptyState,
        Modal,
        Dropdown,
        DropdownItem,
        ConfirmationModal,
        EditPromptModal,
    },
    props: {
        testCase: {
            type: Object,
            required: true,
        },
    },
    emits: ["refresh"],
    data() {
        return {
            loading: false,
            saving: false,
            showEditPromptModal: false,
            showDeletePromptModal: false,
            currentPrompt: null,
            editingPromptIndex: null,
            promptToDelete: null,
        };
    },
    computed: {
        testCasesStore() {
            return useTestCasesStore();
        },
        prompts() {
            return this.testCase.prompts || [];
        },
    },
    methods: {
        addPrompt() {
            this.currentPrompt = {
                id: null,
                input: "",
                expectedOutput: "",
                context: "",
                testCaseId: this.testCase.id,
            };
            this.editingPromptIndex = null;
            this.showEditPromptModal = true;
        },
        editPrompt(prompt, index) {
            this.currentPrompt = { ...prompt };
            this.editingPromptIndex = index;
            this.showEditPromptModal = true;
        },
        deletePrompt(prompt, index) {
            this.promptToDelete = { prompt, index };
            this.showDeletePromptModal = true;
        },
        async confirmDeletePrompt() {
            if (this.promptToDelete && this.promptToDelete.prompt.id) {
                try {
                    await this.testCasesStore.deletePrompt(
                        this.promptToDelete.prompt.id
                    );
                    this.$emit("refresh");
                } catch (error) {
                    console.error("Failed to delete prompt:", error);
                }
            }
            this.cancelDeletePrompt();
        },
        cancelDeletePrompt() {
            this.showDeletePromptModal = false;
            this.promptToDelete = null;
        },
        async savePrompt() {
            try {
                // Validate the form first
                if (
                    this.$refs.editPromptModal &&
                    !this.$refs.editPromptModal.validate()
                ) {
                    return; // Don't save if validation fails
                }

                this.saving = true;

                // Prepare prompt data for API
                const promptData = {
                    input: this.currentPrompt.input,
                    expectedOutput: this.currentPrompt.expectedOutput,
                    context: this.currentPrompt.context || null,
                    testCaseId: this.testCase.id,
                };

                if (this.currentPrompt.id) {
                    // Update existing prompt
                    await this.testCasesStore.updatePrompt(
                        this.currentPrompt.id,
                        promptData
                    );
                } else {
                    // Create new prompt
                    await this.testCasesStore.createPrompt(promptData);
                }

                // Refresh the test case data
                this.$emit("refresh");

                // Close modal and reset
                this.showEditPromptModal = false;
                this.currentPrompt = null;
                this.editingPromptIndex = null;
            } catch (error) {
                console.error("Failed to save prompt:", error);
            } finally {
                this.saving = false;
            }
        },
        closeEditPromptModal() {
            this.showEditPromptModal = false;
            this.currentPrompt = null;
            this.editingPromptIndex = null;
            this.testCasesStore.clearErrors();

            // Reset the form validation state
            if (this.$refs.editPromptModal) {
                this.$refs.editPromptModal.reset();
            }
        },
    },
};
</script>
