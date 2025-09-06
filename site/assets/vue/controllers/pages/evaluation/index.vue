<template>
    <div class="container-fluid py-4" data-bs-theme="light">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <PageHeading title="Evaluation" icon="graph"> </PageHeading>

                <!-- Configuration Section (collapsible when results are loaded) -->
                <div
                    class="configuration-section"
                    :class="{ minimized: showResults }"
                >
                    <div
                        v-if="showResults"
                        class="minimized-header"
                        @click="showResults = false"
                    >
                        <div
                            class="d-flex justify-content-between align-items-center"
                        >
                            <div>
                                <Icon name="gear" class="me-2" />
                                <strong>Evaluation Configuration</strong>
                                <span class="text-muted ms-2"
                                    >(Click to reconfigure)</span
                                >
                            </div>
                            <Icon name="chevron-down" />
                        </div>
                    </div>

                    <div v-else class="configuration-content">
                        <!-- Global loading indicator -->
                        <div
                            v-if="
                                evaluationStore.isAnyLoading &&
                                !evaluationStore.loading.evaluation
                            "
                            class="alert alert-info d-flex align-items-center mb-4"
                        >
                            <div class="spinner me-3"></div>
                            <div>
                                <strong>Loading data...</strong>
                                <div class="small text-muted">
                                    Fetching evaluation data from the server.
                                    This may take a moment.
                                </div>
                            </div>
                        </div>

                        <Accordion
                            :items="[
                                {
                                    id: 'dimension',
                                    label: 'Dimension to compare',
                                },
                                {
                                    id: 'model',
                                    label: 'Models to include',
                                },
                                {
                                    id: 'metric',
                                    label: 'Metrics to include',
                                },
                                {
                                    id: 'test_case',
                                    label: 'Test Case to include',
                                },
                                {
                                    id: 'benchmark',
                                    label: 'Benchmark to include',
                                },
                                {
                                    id: 'prompt',
                                    label: 'Prompt to include',
                                },
                                {
                                    id: 'options',
                                    label: 'Evaluation Options',
                                },
                            ]"
                        >
                            <template v-slot:dimension-pane>
                                <p>
                                    Select what dimension to compare across the
                                    dataset
                                </p>
                                <InputDropdown
                                    v-model="selectedData.dimension"
                                    :options="
                                        evaluationStore.getAvailableDimensions
                                    "
                                    valueKey="id"
                                    labelKey="label"
                                />
                            </template>
                            <template v-slot:model-pane>
                                <p>
                                    Select which models to include in the
                                    evaluation
                                </p>
                                <div
                                    v-if="evaluationStore.loading.models"
                                    class="loading-state"
                                >
                                    <div class="spinner"></div>
                                    <span>Loading models...</span>
                                </div>
                                <InputMultiSelect
                                    v-else
                                    v-model="selectedData.model"
                                    :options="
                                        evaluationStore.getAvailableModels
                                    "
                                    placeholder="Select models..."
                                    :required="true"
                                />
                            </template>
                            <template v-slot:metric-pane>
                                <p>
                                    Select which metrics to include in the
                                    evaluation
                                </p>
                                <div
                                    v-if="evaluationStore.loading.metrics"
                                    class="loading-state"
                                >
                                    <div class="spinner"></div>
                                    <span>Loading metrics...</span>
                                </div>
                                <InputMultiSelect
                                    v-else
                                    v-model="selectedData.metric"
                                    :options="
                                        evaluationStore.getAvailableMetrics
                                    "
                                    placeholder="Select metrics..."
                                    :required="true"
                                />
                            </template>
                            <template v-slot:test_case-pane>
                                <p>
                                    Select which test cases to include in the
                                    evaluation
                                </p>
                                <div
                                    v-if="evaluationStore.loading.testCases"
                                    class="loading-state"
                                >
                                    <div class="spinner"></div>
                                    <span>Loading test cases...</span>
                                </div>
                                <InputMultiSelect
                                    v-else
                                    v-model="selectedData.test_case"
                                    :options="
                                        evaluationStore.getAvailableTestCases
                                    "
                                    placeholder="Select test cases..."
                                    :required="true"
                                />
                            </template>
                            <template v-slot:benchmark-pane>
                                <p>
                                    Select which benchmarks to include in the
                                    evaluation
                                </p>
                                <div
                                    v-if="evaluationStore.loading.benchmarks"
                                    class="loading-state"
                                >
                                    <div class="spinner"></div>
                                    <span>Loading benchmarks...</span>
                                </div>
                                <InputMultiSelect
                                    v-else
                                    v-model="selectedData.benchmark"
                                    :options="
                                        evaluationStore.getAvailableBenchmarks
                                    "
                                    placeholder="Select benchmarks..."
                                    :required="true"
                                />
                            </template>
                            <template v-slot:prompt-pane>
                                <p>
                                    Select which prompts to include in the
                                    evaluation
                                </p>
                                <div
                                    v-if="evaluationStore.loading.prompts"
                                    class="loading-state"
                                >
                                    <div class="spinner"></div>
                                    <span>Loading prompts...</span>
                                </div>
                                <InputMultiSelect
                                    v-else
                                    v-model="selectedData.prompt"
                                    :options="
                                        evaluationStore.getAvailablePrompts
                                    "
                                    placeholder="Select prompts..."
                                    :required="true"
                                />
                            </template>
                            <template v-slot:options-pane>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label"
                                            >Deduplication Strategy</label
                                        >
                                        <p class="text-muted small mb-3">
                                            How to handle results from the same
                                            test configuration across different
                                            benchmark runs
                                        </p>
                                        <InputDropdown
                                            v-model="
                                                selectedData.dedupeStrategy
                                            "
                                            :options="
                                                evaluationStore.getDedupeOptions
                                            "
                                            valueKey="id"
                                            labelKey="label"
                                        />
                                    </div>
                                </div>
                            </template>
                        </Accordion>

                        <!-- Load Dataset Button -->
                        <div class="mt-4">
                            <div class="d-flex justify-content-center">
                                <Button
                                    variant="primary"
                                    @click="loadDataset"
                                    :disabled="
                                        evaluationStore.loading.evaluation ||
                                        !isConfigurationValid
                                    "
                                    size="lg"
                                >
                                    <Icon name="play" class="me-2" />
                                    Load Evaluation Dataset
                                </Button>
                            </div>
                            <div
                                v-if="!isConfigurationValid"
                                class="text-center mt-2"
                            >
                                <small class="text-danger">
                                    Please select a dimension and at least one
                                    item from each relevant category
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Section -->
                <div v-if="showResults" class="results-section mt-4">
                    <EvaluationResults
                        :results="evaluationStore.evaluation.data"
                        :loading="evaluationStore.loading.evaluation"
                        :group-by="selectedData.dimension"
                        :dedupe-strategy="selectedData.dedupeStrategy"
                        :selected-entities="selectedData"
                        @reconfigure="reconfigure"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
