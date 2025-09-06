<template>
    <div class="evaluation-results">
        <div class="results-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">
                        <Icon name="chart-bar" class="me-2" />
                        Evaluation Results
                    </h4>
                    <p class="text-muted mb-0">
                        Grouped by: <strong>{{ groupLabel }}</strong> |
                        Deduplication: <strong>{{ dedupeStrategy }}</strong>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <Button
                        variant="outline-secondary"
                        @click="printResults"
                        :disabled="!results.length"
                    >
                        <Icon name="printer" class="me-1" />
                        Print PDF
                    </Button>
                    <Button variant="secondary" @click="$emit('reconfigure')">
                        <Icon name="gear" class="me-1" />
                        Reconfigure
                    </Button>
                </div>
            </div>
        </div>

        <!-- Main Results Content -->

        <!-- Loading state -->
        <div v-if="loading" class="loading-container">
            <div class="text-center py-5">
                <div class="spinner-large mb-3"></div>
                <h5>Loading Evaluation Data...</h5>
                <p class="text-muted">
                    {{
                        loadingMessage ||
                        "Fetching and processing evaluation results..."
                    }}
                </p>
                <div v-if="progress" class="progress-info">
                    <small class="text-muted">
                        Page {{ progress.currentPage }} |
                        {{ progress.fetchedItems }} items loaded
                    </small>
                </div>
            </div>
        </div>

        <!-- Results content -->
        <div v-else-if="results.length > 0" class="results-content">
            <!-- Add the printable content wrapper around all the results -->
            <div id="printable-content">
                <!-- Summary Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <Icon name="database" />
                            </div>
                            <div class="stat-content">
                                <div class="stat-number">
                                    {{ results.length }}
                                </div>
                                <div class="stat-label">{{ groupLabel }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <Icon name="target" />
                            </div>
                            <div class="stat-content">
                                <div class="stat-number">
                                    {{ totalDataPoints }}
                                </div>
                                <div class="stat-label">Total Data Points</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <Icon name="calculator" />
                            </div>
                            <div class="stat-content">
                                <div class="stat-number">
                                    {{ overallAverage.toFixed(2) }}
                                </div>
                                <div class="stat-label">Overall Average</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <Icon name="medal" />
                            </div>
                            <div class="stat-content">
                                <div class="stat-number">
                                    {{ bestPerformer.score }}
                                </div>
                                <div class="stat-label">
                                    Best Performer:
                                    {{ bestPerformer.name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mb-4">
                    <!-- Box Plot for Score Distribution -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <Icon name="chart-bar" class="me-2" />
                                    Score Distribution Analysis
                                </h5>
                                <p class="text-muted small mb-0">
                                    Box plot showing median, quartiles, and
                                    outliers for each {{ groupBy }}
                                </p>
                            </div>
                            <div class="card-body">
                                <Chart
                                    type="boxplot"
                                    :data="boxplotChartData"
                                    :options="boxplotOptions"
                                    :height="400"
                                    :responsive="true"
                                    :maintain-aspect-ratio="false"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Radar Chart for Multi-dimensional Comparison -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <Icon name="chart-line" class="me-2" />
                                    Performance Profile
                                </h5>
                                <p class="text-muted small mb-0">
                                    Multi-dimensional comparison of top
                                    {{ Math.min(8, results.length) }} performers
                                </p>
                            </div>
                            <div class="card-body">
                                <Chart
                                    type="radar"
                                    :data="radarChartData"
                                    :options="radarOptions"
                                    :height="350"
                                    :responsive="true"
                                    :maintain-aspect-ratio="false"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Performance Bar Chart -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <Icon name="chart-bar" class="me-2" />
                                    Median Performance Ranking
                                </h5>
                                <p class="text-muted small mb-0">
                                    Ranked by median score performance
                                </p>
                            </div>
                            <div class="card-body">
                                <Chart
                                    type="bar"
                                    :data="barChartData"
                                    :options="barOptions"
                                    :height="350"
                                    :responsive="true"
                                    :maintain-aspect-ratio="false"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Data Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <Icon name="table" class="me-2" />
                            Detailed Results Table
                        </h5>
                        <p class="text-muted small mb-0">
                            Complete statistical breakdown for all
                            {{ results.length }} {{ groupLabel.toLowerCase() }}
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>{{ groupLabel.slice(0, -1) }}</th>
                                        <th>Median Score</th>
                                        <th>Min Score</th>
                                        <th>Max Score</th>
                                        <th>Q1</th>
                                        <th>Q3</th>
                                        <th>IQR</th>
                                        <th>Sample Size</th>
                                        <th>Outliers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in sortedResults"
                                        :key="index"
                                    >
                                        <td class="fw-semibold">
                                            {{ getDisplayName(item) }}
                                        </td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="
                                                    getScoreBadgeClass(
                                                        item.median_score
                                                    )
                                                "
                                            >
                                                {{
                                                    formatScore(
                                                        item.median_score
                                                    )
                                                }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ formatScore(item.min_score) }}
                                        </td>
                                        <td>
                                            {{ formatScore(item.max_score) }}
                                        </td>
                                        <td>{{ formatScore(item.q1) }}</td>
                                        <td>{{ formatScore(item.q3) }}</td>
                                        <td>{{ formatScore(item.iqr) }}</td>
                                        <td>
                                            <span class="badge bg-secondary">{{
                                                item.count_score
                                            }}</span>
                                        </td>
                                        <td>
                                            <span
                                                v-if="
                                                    parseFloat(
                                                        item.outlier_pct
                                                    ) > 0
                                                "
                                                class="badge bg-warning text-dark"
                                            >
                                                {{ item.outlier_count }} ({{
                                                    parseFloat(
                                                        item.outlier_pct
                                                    ).toFixed(1)
                                                }}%)
                                            </span>
                                            <span v-else class="text-muted"
                                                >None</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration Table - always visible -->
            <div
                class="print-configuration mt-5 pt-4"
                style="border-top: 2px solid #dee2e6"
            >
                <h5>Evaluation Configuration</h5>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <tbody>
                                <tr>
                                    <td><strong>Generated</strong></td>
                                    <td>{{ new Date().toLocaleString() }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Grouping</strong></td>
                                    <td>{{ groupLabel }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Deduplication</strong></td>
                                    <td>{{ dedupeStrategy }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Groups</strong></td>
                                    <td>{{ totalResults }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <tbody>
                                <tr>
                                    <td><strong>Models</strong></td>
                                    <td>
                                        {{
                                            formatEntityList(
                                                configuredEntities.models
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Metrics</strong></td>
                                    <td>
                                        {{
                                            formatEntityList(
                                                configuredEntities.metrics
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Test Cases</strong></td>
                                    <td>
                                        {{
                                            formatEntityList(
                                                configuredEntities.testCases
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Benchmarks</strong></td>
                                    <td>
                                        {{
                                            formatEntityList(
                                                configuredEntities.benchmarks
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Prompts</strong></td>
                                    <td>
                                        {{
                                            formatEntityList(
                                                configuredEntities.prompts
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Close printable-content wrapper -->
        </div>
        <!-- Close results-content -->

        <!-- Empty state -->
        <div v-else class="empty-state">
            <div class="text-center py-5">
                <Icon name="chart-bar" size="3x" class="text-muted mb-3" />
                <h5>No Results Found</h5>
                <p class="text-muted">
                    No evaluation data matches the selected criteria. Try
                    adjusting your filters or check if data exists for the
                    selected parameters.
                </p>
                <Button variant="primary" @click="$emit('reconfigure')">
                    <Icon name="arrow-left" class="me-1" />
                    Back to Configuration
                </Button>
            </div>
        </div>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";
import Button from "@components/interactables/Button.vue";
import Chart from "@components/charts/Chart.vue";
import html2pdf from "html2pdf.js";
import { useEvaluationStore } from "@stores/EvaluationStore.js";

export default {
    name: "EvaluationResults",
    components: {
        Icon,
        Button,
        Chart,
    },
    props: {
        results: {
            type: Array,
            default: () => [],
        },
        loading: {
            type: Boolean,
            default: false,
        },
        loadingMessage: {
            type: String,
            default: "",
        },
        progress: {
            type: Object,
            default: null,
        },
        groupBy: {
            type: String,
            required: true,
        },
        dedupeStrategy: {
            type: String,
            required: true,
        },
        selectedEntities: {
            type: Object,
            required: true,
        },
    },
    computed: {
        evaluationStore() {
            return useEvaluationStore();
        },

        groupLabel() {
            const labels = {
                model: "Models",
                metric: "Metrics",
                test_case: "Test Cases",
                benchmark: "Benchmarks",
                prompt: "Prompts",
            };
            return labels[this.groupBy] || this.groupBy;
        },

        sortedResults() {
            return [...this.results].sort(
                (a, b) => b.median_score - a.median_score
            );
        },

        totalDataPoints() {
            return this.results.reduce(
                (sum, item) => sum + parseInt(item.count_score || 0),
                0
            );
        },

        overallAverage() {
            if (!this.results.length) return 0;
            const total = this.results.reduce(
                (sum, item) =>
                    sum + item.median_score * parseInt(item.count_score || 0),
                0
            );
            return total / this.totalDataPoints;
        },

        bestPerformer() {
            if (!this.results.length) return { name: "N/A", score: "0.00" };
            const best = this.sortedResults[0];
            return {
                name: this.getDisplayName(best),
                score: this.formatScore(best.median_score),
            };
        },

        // Box Plot Data
        boxplotChartData() {
            const labels = this.results.map((item) =>
                this.getDisplayName(item)
            );
            const boxplotData = this.results.map((item) => ({
                min: item.whisker_min || item.min_score,
                q1: item.q1,
                median: item.median_score,
                q3: item.q3,
                max: item.whisker_max || item.max_score,
                outliers: [],
            }));

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

        boxplotOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "top",
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const data = context.parsed;
                                return [
                                    `Min: ${data.min?.toFixed(3)}`,
                                    `Q1: ${data.q1?.toFixed(3)}`,
                                    `Median: ${data.median?.toFixed(3)}`,
                                    `Q3: ${data.q3?.toFixed(3)}`,
                                    `Max: ${data.max?.toFixed(3)}`,
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
                            text: "Score Distribution",
                        },
                    },
                    x: {
                        title: {
                            display: true,
                            text: this.groupLabel,
                        },
                        ticks: {
                            maxRotation: 45,
                        },
                    },
                },
            };
        },

        // Radar Chart Data (top 8 performers)
        radarChartData() {
            const topResults = this.sortedResults.slice(0, 8);
            const labels = topResults.map((item) => this.getDisplayName(item));

            // Normalize count_score to 0-1 scale for radar chart
            const maxCount = Math.max(
                ...topResults.map((item) => parseInt(item.count_score || 0))
            );

            return {
                labels,
                datasets: [
                    {
                        label: "Median Score",
                        data: topResults.map((item) => item.median_score),
                        borderColor: "rgb(54, 162, 235)",
                        backgroundColor: "rgba(54, 162, 235, 0.2)",
                        borderWidth: 2,
                        pointBackgroundColor: "rgb(54, 162, 235)",
                        pointBorderColor: "#fff",
                    },
                    {
                        label: "Max Score",
                        data: topResults.map((item) => item.max_score),
                        borderColor: "rgb(75, 192, 192)",
                        backgroundColor: "rgba(75, 192, 192, 0.1)",
                        borderWidth: 1,
                        borderDash: [5, 5],
                        pointBackgroundColor: "rgb(75, 192, 192)",
                        pointBorderColor: "#fff",
                    },
                    {
                        label: "Min Score",
                        data: topResults.map((item) => item.min_score),
                        borderColor: "rgb(255, 99, 132)",
                        backgroundColor: "rgba(255, 99, 132, 0.1)",
                        borderWidth: 1,
                        borderDash: [3, 3],
                        pointBackgroundColor: "rgb(255, 99, 132)",
                        pointBorderColor: "#fff",
                    },
                ],
            };
        },

        radarOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            stepSize: 0.2,
                        },
                        grid: {
                            color: "rgba(0, 0, 0, 0.1)",
                        },
                        angleLines: {
                            color: "rgba(0, 0, 0, 0.1)",
                        },
                        pointLabels: {
                            font: {
                                size: 10,
                            },
                        },
                    },
                },
                plugins: {
                    legend: {
                        position: "top",
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                let value = context.parsed.r;
                                if (
                                    context.dataset.label ===
                                    "Sample Size (normalized)"
                                ) {
                                    return `${
                                        context.dataset.label
                                    }: ${value.toFixed(3)} (normalized)`;
                                }
                                return `${
                                    context.dataset.label
                                }: ${value.toFixed(3)}`;
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
            };
        },

        // Bar Chart Data
        barChartData() {
            const sortedData = this.sortedResults.slice(0, 10); // Top 10
            const labels = sortedData.map((item) => this.getDisplayName(item));
            const scores = sortedData.map((item) => item.median_score);

            // Color coding based on performance
            const colors = scores.map((score) => {
                if (score >= 0.8) return "rgba(75, 192, 192, 0.8)"; // Green
                if (score >= 0.6) return "rgba(54, 162, 235, 0.8)"; // Blue
                if (score >= 0.4) return "rgba(255, 206, 86, 0.8)"; // Yellow
                return "rgba(255, 99, 132, 0.8)"; // Red
            });

            return {
                labels,
                datasets: [
                    {
                        label: "Median Score",
                        data: scores,
                        backgroundColor: colors,
                        borderColor: colors.map((color) =>
                            color.replace("0.8", "1")
                        ),
                        borderWidth: 1,
                    },
                ],
            };
        },

        barOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return `Median Score: ${context.parsed.y.toFixed(
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
                            text: "Median Score",
                        },
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                        },
                        title: {
                            display: true,
                            text: this.groupLabel,
                        },
                    },
                },
            };
        },

        // Computed properties for print configuration
        totalResults() {
            return this.results.length;
        },

        configuredEntities() {
            // Use the actual selected entities from the user's configuration
            const store = this.evaluationStore;

            return {
                models: this.getSelectedEntityList(
                    this.selectedEntities.model,
                    store.data?.models || [],
                    "name",
                    "id"
                ),
                metrics: this.getSelectedEntityList(
                    this.selectedEntities.metric,
                    store.data?.metrics || [],
                    "name",
                    "id"
                ),
                testCases: this.getSelectedEntityList(
                    this.selectedEntities.test_case,
                    store.data?.testCases || [],
                    "name",
                    "id"
                ),
                benchmarks: this.getSelectedEntityList(
                    this.selectedEntities.benchmark,
                    store.data?.benchmarks || [],
                    "name",
                    "id"
                ),
                prompts: this.getSelectedEntityList(
                    this.selectedEntities.prompt,
                    store.data?.prompts || [],
                    "name",
                    "id"
                ),
            };
        },
    },
    methods: {
        async printResults() {
            try {
                // Small delay to ensure DOM updates
                await this.$nextTick();

                // Get the content element that includes the configuration table
                const contentElement =
                    document.getElementById("printable-content");

                if (!contentElement) {
                    console.error("Printable content element not found");
                    return;
                }

                // Clone content with special handling for canvas elements
                const contentClone = contentElement.cloneNode(true);

                // Find all canvas elements in both original and cloned content
                const originalCanvases =
                    contentElement.querySelectorAll("canvas");
                const clonedCanvases = contentClone.querySelectorAll("canvas");

                // Copy canvas content from original to cloned elements
                originalCanvases.forEach((originalCanvas, index) => {
                    if (clonedCanvases[index]) {
                        const clonedCanvas = clonedCanvases[index];
                        const context = clonedCanvas.getContext("2d");

                        // Set canvas dimensions
                        clonedCanvas.width = originalCanvas.width;
                        clonedCanvas.height = originalCanvas.height;

                        // Copy the canvas content
                        context.drawImage(originalCanvas, 0, 0);
                    }
                });

                const options = {
                    margin: 0.5,
                    filename: `evaluation-results-${new Date()
                        .toISOString()
                        .slice(0, 10)}.pdf`,
                    image: { type: "jpeg", quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        allowTaint: true,
                        scrollX: 0,
                        scrollY: 0,
                        windowWidth: 1200,
                        windowHeight: 800,
                    },
                    jsPDF: {
                        unit: "in",
                        format: "a4",
                        orientation: "portrait",
                    },
                };

                // Generate PDF
                await html2pdf().set(options).from(contentClone).save();
            } catch (error) {
                console.error("Error generating PDF:", error);
                alert("Error generating PDF. Please try again.");
            }
        },

        getSelectedEntityList(selectedIds, allEntities, nameField, idField) {
            // Only return entities that were actually selected by the user
            return selectedIds.map((selectedId) => {
                const entity = allEntities.find(
                    (e) => e[idField] == selectedId
                );
                return {
                    name: entity
                        ? entity[nameField] || `ID: ${entity[idField]}`
                        : `ID: ${selectedId}`,
                    id: selectedId,
                };
            });
        },

        formatEntityList(entityList) {
            if (!entityList || entityList.length === 0) {
                return "None";
            }

            // If more than 15 items, show only IDs to save space
            if (entityList.length > 15) {
                return `${entityList.length} items: [${entityList
                    .map((entity) => entity.id)
                    .join(", ")}]`;
            }

            // Otherwise show names with IDs
            return entityList
                .map((entity) => `${entity.name} (ID: ${entity.id})`)
                .join(", ");
        },

        getDisplayName(item) {
            // Use enriched names from the store
            const nameKey = this.getGroupKey();
            return (
                item[nameKey] || `${this.groupBy} ${item[`${this.groupBy}_id`]}`
            );
        },

        getGroupKey() {
            const groupKeyMap = {
                model: "model_name",
                metric: "metric_name",
                test_case: "test_case_name",
                benchmark: "benchmark_name",
                prompt: "prompt_name",
            };
            return groupKeyMap[this.groupBy] || this.groupBy;
        },

        formatScore(value) {
            if (value === null || value === undefined) return "-";
            return parseFloat(value).toFixed(3);
        },

        getScoreBadgeClass(score) {
            if (score >= 0.8) return "bg-success";
            if (score >= 0.6) return "bg-primary";
            if (score >= 0.4) return "bg-warning text-dark";
            return "bg-danger";
        },
    },
};
</script>

<style scoped>
.evaluation-results {
    width: 100%;
}

.results-header {
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 15px;
}

.loading-container {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.spinner-large {
    width: 40px;
    height: 40px;
    border: 3px solid #e5e7eb;
    border-top: 3px solid #3b82f6;
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

.stat-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    height: 100%;
}

.stat-icon {
    background: #f0f9ff;
    border-radius: 8px;
    padding: 12px;
    color: #0369a1;
    font-size: 24px;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
}

.card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.card-header {
    background-color: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
    padding: 15px 20px;
}

.card-header h5 {
    margin: 0;
    color: #374151;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.card-body {
    padding: 20px;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #374151;
    background-color: #f8fafc;
}

.empty-state {
    min-height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.progress-info {
    margin-top: 10px;
}

.chart-container {
    min-height: 50vh;
}
</style>
