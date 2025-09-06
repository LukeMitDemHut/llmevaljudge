<template>
    <div class="row">
        <div class="col-12">
            <Card>
                <h5 class="card-title">Model Evaluation</h5>
                <p class="text-muted">
                    Select a model and configure which metrics and test cases to
                    include in the analysis.
                </p>

                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <InputDropdown
                            v-model="selectedModelId"
                            :options="modelOptions"
                            label="Select Model"
                            placeholder="Choose a model to analyze"
                            :required="true"
                            @update:modelValue="onModelChange"
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
                    <div class="col-md-4">
                        <InputMultiCheckbox
                            v-model="selectedTestCaseIds"
                            :options="testCaseOptions"
                            label="Select Test Cases"
                            :allSelected="allTestCasesSelected"
                            @update:modelValue="onTestCasesChange"
                            @select-all="selectAllTestCases"
                            @select-none="selectNoTestCases"
                        />
                    </div>
                </div>

                <!-- Load Data Button -->
                <div class="mb-4">
                    <Button
                        variant="primary"
                        @click="loadModelAnalysis"
                        :disabled="!selectedModelId || resultsStore.loading"
                    >
                        <span
                            v-if="resultsStore.loading"
                            class="spinner-border spinner-border-sm me-2"
                        ></span>
                        {{
                            resultsStore.loading
                                ? "Loading..."
                                : "Analyze Model"
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
                    id="printable-model"
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
                                    {{ evaluationData.metrics?.length || 0 }}
                                </div>
                                <small class="text-muted"
                                    >Metrics Analyzed</small
                                >
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-warning mb-0">
                                    {{ evaluationData.model?.name || "N/A" }}
                                </div>
                                <small class="text-muted">Model</small>
                            </div>
                        </div>
                    </div>

                    <!-- Metric Breakdown -->
                    <div class="row">
                        <div class="col-12">
                            <h6>Metric Performance</h6>
                            <div class="mb-4">
                                <Chart
                                    v-if="evaluationData.metrics?.length"
                                    type="radar"
                                    :data="radarChartData"
                                    :options="{
                                        scales: {
                                            r: {
                                                beginAtZero: true,
                                                max: 1,
                                                ticks: {
                                                    stepSize: 0.2,
                                                },
                                                grid: {
                                                    color: 'rgba(0, 0, 0, 0.1)',
                                                },
                                                angleLines: {
                                                    color: 'rgba(0, 0, 0, 0.1)',
                                                },
                                                pointLabels: {
                                                    font: {
                                                        size: 12,
                                                    },
                                                },
                                            },
                                        },
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function (context) {
                                                        return `${
                                                            context.dataset
                                                                .label
                                                        }: ${context.parsed.r.toFixed(
                                                            3
                                                        )}`;
                                                    },
                                                },
                                            },
                                        },
                                        elements: {
                                            line: {
                                                borderWidth: 2,
                                            },
                                            point: {
                                                radius: 4,
                                                hoverRadius: 6,
                                            },
                                        },
                                    }"
                                    :height="300"
                                    :width="400"
                                    :responsive="true"
                                    :maintain-aspect-ratio="true"
                                />
                                <div v-else class="text-center text-muted py-4">
                                    <em
                                        >No metric data available for chart
                                        visualization</em
                                    >
                                </div>
                            </div>

                            <!-- Stability Analysis with Boxplot -->
                            <h6 class="mt-4">Metric Stability Analysis</h6>
                            <div class="mb-4">
                                <Chart
                                    v-if="evaluationData.metrics?.length"
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
                                                    text: 'Metrics',
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
                                        >No metric data available for stability
                                        analysis</em
                                    >
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Metric</th>
                                            <th>Average Score</th>
                                            <th>Min Score</th>
                                            <th>Max Score</th>
                                            <th>Test Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="metric in evaluationData.metrics ||
                                            []"
                                            :key="metric.name"
                                        >
                                            <td>{{ metric.name }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-primary"
                                                    >{{ metric.average }}</span
                                                >
                                            </td>
                                            <td>{{ metric.min }}</td>
                                            <td>{{ metric.max }}</td>
                                            <td>{{ metric.count }}</td>
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
                        description="Click 'Analyze Model' to load evaluation data for the selected configuration."
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
import html2pdf from "html2pdf.js";

export default {
    name: "ModelEvaluation",
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
            selectedModelId: null,
            selectedMetricIds: [],
            selectedTestCaseIds: [],
        };
    },
    computed: {
        resultsStore() {
            return useResultsStore();
        },
        evaluationData() {
            return this.resultsStore.evaluationData;
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
        testCaseOptions() {
            return (this.resultsStore.testCases || []).map((testCase) => ({
                value: testCase.id,
                label: testCase.name,
            }));
        },
        allMetricsSelected() {
            return (
                this.selectedMetricIds.length ===
                (this.resultsStore.metrics?.length || 0)
            );
        },
        allTestCasesSelected() {
            return (
                this.selectedTestCaseIds.length ===
                (this.resultsStore.testCases?.length || 0)
            );
        },
        radarChartData() {
            if (!this.evaluationData?.metrics?.length) {
                return {
                    labels: [],
                    datasets: [],
                };
            }

            const metrics = this.evaluationData.metrics || [];
            const labels = metrics.map((metric) => metric.name);
            const scores = metrics.map((metric) => metric.average);
            const maxScores = metrics.map((metric) => metric.max);
            const minScores = metrics.map((metric) => metric.min);

            return {
                labels,
                datasets: [
                    {
                        label: "Average Score",
                        data: scores,
                        borderColor: "rgb(54, 162, 235)",
                        backgroundColor: "rgba(54, 162, 235, 0.2)",
                        borderWidth: 2,
                        pointBackgroundColor: "rgb(54, 162, 235)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgb(54, 162, 235)",
                    },
                    {
                        label: "Max Score",
                        data: maxScores,
                        borderColor: "rgb(75, 192, 192)",
                        backgroundColor: "rgba(75, 192, 192, 0.1)",
                        borderWidth: 1,
                        borderDash: [5, 5],
                        pointBackgroundColor: "rgb(75, 192, 192)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgb(75, 192, 192)",
                    },
                    {
                        label: "Min Score",
                        data: minScores,
                        borderColor: "rgb(255, 99, 132)",
                        backgroundColor: "rgba(255, 99, 132, 0.1)",
                        borderWidth: 1,
                        borderDash: [5, 5],
                        pointBackgroundColor: "rgb(255, 99, 132)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgb(255, 99, 132)",
                    },
                ],
            };
        },
        boxplotChartData() {
            if (!this.evaluationData?.metrics?.length) {
                return {
                    labels: [],
                    datasets: [],
                };
            }

            const metrics = this.evaluationData.metrics || [];
            const labels = metrics.map((metric) => metric.name);
            const boxplotData = metrics.map((metric) => {
                const scores = metric.scores || [];
                if (scores.length === 0) {
                    return {
                        min: metric.min,
                        q1: metric.min,
                        median: metric.average,
                        q3: metric.max,
                        max: metric.max,
                        outliers: [],
                    };
                }

                // Calculate quartiles for boxplot
                const sorted = [...scores].sort((a, b) => a - b);
                const q1Index = Math.floor(sorted.length * 0.25);
                const medianIndex = Math.floor(sorted.length * 0.5);
                const q3Index = Math.floor(sorted.length * 0.75);

                return {
                    min: sorted[0],
                    q1: sorted[q1Index],
                    median: sorted[medianIndex],
                    q3: sorted[q3Index],
                    max: sorted[sorted.length - 1],
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
        // Initialize with all test cases selected by default
        this.selectedTestCaseIds = this.testCaseOptions.map(
            (option) => option.value
        );
    },
    methods: {
        onModelChange() {
            this.resultsStore.clearEvaluationData();
        },
        onMetricsChange() {
            this.resultsStore.clearEvaluationData();
        },
        onTestCasesChange() {
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
        selectAllTestCases() {
            this.selectedTestCaseIds = this.testCaseOptions.map(
                (option) => option.value
            );
            this.resultsStore.clearEvaluationData();
        },
        selectNoTestCases() {
            this.selectedTestCaseIds = [];
            this.resultsStore.clearEvaluationData();
        },
        async loadModelAnalysis() {
            if (!this.selectedModelId) {
                return;
            }

            try {
                await this.resultsStore.loadModelAnalysis(
                    this.selectedModelId,
                    this.selectedMetricIds,
                    this.selectedTestCaseIds
                );
            } catch (error) {
                console.error("Failed to load model analysis:", error);
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
    },
};
</script>
