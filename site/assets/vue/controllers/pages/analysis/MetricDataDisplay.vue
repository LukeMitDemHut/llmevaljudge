<template>
    <div class="metric-data-display mb-4">
        <!-- Metric Title -->
        <h5 class="mb-3">{{ title }}</h5>

        <!-- Statistics Overview -->
        <StatCard
            :metric="title"
            :totalDataPoints="data.totalDataPoints"
            :avgScore="data.avgScore"
            :minScore="data.minScore"
            :maxScore="data.maxScore"
        />

        <!-- Best and Worst Results -->
        <div class="row mt-4">
            <div class="col-lg-6 mb-3">
                <ResultsPreview title="Best" :results="data.bestResults" />
            </div>
            <div class="col-lg-6 mb-3">
                <ResultsPreview title="Worst" :results="data.worstResults" />
            </div>
        </div>

        <!-- Model Comparison Chart -->
        <div v-if="comparisonData.length > 1" class="mt-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <Icon name="bar-chart-2" class="me-2" />
                        Cross-Model Comparison for {{ title }}
                    </h6>
                </div>
                <div class="card-body">
                    <Chart
                        type="bar"
                        :data="comparisonChartData"
                        :options="comparisonChartOptions"
                        :height="300"
                        :responsive="true"
                        :maintain-aspect-ratio="false"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
<script>
import StatCard from "./StatCard.vue";
import ResultsPreview from "./ResultsPreview.vue";
import Chart from "@components/charts/Chart.vue";
import Icon from "@components/structure/Icon.vue";

export default {
    name: "MetricDataDisplay",
    components: {
        StatCard,
        ResultsPreview,
        Chart,
        Icon,
    },
    props: {
        data: {
            type: Object,
            required: true,
        },
        title: {
            type: String,
            default: "Metric Name not available",
        },
        comparisonData: {
            type: Array,
            default: () => [],
        },
        currentModelId: {
            type: Number,
            default: null,
        },
    },
    data() {
        return {};
    },
    computed: {
        comparisonChartData() {
            if (!this.comparisonData.length) return null;

            const labels = this.comparisonData.map((item) => item.modelName);
            const avgScores = this.comparisonData.map((item) => item.avgScore);

            // Color coding: highlight current model, keep others uniform
            const colors = this.comparisonData.map((item) => {
                if (item.modelId === this.currentModelId) {
                    return "rgba(255, 165, 0, 0.8)"; // Orange for current model
                }
                return "rgba(108, 117, 125, 0.6)"; // Gray for other models
            });

            const borderColors = colors.map((color) => {
                if (color.includes("255, 165, 0")) {
                    return "rgba(255, 165, 0, 1)"; // Solid orange border
                }
                return "rgba(108, 117, 125, 0.8)"; // Darker gray border
            });

            return {
                labels,
                datasets: [
                    {
                        label: "Average Score",
                        data: avgScores,
                        backgroundColor: colors,
                        borderColor: borderColors,
                        borderWidth: 2,
                    },
                ],
            };
        },

        comparisonChartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const modelData =
                                    this.comparisonData[context.dataIndex];
                                return [
                                    `Average Score: ${context.parsed.y.toFixed(
                                        3
                                    )}`,
                                    `Min Score: ${modelData.minScore.toFixed(
                                        3
                                    )}`,
                                    `Max Score: ${modelData.maxScore.toFixed(
                                        3
                                    )}`,
                                    `Data Points: ${modelData.totalDataPoints}`,
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
                            callback: (value) => value.toFixed(1),
                        },
                        title: {
                            display: true,
                            text: "Average Score",
                        },
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                        },
                        title: {
                            display: true,
                            text: "Models",
                        },
                    },
                },
            };
        },
    },
};
</script>

<style scoped>
.metric-data-display {
    padding: 1.5rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background-color: #f8f9fa;
}

.metric-data-display h5 {
    color: #495057;
    border-bottom: 2px solid #dee2e6;
    padding-bottom: 0.5rem;
}
</style>
