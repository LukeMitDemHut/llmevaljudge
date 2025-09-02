<template>
    <div class="container-fluid py-4" data-bs-theme="light">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <PageHeading title="Benchmarks" icon="chart-line-up">
                    <Button variant="primary" @click="addBenchmark"
                        >Create Benchmark</Button
                    >
                </PageHeading>
                <Card>
                    <EmptyState
                        v-if="
                            benchmarksStore.benchmarks.length === 0 &&
                            !benchmarksStore.loading &&
                            !initialLoading
                        "
                        title="No benchmarks found"
                        description="Create a new benchmark to start evaluating your models."
                        icon="chart-line-up"
                    >
                        <Button variant="primary" @click="addBenchmark"
                            >Create Benchmark</Button
                        >
                    </EmptyState>
                    <div
                        v-else-if="benchmarksStore.loading && initialLoading"
                        class="text-center py-4"
                    >
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div v-else>
                        <BenchmarkList
                            :benchmarks="benchmarksStore.benchmarks"
                            @delete="prepareDeleteBenchmark"
                            @edit="editBenchmark"
                            @start="startBenchmark"
                            @start-missing="startMissingBenchmark"
                            @view-results="viewResults"
                        />
                    </div>
                </Card>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <ConfirmationModal
        v-if="showConfirmationModal"
        :visible="true"
        title="Delete Benchmark"
        message="Deleting a benchmark will remove it and all of its results permanently."
        @confirm="deleteBenchmark"
        @cancel="cancelDeleteBenchmark"
        :requiresConfirmation="true"
        :confirmationText="
            'To confirm the deletion of this benchmark type its name: ' +
            currentBenchmark.name
        "
        :confirmationValue="currentBenchmark.name"
        :confirmationPlaceholder="currentBenchmark.name"
    />

    <!-- Create/Edit Benchmark Modal -->
    <!-- Create/Edit Benchmark Modal -->
    <Modal
        v-if="showBenchmarkModal"
        :visible="true"
        :title="isEditMode ? 'Edit Benchmark' : 'Create Benchmark'"
        size="lg"
        :showFooter="false"
        @close="closeBenchmarkModal"
    >
        <CreateEditBenchmarkWizard
            :benchmark="currentBenchmark"
            :isEditMode="isEditMode"
            @saved="onBenchmarkSaved"
            @cancel="closeBenchmarkModal"
        />
    </Modal>

    <!-- Results Viewer Modal -->
    <Modal
        v-if="showResultsModal"
        :visible="true"
        :showFooter="false"
        title="Benchmark Results"
        size="xl"
        @close="closeResultsModal"
    >
        <BenchmarkResultsViewer
            :benchmark="currentBenchmark"
            @close="closeResultsModal"
        />
    </Modal>
</template>

<script>
import { useBenchmarksStore } from "@stores/BenchmarksStore.js";
import { useSettingsStore } from "@stores/SettingsStore.js";
import { useTestCasesStore } from "@stores/TestCasesStore.js";
import PageHeading from "@components/structure/PageHeading.vue";
import Button from "@components/interactables/Button.vue";
import EmptyState from "@components/structure/EmptyState.vue";
import Card from "@components/structure/Card.vue";
import BenchmarkList from "./BenchmarkList.vue";
import ConfirmationModal from "@components/interactables/ConfirmationModal.vue";
import Modal from "@components/structure/Modal.vue";
import CreateEditBenchmarkWizard from "./CreateEditBenchmarkWizard.vue";
import BenchmarkResultsViewer from "./BenchmarkResultsViewer.vue";

