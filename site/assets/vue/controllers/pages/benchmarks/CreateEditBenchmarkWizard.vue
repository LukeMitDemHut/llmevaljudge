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
                    <div class="col-12">
                        <div class="mb-3">
                            <label for="repeatCount" class="form-label"
                                >Evaluation Repeats</label
                            >
                            <input
                                id="repeatCount"
                                v-model.number="formData.repeatCount"
                                type="number"
                                class="form-control"
                                min="1"
                                max="20"
                                placeholder="1"
                            />
                            <div class="form-text">
                                Number of independent judgements per
                                combination. Higher values yield more consistent
                                results through averaging. Default: 1
                            </div>
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

            <!-- Step 3: Select Metrics -->
            <div v-show="currentStep === 2">
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

            <!-- Step 4: Review & Warnings -->
            <div v-show="currentStep === 3">
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
                                Test Cases ({{ effectiveTestCaseIds.length }})
                            </h6>
                            <p
                                v-if="hasUnscopedMetric"
                                class="small text-muted mb-2"
                            >
                                Includes all test cases for metrics without an
                                explicit assignment.
                            </p>
                            <ul class="list-unstyled mb-0 small">
                                <li
                                    v-for="testCase in effectiveTestCases"
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
                            <div class="d-flex justify-content-between mb-1">
                                <span>Evaluation Repeats:</span>
                                <span class="fw-bold"
                                    >{{ formData.repeatCount || 1 }}×</span
                                >
                            </div>
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
                { key: "metrics", title: "Metrics" },
                { key: "review", title: "Review" },
            ],
            formData: {
                name: "",
                repeatCount: 1,
                modelIds: [],
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
                this.formData.modelIds.includes(m.id),
            );
        },

        selectedTestCasesAll() {
            // All test cases known to the app; used as the "empty = all" fallback
            // for metrics without an explicit test-case assignment.
            return this.benchmarksStore.testCases || [];
        },

        /**
         * Union of test-case IDs over the selected metrics' explicit
         * assignments (their testCaseIds). Metrics with an empty assignment
         * contribute "all test cases" on top of the union.
         */
        effectiveTestCaseIds() {
            const allIds = this.selectedTestCasesAll.map((tc) => tc.id);
            const union = new Set();
            let hasImplicitAll = false;

            this.selectedMetrics.forEach((metric) => {
                const assigned = (metric.testCaseIds || []).filter((id) =>
                    allIds.includes(id),
                );
                if (assigned.length > 0) {
                    assigned.forEach((id) => union.add(id));
                } else {
                    hasImplicitAll = true;
                }
            });

            if (hasImplicitAll) {
                allIds.forEach((id) => union.add(id));
            }

            return allIds.filter((id) => union.has(id));
        },

        effectiveTestCases() {
            const ids = this.effectiveTestCaseIds;
            return this.selectedTestCasesAll.filter((tc) =>
                ids.includes(tc.id),
            );
        },

        hasUnscopedMetric() {
            return this.selectedMetrics.some(
                (metric) => (metric.testCaseIds || []).length === 0,
            );
        },

        selectedMetrics() {
            return (this.benchmarksStore.metrics || []).filter((m) =>
                this.formData.metricIds.includes(m.id),
            );
        },

        canProceedToNextStep() {
            switch (this.currentStep) {
                case 0:
                    return this.formData.name.trim().length > 0;
                case 1:
                    return this.formData.modelIds.length > 0;
                case 2:
                    return this.formData.metricIds.length > 0;
                default:
                    return true;
            }
        },

        canSave() {
            return (
                this.formData.name.trim().length > 0 &&
                this.formData.modelIds.length > 0 &&
                this.formData.metricIds.length > 0
            );
        },

        warnings() {
            const warnings = [];

            // Count (prompt, metric) pairs that will be skipped because the
            // prompt lacks params the metric requires (real pairing data,
            // mirroring MetricTestCaseResolver::pairSatisfiesParams).
            const requiredParamsByMetric = new Map();
            this.selectedMetrics.forEach((metric) => {
                const params = new Set(metric.param || []);
                requiredParamsByMetric.set(metric.id, params);
            });

            let skippedPairsWithoutExpectedOutput = 0;
            let skippedPairsWithoutContext = 0;

            this.selectedMetrics.forEach((metric) => {
                const params = requiredParamsByMetric.get(metric.id);
                const scopedTestCaseIds = metric.testCaseIds || [];
                const scopedTestCases =
                    scopedTestCaseIds.length > 0
                        ? this.selectedTestCasesAll.filter((tc) =>
                              scopedTestCaseIds.includes(tc.id),
                          )
                        : this.selectedTestCasesAll;

                scopedTestCases.forEach((testCase) => {
                    (testCase.prompts || []).forEach((prompt) => {
                        if (
                            params.has("expected_output") &&
                            !prompt.expectedOutput
                        ) {
                            skippedPairsWithoutExpectedOutput++;
                        }
                        if (params.has("context") && !prompt.context) {
                            skippedPairsWithoutContext++;
                        }
                    });
                });
            });

            if (skippedPairsWithoutExpectedOutput > 0) {
                warnings.push(
                    `${skippedPairsWithoutExpectedOutput} prompt/metric evaluations will be skipped because the prompts lack expected output required by selected metrics.`,
                );
            }

            if (skippedPairsWithoutContext > 0) {
                warnings.push(
                    `${skippedPairsWithoutContext} prompt/metric evaluations will be skipped because the prompts lack context required by selected metrics.`,
                );
            }

            return warnings;
        },

        /**
         * Total evaluations = sum over selected metrics of
         * (pairable prompts in the metric's effective test cases
         * x models x repeats). Mirrors the metric-outer dispatch order
         * of RunBenchmarkHandler / EvaluatePromptHandler.
         */
        totalEvaluations() {
            const repeatCount = this.formData.repeatCount || 1;
            const modelCount = this.selectedModels.length;

            const totalPairablePrompts = this.selectedMetrics.reduce(
                (metricSum, metric) =>
                    metricSum +
                    this.getPairablePromptsForMetric(metric).length,
                0,
            );

            return totalPairablePrompts * modelCount * repeatCount;
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

                this.formData = {
                    name: this.benchmark.name || "",
                    repeatCount: this.benchmark.repeatCount || 1,
                    modelIds: this.benchmark.models?.map((m) => m.id) || [],
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
            /**
             * Prompts across the effective (unioned) test cases that can
             * actually run: a (prompt, metric) pair runs only when the metric's
             * params are satisfied by the prompt (empty = all applies per
             * metric). Falls back to all prompts of the effective test cases
             * when no metric/params are known.
             */
            if (this.selectedMetrics.length === 0) {
                return this.effectiveTestCases.reduce(
                    (total, testCase) => total + this.getPromptCount(testCase),
                    0,
                );
            }

            const prompts = this.selectedMetrics.reduce(
                (all, metric) =>
                    all.concat(this.getPairablePromptsForMetric(metric)),
                [],
            );
            return prompts.length;
        },

        /**
         * Effective test cases for one metric: its explicit testCaseIds, or
         * ALL known test cases when the assignment is empty (empty = all).
         * Mirrors MetricTestCaseResolver::getEffectiveTestCasesForMetric.
         */
        getEffectiveTestCasesForMetric(metric) {
            const scopedTestCaseIds = metric.testCaseIds || [];

            if (scopedTestCaseIds.length === 0) {
                return this.selectedTestCasesAll;
            }

            return this.selectedTestCasesAll.filter((tc) =>
                scopedTestCaseIds.includes(tc.id),
            );
        },

        /**
         * Pairable (prompt, metric) pairs for one metric: the prompts of the
         * metric's effective test cases that satisfy the metric's params —
         * mirroring MetricTestCaseResolver::pairSatisfiesParams. The metric's
         * own testCaseIds (not the overall union) decide the scope here,
         * because empty = all applies per metric.
         */
        getPairablePromptsForMetric(metric) {
            const params = metric.param || [];
            return this.getEffectiveTestCasesForMetric(metric).reduce(
                (pairable, testCase) =>
                    pairable.concat(
                        (testCase.prompts || []).filter((prompt) =>
                            params.every((param) => {
                                switch (param) {
                                    case "input":
                                        return !!prompt.input;
                                    case "expected_output":
                                        return !!prompt.expectedOutput;
                                    case "context":
                                        return !!prompt.context;
                                    default:
                                        return true; // actual_output is generated during execution
                                }
                            }),
                        ),
                    ),
                [],
            );
        },

        async saveBenchmark() {
            try {
                this.benchmarksStore.clearErrors();

                const data = {
                    name: this.formData.name,
                    repeatCount: this.formData.repeatCount,
                    modelIds: this.formData.modelIds,
                    metricIds: this.formData.metricIds,
                };

                if (this.isEditMode) {
                    await this.benchmarksStore.updateBenchmark(
                        this.benchmark.id,
                        data,
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
