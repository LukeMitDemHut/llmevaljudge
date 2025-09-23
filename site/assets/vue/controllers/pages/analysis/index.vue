<template>
    <div class="container-fluid py-4" data-bs-theme="light">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <PageHeading :title="title" icon="chart-bar"></PageHeading>

                <!-- Overall Analysis Section -->
                <div v-if="analysisData.overall" class="mb-5">
                    <!-- Overall Stats Cards -->
                    <StatCard
                        :totalDataPoints="analysisData.overall.totalDataPoints"
                        :avgScore="analysisData.overall.avgScore"
                        :minScore="analysisData.overall.minScore"
                        :maxScore="analysisData.overall.maxScore"
                    />

                    <!-- Overall Charts -->
                    <div class="row g-4 mb-4 mt-4">
                        <div class="col-12 w-100">
                            <Card>
                                <template #header>
                                    <h5 class="mb-0">
                                        <Icon name="bar-chart-2" class="me-2" />
                                        Model Performance Comparison
                                    </h5>
                                </template>
                                <Chart
                                    type="bar"
                                    :data="modelComparisonChartData"
                                    :options="barChartOptions"
                                    :height="400"
                                    :responsive="true"
                                    :maintain-aspect-ratio="false"
                                />
                            </Card>
                        </div>
                        <div class="col-12 w-100">
                            <Card>
                                <template #header>
                                    <h5 class="mb-0">
                                        <Icon name="activity" class="me-2" />
                                        Overall Performance Insights
                                    </h5>
                                </template>
                                <Chart
                                    type="radar"
                                    :data="overallRadarChartData"
                                    :options="radarChartOptions"
                                    :height="400"
                                    :responsive="true"
                                    :maintain-aspect-ratio="false"
                                />
                            </Card>
                        </div>
                    </div>
                </div>

                <h2 class="h3 mb-4">
                    <Icon name="tray" class="me-2" />
                    Analysis by Model
                </h2>

                <Tabs v-if="modelTabs.length > 0" :tabs="modelTabs">
                    <template
                        v-for="model in models"
                        :key="`${model.id}-pane`"
                        v-slot:[`model-${model.id}-pane`]
                    >
                        <ModelDataDisplay :data="model" :allModels="models" />
                    </template>
                </Tabs>

                <!-- Empty state when no models -->
                <Card v-else>
                    <EmptyState
                        title="No models available"
                        description="Add models to view analysis data."
                        icon="chart-bar"
                    />
                </Card>
            </div>
        </div>
    </div>
</template>

<script>
import PageHeading from "@components/structure/PageHeading.vue";
import Tabs from "@components/structure/Tabs.vue";
import Card from "@components/structure/Card.vue";
import EmptyState from "@components/structure/EmptyState.vue";
import Icon from "@components/structure/Icon.vue";
import StatCard from "./StatCard.vue";
import Chart from "@components/charts/Chart.vue";
import ModelDataDisplay from "./ModelDataDisplay.vue";

export default {
    name: "Benchmark Analysis",
    components: {
        PageHeading,
        Tabs,
        Card,
        EmptyState,
        Icon,
        StatCard,
        Chart,
        ModelDataDisplay,
    },
    props: {
        title: {
            type: String,
            default: "Analysis",
        },
        analysisData: {
            type: Object,
            default: () => ({ byModel: [] }),
        },
    },
    computed: {
        modelTabs() {
            if (
                !this.analysisData.byModel ||
                this.analysisData.byModel.length === 0
            ) {
                return [];
            }
            return this.analysisData.byModel.map((model) => ({
                id: `model-${model.id}`,
                label: model.name,
                icon: "chart-line", // Add required icon property
            }));
        },
        models() {
            return this.analysisData.byModel || [];
        },

        modelComparisonChartData() {
            if (!this.models.length) return null;

            const labels = this.models.map((model) => model.name);
            const avgScores = this.models.map((model) => model.data.avgScore);

            return {
                labels,
                datasets: [
                    {
                        label: "Average Score",
                        data: avgScores,
                        backgroundColor: [
                            "rgba(54, 162, 235, 0.8)",
                            "rgba(255, 99, 132, 0.8)",
                            "rgba(255, 206, 86, 0.8)",
                            "rgba(75, 192, 192, 0.8)",
                            "rgba(153, 102, 255, 0.8)",
                            "rgba(255, 159, 64, 0.8)",
                            "rgba(199, 199, 199, 0.8)",
                        ],
                        borderColor: [
                            "rgba(54, 162, 235, 1)",
                            "rgba(255, 99, 132, 1)",
                            "rgba(255, 206, 86, 1)",
                            "rgba(75, 192, 192, 1)",
                            "rgba(153, 102, 255, 1)",
                            "rgba(255, 159, 64, 1)",
                            "rgba(199, 199, 199, 1)",
                        ],
                        borderWidth: 2,
                    },
                ],
            };
        },

        overallRadarChartData() {
            if (!this.models.length) return null;

            const labels = this.models.map((model) => model.name);
            const minScores = this.models.map((model) => model.data.minScore);
            const avgScores = this.models.map((model) => model.data.avgScore);
            const maxScores = this.models.map((model) => model.data.maxScore);

            return {
                labels,
                datasets: [
                    {
                        label: "Minimum Score",
                        data: minScores,
                        fill: false,
                        backgroundColor: "rgba(255, 99, 132, 0.2)",
                        borderColor: "rgba(255, 99, 132, 1)",
                        pointBackgroundColor: "rgba(255, 99, 132, 1)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgba(255, 99, 132, 1)",
                        borderWidth: 2,
                    },
                    {
                        label: "Average Score",
                        data: avgScores,
                        fill: false,
                        backgroundColor: "rgba(54, 162, 235, 0.2)",
                        borderColor: "rgba(54, 162, 235, 1)",
                        pointBackgroundColor: "rgba(54, 162, 235, 1)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgba(54, 162, 235, 1)",
                        borderWidth: 2,
                    },
                    {
                        label: "Maximum Score",
                        data: maxScores,
                        fill: false,
                        backgroundColor: "rgba(75, 192, 192, 0.2)",
                        borderColor: "rgba(75, 192, 192, 1)",
                        pointBackgroundColor: "rgba(75, 192, 192, 1)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgba(75, 192, 192, 1)",
                        borderWidth: 2,
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
                        position: "top",
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return `${
                                    context.dataset.label
                                }: ${this.formatScore(context.parsed.y)}`;
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            callback: (value) => this.formatScore(value),
                        },
                    },
                },
            };
        },

        radarChartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "top",
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return `${
                                    context.dataset.label
                                }: ${this.formatScore(context.parsed.r)}`;
                            },
                        },
                    },
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            callback: (value) => this.formatScore(value),
                        },
                    },
                },
            };
        },
    },
    methods: {
        formatScore(score) {
            if (score === null || score === undefined) return "N/A";
            return Number(score).toFixed(3);
        },
    },
    watch: {
        // Watch for changes in analysisData to ensure reactivity
        analysisData: {
            handler(newVal) {
                // Force re-render of tabs when data changes
                this.$forceUpdate();
            },
            deep: true,
        },
    },
};
</script>
