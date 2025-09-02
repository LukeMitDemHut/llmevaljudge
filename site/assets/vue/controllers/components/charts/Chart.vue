<template>
    <div class="chart-container">
        <canvas :id="chartId" :width="width" :height="height"></canvas>
    </div>
</template>

<script>
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    RadialLinearScale,
    Filler,
    RadarController,
    LineController,
    BarController,
    BarElement,
    PieController,
    DoughnutController,
    PolarAreaController,
    ArcElement,
} from "chart.js";
import {
    BoxPlotController,
    BoxAndWiskers,
} from "@sgratzl/chartjs-chart-boxplot";

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    RadialLinearScale,
    Filler,
    RadarController,
    LineController,
    BarController,
    PieController,
    DoughnutController,
    PolarAreaController,
    BoxPlotController,
    BoxAndWiskers
);

export default {
    name: "Chart",
    props: {
        type: {
            type: String,
            required: true,
            validator(value) {
                return [
                    "line",
                    "bar",
                    "radar",
                    "pie",
                    "doughnut",
                    "polarArea",
                    "boxplot",
                ].includes(value);
            },
        },
        data: {
            type: Object,
            required: true,
        },
        options: {
            type: Object,
            default: () => ({}),
        },
        width: {
            type: [Number, String],
            default: 400,
        },
        height: {
            type: [Number, String],
            default: 400,
        },
        responsive: {
            type: Boolean,
            default: true,
        },
        maintainAspectRatio: {
            type: Boolean,
            default: true,
        },
    },
    data() {
        return {
            chart: null,
            chartId: `chart-${Math.random().toString(36).substr(2, 9)}`,
        };
    },
    computed: {
        chartOptions() {
            return {
                responsive: this.responsive,
                maintainAspectRatio: this.maintainAspectRatio,
                ...this.options,
            };
        },
    },
    mounted() {
        this.createChart();
    },
    beforeUnmount() {
        if (this.chart) {
            this.chart.destroy();
        }
    },
    watch: {
        data: {
            handler() {
                this.updateChart();
            },
            deep: true,
        },
        options: {
            handler() {
                this.updateChart();
            },
            deep: true,
        },
    },
    methods: {
        createChart() {
            const ctx = document.getElementById(this.chartId);
            if (!ctx) {
                console.error("Canvas element not found");
                return;
            }

            this.chart = new ChartJS(ctx, {
                type: this.type,
                data: this.data,
                options: this.chartOptions,
            });
        },
        updateChart() {
            if (!this.chart) return;

            // Update data
            this.chart.data = this.data;

            // Update options
            Object.assign(this.chart.options, this.chartOptions);

            // Re-render the chart
            this.chart.update();
        },
        refreshChart() {
            if (this.chart) {
                this.chart.destroy();
            }
            this.$nextTick(() => {
                this.createChart();
            });
        },
    },
};
</script>

<style scoped>
.chart-container {
    position: relative;
    width: 100%;
    height: auto;
}

.chart-container canvas {
    max-width: 100%;
    height: auto !important;
}
</style>
