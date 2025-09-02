<template>
    <div class="benchmark-wizard">
        <!-- Progress Steps -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div
                        v-for="(step, index) in steps"
                        :key="step.key"
                        class="d-flex align-items-center"
                        :class="{ 'flex-fill': index < steps.length - 1 }"
                    >
                        <div
                            class="step-indicator rounded-circle d-flex align-items-center justify-content-center me-2"
                            :class="{
                                'bg-primary text-white': index <= currentStep,
                                'bg-light text-muted': index > currentStep,
                            }"
                            style="width: 32px; height: 32px; min-width: 32px"
                        >
                            <span class="small fw-bold">{{ index + 1 }}</span>
                        </div>
                        <span
                            class="small fw-bold me-3"
                            :class="{
                                'text-primary': index <= currentStep,
                                'text-muted': index > currentStep,
                            }"
                        >
                            {{ step.title }}
                        </span>
                        <div
                            v-if="index < steps.length - 1"
                            class="flex-fill border-top mx-3"
                            :class="{
                                'border-primary': index < currentStep,
                                'border-light': index >= currentStep,
                            }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step Content -->
        <div class="step-content mb-4" style="min-height: 400px">
            <!-- Step 1: Basic Info -->
            <div v-show="currentStep === 0">
                <h5 class="mb-3">Basic Information</h5>
                <div class="row">
                    <div class="col-12">
                        <div class="mb-3">
                            <label for="benchmarkName" class="form-label"
                                >Benchmark Name</label
                            >
                            <input
                                id="benchmarkName"
                                v-model="formData.name"
                                type="text"
                                class="form-control"
                                placeholder="Enter benchmark name"
                                required
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Select Models -->
            <div v-show="currentStep === 1">
                <h5 class="mb-3">Select Models</h5>
                <div v-if="benchmarksStore.loading" class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading models...</span>
                    </div>
                </div>
                <div
                    v-else-if="(benchmarksStore.models || []).length === 0"
                    class="text-center py-4"
                >
                    <EmptyState
                        title="No models available"
                        description="You need to configure models in Settings before creating a benchmark."
                        icon="cpu"
                    />
                </div>
                <div v-else class="row">
                    <div
                        v-for="model in benchmarksStore.models"
                        :key="model.id"
                        class="col-12 col-md-6 col-lg-4 mb-3"
                    >
                        <Card
                            class="h-100 cursor-pointer model-selection-card"
                            :class="{
                                'border-primary bg-primary bg-opacity-10':
                                    isModelSelected(model.id),
                            }"
                            @click="toggleModel(model.id)"
                        >
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <input
                                        type="checkbox"
                                        :checked="isModelSelected(model.id)"
                                        class="form-check-input"
                                        @click.stop
                                        @change="toggleModel(model.id)"
                                    />
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">{{ model.name }}</h6>
                                    <small class="text-muted">{{
                                        model.provider.name
                                    }}</small>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- Step 3: Select Test Cases -->
            <div v-show="currentStep === 2">
                <h5 class="mb-3">Select Test Cases</h5>
                <div v-if="benchmarksStore.loading" class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden"
                            >Loading test cases...</span
                        >
                    </div>
                </div>
                <div
                    v-else-if="(benchmarksStore.testCases || []).length === 0"
                    class="text-center py-4"
                >
                    <EmptyState
                        title="No test cases available"
                        description="You need to create test cases before creating a benchmark."
                        icon="list-checks"
                    />
                </div>
                <div v-else class="row">
                    <div
                        v-for="testCase in benchmarksStore.testCases"
                        :key="testCase.id"
                        class="col-12 col-md-6 col-lg-4 mb-3"
                    >
                        <Card
                            class="h-100 cursor-pointer testcase-selection-card"
                            :class="{
                                'border-primary bg-primary bg-opacity-10':
                                    isTestCaseSelected(testCase.id),
                            }"
                            @click="toggleTestCase(testCase.id)"
                        >
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <input
                                        type="checkbox"
                                        :checked="
                                            isTestCaseSelected(testCase.id)
                                        "
                                        class="form-check-input"
                                        @click.stop
                                        @change="toggleTestCase(testCase.id)"
                                    />
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">{{ testCase.name }}</h6>
                                    <small class="text-muted">
                                        {{ getPromptCount(testCase) }} prompts
                                    </small>
                                    <div
                                        v-if="testCase.description"
                                        class="small text-muted mt-1"
                                    >
                                        {{ testCase.description }}
                                    </div>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- Step 4: Select Metrics -->
            <div v-show="currentStep === 3">
                <h5 class="mb-3">Select Metrics</h5>
                <div v-if="benchmarksStore.loading" class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading metrics...</span>
                    </div>
                </div>
                <div
                    v-else-if="(benchmarksStore.metrics || []).length === 0"
                    class="text-center py-4"
                >
                    <EmptyState
                        title="No metrics available"
                        description="You need to configure metrics in Settings before creating a benchmark."
                        icon="chart-line-up"
                    />
                </div>
                <div v-else class="row">
                    <div
                        v-for="metric in benchmarksStore.metrics"
                        :key="metric.id"
                        class="col-12 col-md-6 col-lg-4 mb-3"
                    >
                        <Card
                            class="h-100 cursor-pointer metric-selection-card"
                            :class="{
                                'border-primary bg-primary bg-opacity-10':
                                    isMetricSelected(metric.id),
                            }"
                            @click="toggleMetric(metric.id)"
                        >
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <input
                                        :id="`metric-${metric.id}`"
                                        type="checkbox"
                                        :checked="isMetricSelected(metric.id)"
                                        class="form-check-input"
                                        @click.stop
                                        @change="toggleMetric(metric.id)"
                                    />
                                </div>
                                <div class="flex-grow-1">
                                    <label
                                        :for="`metric-${metric.id}`"
                                        class="cursor-pointer w-100"
                                    >
                                        <h6 class="mb-1">{{ metric.name }}</h6>
                                        <small class="text-muted">{{
                                            getMetricTypeLabel(metric.type)
                                        }}</small>
                                        <div
                                            v-if="metric.description"
                                            class="small text-muted mt-1"
                                        >
                                            {{ metric.description }}
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- Step 5: Review & Warnings -->
            <div v-show="currentStep === 4">
                <h5 class="mb-3">Review Configuration</h5>

                <!-- Configuration Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <Card>
                            <h6 class="text-primary">
                                <Icon name="cpu" class="me-2" />
                                Models ({{ selectedModels.length }})
                            </h6>
                            <ul class="list-unstyled mb-0 small">
                                <li
                                    v-for="model in selectedModels"
                                    :key="model.id"
                                >
                                    {{ model.name }}
                                </li>
                            </ul>
                        </Card>
                    </div>
                    <div class="col-md-4">
                        <Card>
                            <h6 class="text-primary">
                                <Icon name="list-checks" class="me-2" />
                                Test Cases ({{ selectedTestCases.length }})
                            </h6>
                            <ul class="list-unstyled mb-0 small">
                                <li
                                    v-for="testCase in selectedTestCases"
                                    :key="testCase.id"
                                >
                                    {{ testCase.name }} ({{
                                        getPromptCount(testCase)
                                    }}
                                    prompts)
                                </li>
                            </ul>
                        </Card>
                    </div>
                    <div class="col-md-4">
                        <Card>
                            <h6 class="text-primary">
                                <Icon name="chart-line-up" class="me-2" />
                                Metrics ({{ selectedMetrics.length }})
                            </h6>
                            <ul class="list-unstyled mb-0 small">
                                <li
                                    v-for="metric in selectedMetrics"
                                    :key="metric.id"
                                >
                                    {{ metric.name }}
                                </li>
                            </ul>
                        </Card>
                    </div>
                </div>

                <!-- Warnings -->
                <div v-if="warnings.length > 0" class="alert alert-warning">
                    <h6 class="alert-heading">
                        <Icon name="exclamation-triangle" class="me-2" />
                        Configuration Warnings
                    </h6>
                    <ul class="mb-0">
                        <li v-for="warning in warnings" :key="warning">
                            {{ warning }}
                        </li>
                    </ul>
                </div>

                <!-- Estimates -->
                <div class="row">
                    <div class="col-md-6">
                        <Card class="bg-light">
                            <h6 class="mb-2">Benchmark Estimate</h6>
                            <div class="d-flex justify-content-between">
                                <span>Total Evaluations:</span>
                                <span class="fw-bold">{{
                                    totalEvaluations
                                }}</span>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="d-flex justify-content-between">
            <Button
                variant="secondary"
                @click="currentStep > 0 ? currentStep-- : $emit('cancel')"
            >
                {{ currentStep > 0 ? "Previous" : "Cancel" }}
            </Button>

            <div>
                <Button
                    v-if="currentStep < steps.length - 1"
                    variant="primary"
                    @click="nextStep"
                    :disabled="!canProceedToNextStep"
                >
                    Next
                </Button>
                <Button
                    v-else
                    variant="success"
                    @click="saveBenchmark"
                    :disabled="!canSave || benchmarksStore.loading"
                >
                    <span
                        v-if="benchmarksStore.loading"
                        class="spinner-border spinner-border-sm me-2"
                    ></span>
                    {{ isEditMode ? "Update Benchmark" : "Create Benchmark" }}
                </Button>
            </div>
        </div>

        <!-- Show store errors if any -->
        <div v-if="benchmarksStore.hasErrors" class="alert alert-danger mt-3">
            <div v-if="benchmarksStore.errors.general">
                {{ benchmarksStore.errors.general }}
            </div>
            <div v-else>
                <strong>Validation errors:</strong>
                <ul class="mb-0">
                    <li
                        v-for="(error, field) in benchmarksStore.errors"
                        :key="field"
                    >
                        {{ field }}:
                        {{ Array.isArray(error) ? error.join(", ") : error }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
import { useBenchmarksStore } from "@stores/BenchmarksStore.js";
import Button from "@components/interactables/Button.vue";
import Card from "@components/structure/Card.vue";
import EmptyState from "@components/structure/EmptyState.vue";
import Icon from "@components/structure/Icon.vue";

export default {
    name: "CreateEditBenchmarkWizard",
    components: {
        Button,
        Card,
        EmptyState,
        Icon,
    },
    props: {
        benchmark: {
            type: Object,
            default: () => ({}),
        },
        isEditMode: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["saved", "cancel"],
    data() {
        return {
            currentStep: 0,
            steps: [
                { key: "basic", title: "Basic Info" },
                { key: "models", title: "Models" },
                { key: "testcases", title: "Test Cases" },
                { key: "metrics", title: "Metrics" },
                { key: "review", title: "Review" },
            ],
            formData: {
                name: "",
                modelIds: [],
                testCaseIds: [],
                metricIds: [],
            },
        };
    },
    watch: {
        benchmark: {
            immediate: true,
            handler(newBenchmark) {
                if (this.isEditMode && newBenchmark) {
                    console.log("Benchmark prop changed:", newBenchmark);
                    this.initializeFormData();
                }
            },
        },
    },
    computed: {
        benchmarksStore() {
            return useBenchmarksStore();
        },

        selectedModels() {
            return (this.benchmarksStore.models || []).filter((m) =>
                this.formData.modelIds.includes(m.id)
            );
        },

        selectedTestCases() {
            return (this.benchmarksStore.testCases || []).filter((tc) =>
                this.formData.testCaseIds.includes(tc.id)
            );
        },

        selectedMetrics() {
            return (this.benchmarksStore.metrics || []).filter((m) =>
                this.formData.metricIds.includes(m.id)
            );
        },

        canProceedToNextStep() {
            switch (this.currentStep) {
                case 0:
                    return this.formData.name.trim().length > 0;
                case 1:
                    return this.formData.modelIds.length > 0;
                case 2:
                    return this.formData.testCaseIds.length > 0;
                case 3:
                    return this.formData.metricIds.length > 0;
                default:
                    return true;
            }
        },

        canSave() {
            return (
                this.formData.name.trim().length > 0 &&
                this.formData.modelIds.length > 0 &&
                this.formData.testCaseIds.length > 0 &&
                this.formData.metricIds.length > 0
            );
        },

        warnings() {
            const warnings = [];

            // Check for metric parameter compatibility
            const requiredParams = new Set();
            this.selectedMetrics.forEach((metric) => {
                if (metric.definition && Array.isArray(metric.definition)) {
                    metric.definition.forEach((param) =>
                        requiredParams.add(param)
                    );
                }
            });

            if (requiredParams.has("expected_output")) {
                const promptsWithoutExpectedOutput =
                    this.getTotalPromptsWithoutExpectedOutput();
                if (promptsWithoutExpectedOutput > 0) {
                    warnings.push(
                        `${promptsWithoutExpectedOutput} prompts will be skipped because they lack expected output required by selected metrics.`
                    );
                }
            }

            if (requiredParams.has("context")) {
                const promptsWithoutContext =
                    this.getTotalPromptsWithoutContext();
                if (promptsWithoutContext > 0) {
                    warnings.push(
                        `${promptsWithoutContext} prompts will be skipped because they lack context required by selected metrics.`
                    );
                }
            }

            return warnings;
        },

        totalEvaluations() {
            const validPrompts = this.getTotalValidPrompts();
            return (
                validPrompts *
                this.selectedModels.length *
                this.selectedMetrics.length
            );
        },
    },

    async mounted() {
        // Data should already be initialized by the parent component
        // No need to load data since it's passed from the controller

        // Initialize form data
        this.initializeFormData();
    },

    methods: {
        initializeFormData() {
            if (this.isEditMode && this.benchmark) {
                console.log("Editing benchmark:", this.benchmark);
                console.log("Benchmark models:", this.benchmark.models);
                console.log("Benchmark metrics:", this.benchmark.metrics);
                console.log("Benchmark testCases:", this.benchmark.testCases);

                this.formData = {
                    name: this.benchmark.name || "",
                    modelIds: this.benchmark.models?.map((m) => m.id) || [],
                    testCaseIds:
                        this.benchmark.testCases?.map((tc) => tc.id) || [],
                    metricIds: this.benchmark.metrics?.map((m) => m.id) || [],
                };

                console.log("Form data after initialization:", this.formData);
            }
        },

        nextStep() {
            if (
                this.canProceedToNextStep &&
                this.currentStep < this.steps.length - 1
            ) {
                this.currentStep++;
            }
        },

        isModelSelected(modelId) {
            return this.formData.modelIds.includes(modelId);
        },

        toggleModel(modelId) {
            const index = this.formData.modelIds.indexOf(modelId);
            if (index > -1) {
                this.formData.modelIds.splice(index, 1);
            } else {
                this.formData.modelIds.push(modelId);
            }
        },

        isTestCaseSelected(testCaseId) {
            return this.formData.testCaseIds.includes(testCaseId);
        },

        toggleTestCase(testCaseId) {
            const index = this.formData.testCaseIds.indexOf(testCaseId);
            if (index > -1) {
                this.formData.testCaseIds.splice(index, 1);
            } else {
                this.formData.testCaseIds.push(testCaseId);
            }
        },

        isMetricSelected(metricId) {
            return this.formData.metricIds.includes(metricId);
        },

        toggleMetric(metricId) {
            const index = this.formData.metricIds.indexOf(metricId);
            if (index > -1) {
                this.formData.metricIds.splice(index, 1);
            } else {
                this.formData.metricIds.push(metricId);
            }
        },

        getMetricTypeLabel(type) {
            const typeLabels = {
                "g-eval": "G-Eval",
                dag: "DAG (Directed Acyclic Graph)",
                tale: "TALE (Tool-Augmented LLM Evaluation)",
            };
            return typeLabels[type] || type;
        },

        getPromptCount(testCase) {
            if (testCase.prompts && Array.isArray(testCase.prompts)) {
                return testCase.prompts.length;
            }
            if (testCase.promptCount !== undefined) {
                return testCase.promptCount;
            }
            return 0;
        },

        getTotalValidPrompts() {
            // This is a simplified calculation - in reality, you'd need to check each prompt
            // against the selected metrics' requirements
            return this.selectedTestCases.reduce((total, testCase) => {
                return total + this.getPromptCount(testCase);
            }, 0);
        },

        getTotalPromptsWithoutExpectedOutput() {
            // Simplified - would need actual prompt data
            return Math.floor(this.getTotalValidPrompts() * 0.1); // Assuming 10% might lack expected output
        },

        getTotalPromptsWithoutContext() {
            // Simplified - would need actual prompt data
            return Math.floor(this.getTotalValidPrompts() * 0.05); // Assuming 5% might lack context
        },

        async saveBenchmark() {
            try {
                this.benchmarksStore.clearErrors();

                const data = {
                    name: this.formData.name,
                    modelIds: this.formData.modelIds,
                    testCaseIds: this.formData.testCaseIds,
                    metricIds: this.formData.metricIds,
                };

                if (this.isEditMode) {
                    await this.benchmarksStore.updateBenchmark(
                        this.benchmark.id,
                        data
                    );
                } else {
                    await this.benchmarksStore.createBenchmark(data);
                }

                this.$emit("saved");
            } catch (error) {
                // Error is handled by the store
                console.error("Failed to save benchmark:", error);
            }
        },
    },
};
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}

.model-selection-card:hover,
.testcase-selection-card:hover,
.metric-selection-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.step-indicator {
    font-size: 0.875rem;
}
</style>