<script>
import PageHeading from "@components/structure/PageHeading.vue";
import Tabs from "@components/structure/Tabs.vue";
import ButtonGroup from "@components/interactables/ButtonGroup.vue";
import Accordion from "@components/structure/Accordion.vue";
import InputDropdown from "@components/interactables/inputs/InputDropdown.vue";
import InputMultiSelect from "@components/interactables/inputs/InputMultiSelect.vue";
import Button from "@components/interactables/Button.vue";
import Icon from "@components/structure/Icon.vue";
import EvaluationResults from "./EvaluationResults.vue";
import { useEvaluationStore } from "@stores/EvaluationStore.js";

export default {
    name: "Evaluation",
    components: {
        PageHeading,
        Tabs,
        ButtonGroup,
        Accordion,
        InputDropdown,
        InputMultiSelect,
        Button,
        Icon,
        EvaluationResults,
    },
    data() {
        return {
            selectedData: {
                model: [],
                metric: [],
                test_case: [],
                benchmark: [],
                prompt: [],
                dimension: null,
                dedupeStrategy: "latest",
            },
            showResults: false,
        };
    },
    computed: {
        evaluationStore() {
            return useEvaluationStore();
        },
        isConfigurationValid() {
            return (
                this.selectedData.dimension &&
                this.selectedData.model.length > 0 &&
                this.selectedData.metric.length > 0 &&
                this.selectedData.test_case.length > 0 &&
                this.selectedData.benchmark.length > 0 &&
                this.selectedData.prompt.length > 0
            );
        },
    },
    watch: {
        "selectedData.dimension"(newDimension, oldDimension) {
            if (newDimension && newDimension !== oldDimension) {
                this.loadDataForDimension(newDimension);
            }
        },
    },
    async mounted() {
        // Load all data on component mount
        await this.evaluationStore.loadAllData();
    },
    methods: {
        async loadDataForDimension(dimension) {
            await this.evaluationStore.loadDataForDimension(dimension);
        },
        async loadDataset() {
            if (!this.isConfigurationValid) return;

            const params = {
                group: this.selectedData.dimension,
                dedupe: this.selectedData.dedupeStrategy,
            };

            // Add selected IDs to parameters
            if (this.selectedData.model.length > 0) {
                params.model = this.selectedData.model.join("-");
            }
            if (this.selectedData.metric.length > 0) {
                params.metric = this.selectedData.metric.join("-");
            }
            if (this.selectedData.test_case.length > 0) {
                params.test_case = this.selectedData.test_case.join("-");
            }
            if (this.selectedData.benchmark.length > 0) {
                params.benchmark = this.selectedData.benchmark.join("-");
            }
            if (this.selectedData.prompt.length > 0) {
                params.prompt = this.selectedData.prompt.join("-");
            }

            await this.evaluationStore.loadEvaluation(params);

            if (this.evaluationStore.isEvaluationValid) {
                this.showResults = true;
            }
        },
        reconfigure() {
            this.showResults = false;
            this.evaluationStore.resetEvaluation();
        },
    },
};
</script>

<style scoped>
.loading-state {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    color: #6b7280;
    font-size: 14px;
}

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid #e5e7eb;
    border-top: 2px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

/* Make multiselect components fill width */
:deep(.multiselect-container) {
    width: 100%;
}

:deep(.selected-tag) {
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Better responsive handling for long prompt labels */
:deep(.option-label) {
    word-break: break-word;
    line-height: 1.4;
    padding: 2px 0;
}

/* Accordion content spacing */
:deep(.accordion-content) {
    padding: 20px;
}

:deep(.accordion-content p) {
    margin-bottom: 15px;
    color: #6b7280;
    font-size: 14px;
}

/* Configuration section styling */
.configuration-section {
    transition: all 0.3s ease;
}

.configuration-section.minimized {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 0;
    margin-bottom: 20px;
}

.minimized-header {
    padding: 15px 20px;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.minimized-header:hover {
    background-color: #f1f5f9;
}

.configuration-content {
    transition: opacity 0.3s ease;
}

.results-section {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Button styling */
.btn-lg {
    padding: 12px 32px;
    font-size: 16px;
    font-weight: 600;
}

/* Form styling */
.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}
</style>
