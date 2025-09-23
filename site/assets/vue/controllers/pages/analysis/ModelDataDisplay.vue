<template>
    <StatCard
        :totalDataPoints="data.data.totalDataPoints"
        :model="data.name"
        :avgScore="data.data.avgScore"
        :minScore="data.data.minScore"
        :maxScore="data.data.maxScore"
    />

    <!-- Bar Chart for Average Scores per Metric -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <Icon name="chart-bar" class="me-2" />
                Average Performance by Metric
            </h5>
            <p class="text-muted small mb-0">
                Comparison of model performance across different metrics
            </p>
        </div>
        <div class="card-body">
            <Chart
                v-if="data.perMetric?.length"
                type="bar"
                :data="barChartData"
                :options="barChartOptions"
                :height="350"
                :responsive="true"
                :maintain-aspect-ratio="false"
            />
            <div v-else class="text-center text-muted py-4">
                <em>No metric data available for chart visualization</em>
            </div>
        </div>
    </div>

    <!-- Radar Chart for Metric Insights -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <Icon name="chart-line" class="me-2" />
                Metric Performance Profile
            </h5>
            <p class="text-muted small mb-0">
                Multi-dimensional view showing min, max, and average performance
                for each metric
            </p>
        </div>
        <div class="card-body">
            <Chart
                v-if="data.perMetric?.length"
                type="radar"
                :data="radarChartData"
                :options="radarChartOptions"
                :height="400"
                :responsive="true"
                :maintain-aspect-ratio="false"
            />
            <div v-else class="text-center text-muted py-4">
                <em>No metric data available for radar chart visualization</em>
            </div>
        </div>
    </div>

    <!-- Individual Metric Details -->
    <h4>Get key insights per Metric</h4>
    <div v-for="metricData in data.perMetric" :key="metricData.id">
        <MetricDataDisplay
            :data="metricData.data"
            :title="`Metric: ${metricData.name}`"
            :comparisonData="getMetricComparisonData(metricData.id)"
            :currentModelId="data.id"
        />
    </div>
</template>
<script>
import StatCard from "./StatCard.vue";
import MetricDataDisplay from "./MetricDataDisplay.vue";
import Chart from "@components/charts/Chart.vue";
import Icon from "@components/structure/Icon.vue";

export default {
    name: "ModelDataDisplay",
    components: {
        StatCard,
        MetricDataDisplay,
        Chart,
        Icon,
    },
    props: {
        data: {
            type: Object,
            required: true,
        },
        allModels: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {};
    },
    computed: {
        barChartData() {
            if (!this.data.perMetric?.length) {
                return {
                    labels: [],
                    datasets: [],
                };
            }

            const labels = this.data.perMetric.map((metric) => metric.name);
            const scores = this.data.perMetric.map(
                (metric) => metric.data.avgScore
            );

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
                        label: "Average Score",
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

        barChartOptions() {
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
                                return `Average Score: ${context.parsed.y.toFixed(
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
                            text: "Average Score",
                        },
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                        },
                        title: {
                            display: true,
                            text: "Metrics",
                        },
                    },
                },
            };
        },

        radarChartData() {
            if (!this.data.perMetric?.length) {
                return {
                    labels: [],
                    datasets: [],
                };
            }

            const labels = this.data.perMetric.map((metric) => metric.name);
            const avgScores = this.data.perMetric.map(
                (metric) => metric.data.avgScore
            );
            const maxScores = this.data.perMetric.map(
                (metric) => metric.data.maxScore
            );
            const minScores = this.data.perMetric.map(
                (metric) => metric.data.minScore
            );

            return {
                labels,
                datasets: [
                    {
                        label: "Average Score",
                        data: avgScores,
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
                        borderDash: [3, 3],
                        pointBackgroundColor: "rgb(255, 99, 132)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgb(255, 99, 132)",
                    },
                ],
            };
        },

        radarChartOptions() {
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
                                size: 11,
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
                                return `${
                                    context.dataset.label
                                }: ${context.parsed.r.toFixed(3)}`;
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
    },
    methods: {
        getMetricComparisonData(metricId) {
            if (!this.allModels.length) return null;

            // Find the same metric across all models
            const comparisonData = this.allModels
                .map((model) => {
                    const metric = model.perMetric?.find(
                        (m) => m.id === metricId
                    );
                    return {
                        modelId: model.id,
                        modelName: model.name,
                        avgScore: metric?.data?.avgScore || 0,
                        minScore: metric?.data?.minScore || 0,
                        maxScore: metric?.data?.maxScore || 0,
                        totalDataPoints: metric?.data?.totalDataPoints || 0,
                    };
                })
                .filter((item) => item.totalDataPoints > 0); // Only include models that have data for this metric

            return comparisonData;
        },
    },
};
</script>