export default {
    name: "BenchmarksPage",
    components: {
        PageHeading,
        Button,
        EmptyState,
        Card,
        BenchmarkList,
        ConfirmationModal,
        Modal,
        CreateEditBenchmarkWizard,
        BenchmarkResultsViewer,
    },
    props: {
        benchmarks: {
            type: Array,
            default: () => [],
        },
        models: {
            type: Array,
            default: () => [],
        },
        metrics: {
            type: Array,
            default: () => [],
        },
        testCases: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            currentBenchmark: null,
            showConfirmationModal: false,
            showBenchmarkModal: false,
            showResultsModal: false,
            isEditMode: false,
            refreshTimer: null,
            initialLoading: true,
        };
    },
    computed: {
        benchmarksStore() {
            return useBenchmarksStore();
        },
        settingsStore() {
            return useSettingsStore();
        },
        testCasesStore() {
            return useTestCasesStore();
        },

        // Check if any modal is open to prevent auto-refresh during modal interactions
        isAnyModalOpen() {
            return (
                this.showConfirmationModal ||
                this.showBenchmarkModal ||
                this.showResultsModal
            );
        },
    },
    async mounted() {
        // Initialize stores with server data
        this.benchmarksStore.initialize({
            benchmarks: this.benchmarks,
            models: this.models,
            metrics: this.metrics,
            testCases: this.testCases,
        });

        this.settingsStore.initialize({
            models: this.models,
            metrics: this.metrics,
        });

        this.testCasesStore.initialize({
            testCases: this.testCases,
        });

        // Mark initial loading as complete since we have server data
        this.initialLoading = false;

        // Start auto-refresh timer
        this.startAutoRefresh();
    },

    beforeUnmount() {
        // Clean up the timer when component is destroyed
        this.stopAutoRefresh();
    },
    methods: {
        prepareDeleteBenchmark(benchmark) {
            this.currentBenchmark = { ...benchmark };
            this.showConfirmationModal = true;
        },

        async deleteBenchmark() {
            try {
                await this.benchmarksStore.deleteBenchmark(
                    this.currentBenchmark.id
                );
                this.cancelDeleteBenchmark();
            } catch (error) {
                console.error("Failed to delete benchmark:", error);
            }
        },

        cancelDeleteBenchmark() {
            this.showConfirmationModal = false;
            this.currentBenchmark = null;
        },

        addBenchmark() {
            this.currentBenchmark = {
                id: null,
                name: "",
                models: [],
                testCases: [],
                metrics: [],
            };
            this.isEditMode = false;
            this.showBenchmarkModal = true;
        },

        editBenchmark(benchmark) {
            this.currentBenchmark = { ...benchmark };
            this.isEditMode = true;
            this.showBenchmarkModal = true;
        },

        closeBenchmarkModal() {
            this.showBenchmarkModal = false;
            this.currentBenchmark = null;
            this.isEditMode = false;
            this.benchmarksStore.clearErrors();
        },

        onBenchmarkSaved() {
            this.closeBenchmarkModal();
        },

        async startBenchmark(benchmark) {
            try {
                await this.benchmarksStore.startBenchmark(benchmark.id);
            } catch (error) {
                console.error("Failed to start benchmark:", error);
            }
        },

        async startMissingBenchmark(benchmark) {
            try {
                await this.benchmarksStore.startMissingBenchmark(benchmark.id);
            } catch (error) {
                console.error("Failed to start missing benchmark:", error);
            }
        },

        viewResults(benchmark) {
            this.currentBenchmark = { ...benchmark };
            this.showResultsModal = true;
        },

        closeResultsModal() {
            this.showResultsModal = false;
            this.currentBenchmark = null;
        },

        // Auto-refresh functionality
        startAutoRefresh() {
            // Refresh every 5 seconds (5000ms)
            this.refreshTimer = setInterval(() => {
                this.refreshBenchmarks();
            }, 5000);
        },

        stopAutoRefresh() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
        },

        async refreshBenchmarks() {
            // Only refresh if no modal is open to avoid interfering with user interactions
            if (!this.isAnyModalOpen && !this.benchmarksStore.saving) {
                try {
                    await this.benchmarksStore.refreshBenchmarksQuietly();
                } catch (error) {
                    // Silently fail on auto-refresh to avoid annoying the user
                    console.debug("Auto-refresh failed:", error);
                }
            }
        },
    },
};
</script>
