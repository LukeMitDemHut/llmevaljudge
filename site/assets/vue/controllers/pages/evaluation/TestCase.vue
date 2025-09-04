<template>
    <div class="row">
        <div class="col-12">
            <Card>
                <h5 class="card-title">Test Case Evaluation</h5>
                <p class="text-muted">
                    Select a test case and configure which models and metrics to
                    include in the analysis.
                </p>

                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <InputDropdown
                            v-model="selectedTestCaseId"
                            :options="testCaseOptions"
                            label="Select Test Case"
                            placeholder="Choose a test case to analyze"
                            :required="true"
                            @update:modelValue="onTestCaseChange"
                        />
                    </div>
                    <div class="col-md-4">
                        <InputMultiCheckbox
                            v-model="selectedModelIds"
                            :options="modelOptions"
                            label="Select Models"
                            :allSelected="allModelsSelected"
                            @update:modelValue="onModelsChange"
                            @select-all="selectAllModels"
                            @select-none="selectNoModels"
                        />
                    </div>
                    <div class="col-md-4">
                        <InputMultiCheckbox
                            v-model="selectedMetricIds"
                            :options="metricOptions"
                            label="Select Metrics"
                            :allSelected="allMetricsSelected"
                            @update:modelValue="onMetricsChange"
                            @select-all="selectAllMetrics"
                            @select-none="selectNoMetrics"
                        />
                    </div>
                </div>

                <!-- Load Data Button -->
                <div class="mb-4">
                    <Button
                        variant="primary"
                        @click="loadTestCaseAnalysis"
                        :disabled="!selectedTestCaseId || resultsStore.loading"
                    >
                        <span
                            v-if="resultsStore.loading"
                            class="spinner-border spinner-border-sm me-2"
                        ></span>
                        {{
                            resultsStore.loading
                                ? "Loading..."
                                : "Analyze Test Case"
                        }}
                    </Button>
                </div>

                <!-- Error Display -->
                <Alert
                    v-if="resultsStore.hasErrors"
                    variant="danger"
                    :message="getErrorMessage()"
                />

                <!-- Results Display -->
                <div
                    v-if="evaluationData && !resultsStore.loading"
                    id="printable-testcase"
                >
                    <hr />
                    <div
                        class="d-flex justify-content-between align-items-center mb-3"
                    >
                        <h6 class="mb-0">Analysis Results</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <span
                                class="badge bg-secondary"
                                v-if="evaluationData.deduplication"
                            >
                                <i class="ph ph-copy me-1"></i>
                                {{
                                    getDeduplicationLabel(
                                        evaluationData.deduplication
                                    )
                                }}
                            </span>
                            <span
                                class="badge bg-info"
                                v-if="evaluationData.benchmarkScope"
                            >
                                <i class="ph ph-chart-line-up me-1"></i>
                                Benchmark Scope
                            </span>
                        </div>
                    </div>

                    <!-- Overall Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-primary mb-0">
                                    {{
                                        evaluationData.overall?.averageScore ||
                                        0
                                    }}
                                </div>
                                <small class="text-muted">Average Score</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-success mb-0">
                                    {{
                                        evaluationData.overall?.totalTests || 0
                                    }}
                                </div>
                                <small class="text-muted">Total Tests</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-info mb-0">
                                    {{
                                        evaluationData.modelMetrics?.length || 0
                                    }}
                                </div>
                                <small class="text-muted"
                                    >Model-Metric Combinations</small
                                >
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-warning mb-0">
                                    {{ evaluationData.testCase?.name || "N/A" }}
                                </div>
                                <small class="text-muted">Test Case</small>
                            </div>
                        </div>
                    </div>

                    <!-- Model-Metric Performance -->
                    <div class="row">
                        <div class="col-12">
                            <h6>Model-Metric Performance Heatmap</h6>
                            <div class="mb-4">
                                <Chart
                                    v-if="evaluationData.modelMetrics?.length"
                                    type="bar"
                                    :data="heatmapChartData"
                                    :options="{
                                        responsive: true,
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    title: function (context) {
                                                        return context[0].label;
                                                    },
                                                    label: function (context) {
                                                        return `Score: ${context.parsed.y.toFixed(
                                                            3
                                                        )}`;
                                                    },
                                                },
                                            },
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                max: 1,
                                                ticks: {
                                                    stepSize: 0.2,
                                                },
                                                title: {
                                                    display: true,
                                                    text: 'Performance Score',
                                                },
                                            },
                                            x: {
                                                title: {
                                                    display: true,
                                                    text: 'Model-Metric Combinations',
                                                },
                                                ticks: {
                                                    maxRotation: 45,
                                                    minRotation: 45,
                                                },
                                            },
                                        },
                                    }"
                                    :height="400"
                                    :responsive="true"
                                    :maintain-aspect-ratio="true"
                                />
                                <div v-else class="text-center text-muted py-4">
                                    <em
                                        >No test case data available for chart
                                        visualization</em
                                    >
                                </div>
                            </div>

                            <!-- Performance Stability Analysis -->
                            <h6 class="mt-4">
                                Performance Variability Analysis
                            </h6>
                            <div class="mb-4">
                                <Chart
                                    v-if="evaluationData.modelMetrics?.length"
                                    type="boxplot"
                                    :data="boxplotChartData"
                                    :options="{
                                        responsive: true,
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function (context) {
                                                        const data =
                                                            context.parsed;
                                                        return [
                                                            `Min: ${data.min?.toFixed(
                                                                3
                                                            )}`,
                                                            `Q1: ${data.q1?.toFixed(
                                                                3
                                                            )}`,
                                                            `Median: ${data.median?.toFixed(
                                                                3
                                                            )}`,
                                                            `Q3: ${data.q3?.toFixed(
                                                                3
                                                            )}`,
                                                            `Max: ${data.max?.toFixed(
                                                                3
                                                            )}`,
                                                        ];
                                                    },
                                                },
                                            },
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                max: 1,
                                                ticks: {
                                                    stepSize: 0.2,
                                                },
                                                title: {
                                                    display: true,
                                                    text: 'Score Distribution',
                                                },
                                            },
                                            x: {
                                                title: {
                                                    display: true,
                                                    text: 'Model-Metric Combinations',
                                                },
                                                ticks: {
                                                    maxRotation: 45,
                                                    minRotation: 45,
                                                },
                                            },
                                        },
                                    }"
                                    :height="300"
                                    :responsive="true"
                                    :maintain-aspect-ratio="true"
                                />
                                <div v-else class="text-center text-muted py-4">
                                    <em
                                        >No test case data available for
                                        variability analysis</em
                                    >
                                </div>
                            </div>

                            <!-- Detailed Results Table -->
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Model</th>
                                            <th>Metric</th>
                                            <th>Score</th>
                                            <th>Actual Output</th>
                                            <th>Expected Output</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="result in evaluationData.results ||
                                            []"
                                            :key="`${result.model?.name}-${result.metric?.name}`"
                                        >
                                            <td>{{ result.model?.name }}</td>
                                            <td>{{ result.metric?.name }}</td>
                                            <td>
                                                <span
                                                    class="badge"
                                                    :class="
                                                        getScoreBadgeClass(
                                                            result.score
                                                        )
                                                    "
                                                    >{{
                                                        result.score?.toFixed(3)
                                                    }}</span
                                                >
                                            </td>
                                            <td>
                                                <span
                                                    class="text-truncate d-inline-block"
                                                    style="max-width: 200px"
                                                    :title="result.actualOutput"
                                                >
                                                    {{ result.actualOutput }}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    class="text-truncate d-inline-block"
                                                    style="max-width: 200px"
                                                    :title="
                                                        result.prompt
                                                            ?.expectedOutput
                                                    "
                                                >
                                                    {{
                                                        result.prompt
                                                            ?.expectedOutput
                                                    }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Results Message -->
                <div v-else-if="!resultsStore.loading && !evaluationData">
                    <EmptyState
                        title="No Analysis Data"
                        description="Click 'Analyze Test Case' to load evaluation data for the selected configuration."
                        icon="chart-line"
                    />
                </div>
            </Card>
        </div>
    </div>
</template>

<script>
import { useResultsStore } from "@stores/ResultsStore.js";
import Card from "@components/structure/Card.vue";
import InputDropdown from "@components/interactables/inputs/InputDropdown.vue";
import InputMultiCheckbox from "@components/interactables/inputs/InputMultiCheckbox.vue";
import Button from "@components/interactables/Button.vue";
import Alert from "@components/interactables/Alert.vue";
import EmptyState from "@components/structure/EmptyState.vue";
import Chart from "@components/charts/Chart.vue";

export default {
    name: "TestCaseEvaluation",
    components: {
        Card,
        InputDropdown,
        InputMultiCheckbox,
        Button,
        Alert,
        EmptyState,
        Chart,
    },
    data() {
        return {
            selectedTestCaseId: null,
            selectedModelIds: [],
            selectedMetricIds: [],
        };
    },
    computed: {
        resultsStore() {
            return useResultsStore();
        },
        evaluationData() {
            return this.resultsStore.evaluationData;
        },
        testCaseOptions() {
            return (this.resultsStore.testCases || []).map((testCase) => ({
                value: testCase.id,
                label: testCase.name,
            }));
        },
        modelOptions() {
            return (this.resultsStore.models || []).map((model) => ({
                value: model.id,
                label: model.name,
            }));
        },
        metricOptions() {
            return (this.resultsStore.metrics || []).map((metric) => ({
                value: metric.id,
                label: metric.name,
            }));
        },
        allModelsSelected() {
            return (
                this.selectedModelIds.length ===
                (this.resultsStore.models?.length || 0)
            );
        },
        allMetricsSelected() {
            return (
                this.selectedMetricIds.length ===
                (this.resultsStore.metrics?.length || 0)
            );
        },
        heatmapChartData() {
            if (!this.evaluationData?.modelMetrics?.length) {
                return {
                    labels: [],
                    datasets: [],
                };
            }

            const modelMetrics = this.evaluationData.modelMetrics || [];
            const labels = modelMetrics.map(
                (item) => `${item.modelName} - ${item.metricName}`
            );
            const scores = modelMetrics.map((item) => item.score);

            // Color coding based on score ranges
            const backgroundColors = scores.map((score) => {
                if (score >= 0.8) return "rgba(75, 192, 192, 0.6)"; // Green for high scores
                if (score >= 0.6) return "rgba(54, 162, 235, 0.6)"; // Blue for medium scores
                if (score >= 0.4) return "rgba(255, 206, 86, 0.6)"; // Yellow for low-medium scores
                return "rgba(255, 99, 132, 0.6)"; // Red for low scores
            });

            const borderColors = backgroundColors.map((color) =>
                color.replace("0.6", "1")
            );

            return {
                labels,
                datasets: [
                    {
                        label: "Performance Score",
                        data: scores,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 1,
                    },
                ],
            };
        },
        boxplotChartData() {
            if (!this.evaluationData?.modelMetrics?.length) {
                return {
                    labels: [],
                    datasets: [],
                };
            }

            const modelMetrics = this.evaluationData.modelMetrics || [];
            const labels = modelMetrics.map(
                (item) => `${item.modelName} - ${item.metricName}`
            );

            // For test case analysis, we might not have multiple scores per combination
            // So we'll use the single score as all quartile values
            const boxplotData = modelMetrics.map((item) => {
                const score = item.score;
                return {
                    min: score,
                    q1: score,
                    median: score,
                    q3: score,
                    max: score,
                    outliers: [],
                };
            });

            return {
                labels,
                datasets: [
                    {
                        label: "Score Distribution",
                        data: boxplotData,
                        backgroundColor: "rgba(54, 162, 235, 0.3)",
                        borderColor: "rgba(54, 162, 235, 1)",
                        borderWidth: 1,
                        outlierColor: "rgba(255, 99, 132, 0.8)",
                        medianColor: "rgba(255, 159, 64, 1)",
                    },
                ],
            };
        },
    },
    mounted() {
        // Initialize with all models and metrics selected by default
        this.selectedModelIds = this.modelOptions.map((option) => option.value);
        this.selectedMetricIds = this.metricOptions.map(
            (option) => option.value
        );
    },
    methods: {
        onTestCaseChange() {
            this.resultsStore.clearEvaluationData();
        },
        onModelsChange() {
            this.resultsStore.clearEvaluationData();
        },
        onMetricsChange() {
            this.resultsStore.clearEvaluationData();
        },
        selectAllModels() {
            this.selectedModelIds = this.modelOptions.map(
                (option) => option.value
            );
            this.resultsStore.clearEvaluationData();
        },
        selectNoModels() {
            this.selectedModelIds = [];
            this.resultsStore.clearEvaluationData();
        },
        selectAllMetrics() {
            this.selectedMetricIds = this.metricOptions.map(
                (option) => option.value
            );
            this.resultsStore.clearEvaluationData();
        },
        selectNoMetrics() {
            this.selectedMetricIds = [];
            this.resultsStore.clearEvaluationData();
        },
        async loadTestCaseAnalysis() {
            if (!this.selectedTestCaseId) {
                return;
            }

            try {
                await this.resultsStore.loadTestCaseAnalysis(
                    this.selectedTestCaseId,
                    this.selectedModelIds,
                    this.selectedMetricIds
                );
            } catch (error) {
                console.error("Failed to load test case analysis:", error);
            }
        },
        getErrorMessage() {
            if (this.resultsStore.errors.general) {
                return this.resultsStore.errors.general;
            }

            const errorMessages = Object.entries(this.resultsStore.errors)
                .map(
                    ([field, error]) =>
                        `${field}: ${
                            Array.isArray(error) ? error.join(", ") : error
                        }`
                )
                .join("; ");

            return errorMessages || "An error occurred";
        },
        getDeduplicationLabel(strategy) {
            const labels = {
                latest: "Latest Results",
                all: "All Results",
                average: "Averaged Results",
            };
            return labels[strategy] || strategy;
        },
        getScoreBadgeClass(score) {
            if (score >= 0.8) return "bg-success";
            if (score >= 0.6) return "bg-primary";
            if (score >= 0.4) return "bg-warning";
            return "bg-danger";
        },
    },
};
</script>
