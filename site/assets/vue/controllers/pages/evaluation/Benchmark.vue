<template>
    <div class="row">
        <div class="col-12">
            <Card>
                <h5 class="card-title">Benchmark Evaluation</h5>
                <p class="text-muted">
                    Select a benchmark and configure which models to compare in
                    the analysis.
                </p>

                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <InputDropdown
                            v-model="selectedBenchmarkId"
                            :options="benchmarkOptions"
                            label="Select Benchmark"
                            placeholder="Choose a benchmark to analyze"
                            :required="true"
                            @update:modelValue="onBenchmarkChange"
                        />
                    </div>
                    <div class="col-md-6">
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
                </div>

                <!-- Load Data Button -->
                <div class="mb-4">
                    <Button
                        variant="primary"
                        @click="loadBenchmarkAnalysis"
                        :disabled="!selectedBenchmarkId || resultsStore.loading"
                    >
                        <span
                            v-if="resultsStore.loading"
                            class="spinner-border spinner-border-sm me-2"
                        ></span>
                        {{
                            resultsStore.loading
                                ? "Loading..."
                                : "Analyze Benchmark"
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
                    id="printable"
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
                                    {{ evaluationData.models?.length || 0 }}
                                </div>
                                <small class="text-muted"
                                    >Models Analyzed</small
                                >
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-warning mb-0">
                                    {{
                                        evaluationData.benchmark?.name || "N/A"
                                    }}
                                </div>
                                <small class="text-muted">Benchmark</small>
                            </div>
                        </div>
                    </div>

                    <!-- Model Performance Comparison -->
                    <div class="row">
                        <div class="col-12">
                            <h6>Model Performance Comparison</h6>
                            <div class="mb-4">
                                <Chart
                                    v-if="evaluationData.models?.length"
                                    type="bar"
                                    :data="barChartData"
                                    :options="{
                                        responsive: true,
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
                                                        }: ${context.parsed.y.toFixed(
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
                                                grid: {
                                                    color: 'rgba(0, 0, 0, 0.1)',
                                                },
                                                title: {
                                                    display: true,
                                                    text: 'Score',
                                                },
                                            },
                                            x: {
                                                grid: {
                                                    display: false,
                                                },
                                                title: {
                                                    display: true,
                                                    text: 'Models',
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
                                        >No model data available for chart
                                        visualization</em
                                    >
                                </div>
                            </div>

                            <!-- Model Stability Analysis with Boxplot -->
                            <h6 class="mt-4">Model Stability Analysis</h6>
                            <div class="mb-4">
                                <Chart
                                    v-if="evaluationData.models?.length"
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
                                                    text: 'Models',
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
                                        >No model data available for stability
                                        analysis</em
                                    >
                                </div>
                            </div>

                            <!-- Model Performance Table -->
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Model</th>
                                            <th>Average Score</th>
                                            <th>Min Score</th>
                                            <th>Max Score</th>
                                            <th>Test Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="model in evaluationData.models ||
                                            []"
                                            :key="model.name"
                                        >
                                            <td>{{ model.name }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-primary"
                                                    >{{ model.average }}</span
                                                >
                                            </td>
                                            <td>{{ model.min }}</td>
                                            <td>{{ model.max }}</td>
                                            <td>{{ model.count }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Metric Performance Overview -->
                            <h6 class="mt-4">Metric Performance Overview</h6>
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

                            <!-- Metric Performance Table -->
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
                                                    class="badge bg-secondary"
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
                        description="Click 'Analyze Benchmark' to load evaluation data for the selected configuration."
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
    name: "BenchmarkEvaluation",
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
            selectedBenchmarkId: null,
            selectedModelIds: [],
        };
    },
    computed: {
        resultsStore() {
            return useResultsStore();
        },
        evaluationData() {
            return this.resultsStore.evaluationData;
        },
        benchmarkOptions() {
            return (this.resultsStore.benchmarks || []).map((benchmark) => ({
                value: benchmark.id,
                label: benchmark.name,
            }));
        },
        modelOptions() {
            return (this.resultsStore.models || []).map((model) => ({
                value: model.id,
                label: model.name,
            }));
        },
        allModelsSelected() {
            return (
                this.selectedModelIds.length ===
                (this.resultsStore.models?.length || 0)
            );
        },
        barChartData() {
            if (!this.evaluationData?.models?.length) {
                return {
                    labels: [],
                    datasets: [],
                };
            }

            const models = this.evaluationData.models || [];
            const labels = models.map((model) => model.name);
            const averageScores = models.map((model) => model.average);
            const maxScores = models.map((model) => model.max);
            const minScores = models.map((model) => model.min);

            return {
                labels,
                datasets: [
                    {
                        label: "Average Score",
                        data: averageScores,
                        backgroundColor: "rgba(54, 162, 235, 0.6)",
                        borderColor: "rgba(54, 162, 235, 1)",
                        borderWidth: 1,
                    },
                    {
                        label: "Max Score",
                        data: maxScores,
                        backgroundColor: "rgba(75, 192, 192, 0.6)",
                        borderColor: "rgba(75, 192, 192, 1)",
                        borderWidth: 1,
                    },
                    {
                        label: "Min Score",
                        data: minScores,
                        backgroundColor: "rgba(255, 99, 132, 0.6)",
                        borderColor: "rgba(255, 99, 132, 1)",
                        borderWidth: 1,
                    },
                ],
            };
        },
        boxplotChartData() {
            if (!this.evaluationData?.models?.length) {
                return {
                    labels: [],
                    datasets: [],
                };
            }

            const models = this.evaluationData.models || [];
            const labels = models.map((model) => model.name);
            const boxplotData = models.map((model) => {
                const scores = model.scores || [];
                if (scores.length === 0) {
                    return {
                        min: model.min,
                        q1: model.min,
                        median: model.average,
                        q3: model.max,
                        max: model.max,
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
        radarChartData() {
            if (!this.evaluationData?.metrics?.length) {
                return {
                    labels: [],
                    datasets: [],
                };
            }

            const metrics = this.evaluationData.metrics || [];
            const labels = metrics.map((metric) => metric.name);
            const averageScores = metrics.map((metric) => metric.average);

            return {
                labels,
                datasets: [
                    {
                        label: "Average Score Across All Models",
                        data: averageScores,
                        borderColor: "rgb(255, 99, 132)",
                        backgroundColor: "rgba(255, 99, 132, 0.2)",
                        borderWidth: 2,
                        pointBackgroundColor: "rgb(255, 99, 132)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgb(255, 99, 132)",
                    },
                ],
            };
        },
    },
    mounted() {
        // Initialize with all models selected by default
        this.selectedModelIds = this.modelOptions.map((option) => option.value);
    },
    methods: {
        onBenchmarkChange() {
            this.resultsStore.clearEvaluationData();
        },
        onModelsChange() {
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
        async loadBenchmarkAnalysis() {
            if (!this.selectedBenchmarkId) {
                return;
            }

            try {
                await this.resultsStore.loadBenchmarkAnalysis(
                    this.selectedBenchmarkId,
                    this.selectedModelIds
                );
            } catch (error) {
                console.error("Failed to load benchmark analysis:", error);
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
