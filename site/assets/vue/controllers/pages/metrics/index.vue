<template>
    <div class="container-fluid py-4" data-bs-theme="light">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <PageHeading title="Metrics" icon="chart-line">
                    <Button variant="primary" @click="addMetric"
                        >Add Metric</Button
                    >
                </PageHeading>
                <Card>
                    <!--TODO: search bar-->
                    <EmptyState
                        v-if="metricsStore.metrics.length === 0"
                        title="No metrics found"
                        description="Create a new metric to get started."
                        icon="chart-line"
                    >
                        <Button variant="primary" @click="addMetric"
                            >Create Metric</Button
                        >
                    </EmptyState>
                    <div v-else>
                        <MetricList
                            :metrics="metricsStore.metrics"
                            @delete="prepareDeleteMetric"
                            @select="showMetricDetails"
                            @duplicate="duplicateMetric"
                            @edit="editMetric"
                        />
                    </div>
                </Card>
            </div>
        </div>
    </div>

    <ConfirmationModal
        v-if="showConfirmationModal"
        :visible="true"
        title="Delete Metric"
        message="Deleting a metric will remove it and all of its associated data."
        @confirm="deleteMetric"
        @cancel="showConfirmationModal = false"
        :requiresConfirmation="true"
        :confirmationText="
            'To confirm the deletion of this metric type its name: ' +
            currentMetric.name
        "
        :confirmationValue="currentMetric.name"
        :confirmationPlaceholder="currentMetric.name"
    />
    <Modal
        v-if="editMetricModal"
        :visible="true"
        :title="currentMetric?.id ? 'Edit Metric' : 'Create a New Metric'"
        @close="closeEditMetricModal"
    >
        <EditMetricModal
            ref="editMetricModal"
            :metric="currentMetric"
            :models="models"
            :availableParams="availableParams"
        />

        <!-- Show store errors if any -->
        <div v-if="metricsStore.hasErrors" class="alert alert-danger mt-3">
            <div v-if="metricsStore.errors.general">
                {{ metricsStore.errors.general }}
            </div>
            <div v-else>
                <strong>Validation errors:</strong>
                <ul class="mb-0">
                    <li
                        v-for="(error, field) in metricsStore.errors"
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
                @click="closeEditMetricModal"
                :disabled="saving"
            >
                Cancel
            </button>
            <button
                type="button"
                class="btn btn-primary"
                @click="saveMetric"
                :disabled="saving"
            >
                <span
                    v-if="saving"
                    class="spinner-border spinner-border-sm me-2"
                    role="status"
                ></span>
                {{
                    saving
                        ? currentMetric?.id
                            ? "Updating..."
                            : "Creating..."
                        : currentMetric?.id
                        ? "Update"
                        : "Create"
                }}
            </button>
        </template>
    </Modal>
    <Modal
        v-if="showDetailsMetricModal"
        :visible="true"
        title="Metric Details"
        :showFooter="false"
        @close="showDetailsMetricModal = false"
    >
        <DetailsMetricModal :metric="currentMetric" />
    </Modal>
</template>

<script>
import { useMetricsStore } from "@stores/MetricsStore.js";
import PageHeading from "@components/structure/PageHeading.vue";
import Button from "@components/interactables/Button.vue";
import EmptyState from "@components/structure/EmptyState.vue";
import Card from "@components/structure/Card.vue";
import MetricList from "./MetricList.vue";
import ConfirmationModal from "@components/interactables/ConfirmationModal.vue";
import Modal from "@components/structure/Modal.vue";
import EditMetricModal from "./EditMetricModal.vue";
import DetailsMetricModal from "./DetailsMetricModal.vue";

export default {
    name: "MetricsPage",
    components: {
        PageHeading,
        Button,
        EmptyState,
        Card,
        MetricList,
        ConfirmationModal,
        Modal,
        EditMetricModal,
        DetailsMetricModal,
    },
    props: {
        metrics: {
            type: Array,
            default: () => [],
        },
        models: {
            type: Array,
            default: () => [],
        },
        availableParams: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            currentMetric: null,
            showConfirmationModal: false,
            editMetricModal: false,
            showDetailsMetricModal: false,
            saving: false,
        };
    },
    methods: {
        prepareDeleteMetric(metric) {
            this.currentMetric = { ...metric };
            this.showConfirmationModal = true;
        },
        deleteMetric() {
            this.metricsStore.deleteMetric(this.currentMetric.id);
            this.showConfirmationModal = false;
            this.currentMetric = null;
        },
        showMetricDetails(metric) {
            this.currentMetric = { ...metric };
            this.showDetailsMetricModal = true;
        },
        duplicateMetric(metric) {
            this.currentMetric = {
                ...metric,
                id: null, // Clear ID for new metric
                name: metric.name + " (Copy)", // Append "(Copy)" to name
                ratingModelId: metric.ratingModel?.id || null, // Extract ID from nested object if it exists
            };
            this.editMetricModal = true;
        },
        editMetric(metric) {
            this.currentMetric = {
                ...metric,
                ratingModelId: metric.ratingModel?.id || null, // Extract ID from nested object if it exists
            };
            this.editMetricModal = true;
        },
        addMetric() {
            this.currentMetric = {
                id: null,
                name: "",
                type: null,
                description: "",
                ratingModelId: null,
                definition: {
                    type: "steps",
                    steps: [""],
                    criteria: "",
                },
                param: [],
                threshold: null,
            };
            this.editMetricModal = true;
        },
        async saveMetric() {
            try {
                // Validate the form first
                if (
                    this.$refs.editMetricModal &&
                    !this.$refs.editMetricModal.validate()
                ) {
                    return; // Don't save if validation fails
                }

                this.saving = true;

                // Prepare metric data for API
                const metricData = {
                    name: this.currentMetric.name,
                    type: this.currentMetric.type,
                    description: this.currentMetric.description || "",
                    rating_model_id: this.currentMetric.ratingModelId, // Use snake_case for API
                    threshold: this.currentMetric.threshold,
                    param: this.currentMetric.param || [],
                    definition: this.currentMetric.definition || {},
                };

                // Additional validation to ensure ratingModelId is not null
                if (!metricData.rating_model_id) {
                    console.error("Rating model ID is null or undefined!");
                    return;
                }

                // Check if we're editing (has ID) or creating (no ID)
                if (this.currentMetric.id) {
                    // Update existing metric
                    await this.metricsStore.updateMetric(
                        this.currentMetric.id,
                        metricData
                    );
                } else {
                    // Create new metric
                    await this.metricsStore.createMetric(metricData);
                }

                // Close modal and reset
                this.editMetricModal = false;
                this.currentMetric = null;
            } catch (error) {
                // Error handling is done in the store, but we can add user feedback here
                console.error("Failed to save metric:", error);
                // The store will handle validation errors and update its error state
            } finally {
                this.saving = false;
            }
        },
        closeEditMetricModal() {
            this.editMetricModal = false;
            this.currentMetric = null;
            this.metricsStore.clearErrors();

            // Reset the form validation state
            if (this.$refs.editMetricModal) {
                this.$refs.editMetricModal.reset();
            }
        },
    },
    computed: {
        metricsStore() {
            return useMetricsStore();
        },
    },
    mounted() {
        // Initialize store with server data on mount
        this.metricsStore.initialize({
            metrics: this.metrics,
            models: this.models,
        });
    },
};
</script>
