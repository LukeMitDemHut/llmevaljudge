<template>
    <div class="container-fluid py-4" data-bs-theme="light">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <PageHeading title="Test Cases" icon="list-checks">
                    <Button variant="primary" @click="addTestCase"
                        >Add Test Case</Button
                    >
                </PageHeading>
                <Card>
                    <EmptyState
                        v-if="testCasesStore.testCases.length === 0"
                        title="No test cases found"
                        description="Create a new test case to get started."
                        icon="list-checks"
                    >
                        <Button variant="primary" @click="addTestCase"
                            >Create Test Case</Button
                        >
                    </EmptyState>
                    <div v-else>
                        <TestCaseList
                            :testCases="testCasesStore.testCases"
                            @delete="prepareDeleteTestCase"
                            @select="showTestCaseDetails"
                        />
                    </div>
                </Card>
            </div>
        </div>
    </div>

    <ConfirmationModal
        v-if="showConfirmationModal"
        :visible="true"
        title="Delete Test Case"
        message="Deleting a test case will remove it and all of its prompts permanently."
        @confirm="deleteTestCase"
        @cancel="cancelDeleteTestCase"
        :requiresConfirmation="true"
        :confirmationText="
            'To confirm the deletion of this test case type its name: ' +
            currentTestCase.name
        "
        :confirmationValue="currentTestCase.name"
        :confirmationPlaceholder="currentTestCase.name"
    />

    <Modal
        v-if="showNewTestCaseModal"
        :visible="true"
        title="Create a New Test Case"
        @close="closeNewTestCaseModal"
    >
        <EditTestCaseModal
            ref="editTestCaseModal"
            :testCase="currentTestCase"
        />

        <!-- Show store errors if any -->
        <div v-if="testCasesStore.hasErrors" class="alert alert-danger mt-3">
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
                        {{ Array.isArray(error) ? error.join(", ") : error }}
                    </li>
                </ul>
            </div>
        </div>

        <template v-slot:footer>
            <button
                type="button"
                class="btn btn-secondary"
                @click="closeNewTestCaseModal"
                :disabled="saving"
            >
                Cancel
            </button>
            <button
                type="button"
                class="btn btn-primary"
                @click="saveTestCase"
                :disabled="saving"
            >
                <span
                    v-if="saving"
                    class="spinner-border spinner-border-sm me-2"
                    role="status"
                ></span>
                {{ saving ? "Creating..." : "Create" }}
            </button>
        </template>
    </Modal>

    <Modal
        v-if="showDetailsTestCaseModal"
        :visible="true"
        title="Test Case Details"
        :showFooter="false"
        @close="showDetailsTestCaseModal = false"
        size="lg"
    >
        <DetailsTestCaseModal
            :testCase="currentTestCase"
            @refresh="refreshTestCaseDetails"
        />
    </Modal>
</template>

<script>
import { useTestCasesStore } from "@stores/TestCasesStore.js";
import PageHeading from "@components/structure/PageHeading.vue";
import Button from "@components/interactables/Button.vue";
import EmptyState from "@components/structure/EmptyState.vue";
import Card from "@components/structure/Card.vue";
import TestCaseList from "./TestCaseList.vue";
import ConfirmationModal from "@components/interactables/ConfirmationModal.vue";
import Modal from "@components/structure/Modal.vue";
import EditTestCaseModal from "./EditTestCaseModal.vue";
import DetailsTestCaseModal from "./DetailsTestCaseModal.vue";

export default {
    name: "TestCasesPage",
    components: {
        PageHeading,
        Button,
        EmptyState,
        Card,
        TestCaseList,
        ConfirmationModal,
        Modal,
        EditTestCaseModal,
        DetailsTestCaseModal,
    },
    props: {
        testcases: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            currentTestCase: null,
            showConfirmationModal: false,
            showNewTestCaseModal: false,
            showDetailsTestCaseModal: false,
            saving: false,
        };
    },
    methods: {
        prepareDeleteTestCase(testCase) {
            this.currentTestCase = { ...testCase };
            this.showConfirmationModal = true;
        },
        deleteTestCase() {
            this.testCasesStore.deleteTestCase(this.currentTestCase.id);
            this.cancelDeleteTestCase();
        },
        cancelDeleteTestCase() {
            this.showConfirmationModal = false;
            this.currentTestCase = null;
        },
        async showTestCaseDetails(testCase) {
            this.currentTestCase = { ...testCase };
            this.showDetailsTestCaseModal = true;

            // Load full test case data with prompts
            try {
                const fullTestCase =
                    await this.testCasesStore.loadTestCaseWithPrompts(
                        testCase.id
                    );
                this.currentTestCase = fullTestCase;
            } catch (error) {
                console.error("Failed to load test case details:", error);
            }
        },
        addTestCase() {
            this.currentTestCase = {
                id: null,
                name: "",
                description: "",
            };
            this.showNewTestCaseModal = true;
        },
        async saveTestCase() {
            try {
                // Validate the form first
                if (
                    this.$refs.editTestCaseModal &&
                    !this.$refs.editTestCaseModal.validate()
                ) {
                    return; // Don't save if validation fails
                }

                this.saving = true;

                // Prepare test case data for API
                const testCaseData = {
                    name: this.currentTestCase.name,
                    description: this.currentTestCase.description || "",
                };

                // Create the test case via the store
                await this.testCasesStore.createTestCase(testCaseData);

                // Close modal and reset
                this.showNewTestCaseModal = false;
                this.currentTestCase = null;
            } catch (error) {
                // Error handling is done in the store, but we can add user feedback here
                console.error("Failed to create test case:", error);
                // The store will handle validation errors and update its error state
            } finally {
                this.saving = false;
            }
        },
        closeNewTestCaseModal() {
            this.showNewTestCaseModal = false;
            this.currentTestCase = null;
            this.testCasesStore.clearErrors();

            // Reset the form validation state
            if (this.$refs.editTestCaseModal) {
                this.$refs.editTestCaseModal.reset();
            }
        },
        async refreshTestCaseDetails() {
            if (this.currentTestCase && this.currentTestCase.id) {
                try {
                    const updatedTestCase =
                        await this.testCasesStore.loadTestCaseWithPrompts(
                            this.currentTestCase.id
                        );
                    this.currentTestCase = updatedTestCase;
                } catch (error) {
                    console.error(
                        "Failed to refresh test case details:",
                        error
                    );
                }
            }
        },
    },
    computed: {
        testCasesStore() {
            return useTestCasesStore();
        },
    },
    mounted() {
        // Initialize store with server data on mount
        this.testCasesStore.initialize({
            testCases: this.testcases,
        });
    },
};
</script>
