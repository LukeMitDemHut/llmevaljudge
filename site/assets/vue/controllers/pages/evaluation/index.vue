<template>
    <div class="container-fluid py-4" data-bs-theme="light">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <PageHeading title="Evaluation" icon="graph">
                    <div class="d-flex gap-2 align-items-center">
                        <!-- Deduplication Strategy Selector -->
                        <div class="d-flex align-items-center gap-2">
                            <label
                                for="deduplicationStrategy"
                                class="form-label mb-0 text-muted small"
                            >
                                <i class="ph ph-copy me-1"></i>
                                Deduplication:
                            </label>
                            <select
                                id="deduplicationStrategy"
                                v-model="resultsStore.deduplicationStrategy"
                                @change="handleDeduplicationChange"
                                class="form-select form-select-sm"
                                style="width: auto; min-width: 120px"
                            >
                                <option value="latest">Latest</option>
                                <option value="all">All</option>
                                <option value="average">Average</option>
                            </select>
                            <div class="ms-1">
                                <i
                                    class="ph ph-info text-muted"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="bottom"
                                    :title="getDeduplicationTooltip()"
                                ></i>
                            </div>
                        </div>

                        <ButtonGroup
                            @click="printResults"
                            :buttons="[
                                {
                                    id: 'print',
                                    icon: 'printer',
                                    variant: 'outline-secondary',
                                    size: 'sm',
                                },
                            ]"
                            aria-label="Settings actions"
                        >
                        </ButtonGroup>
                    </div>
                </PageHeading>

                <Tabs
                    :tabs="[
                        {
                            id: 'model',
                            label: 'Model',
                            icon: 'tray',
                        },
                        {
                            id: 'metric',
                            label: 'Metric',
                            icon: 'chart-line',
                        },
                        {
                            id: 'testcase',
                            label: 'Test Case',
                            icon: 'list-checks',
                        },
                        {
                            id: 'benchmark',
                            label: 'Benchmark',
                            icon: 'chart-line-up',
                        },
                    ]"
                    :activeTab="activeTab"
                    @update:activeTab="handleTabChange"
                >
                    <template v-slot:model-pane> <Model /> </template>
                    <template v-slot:metric-pane> <Metric /> </template>
                    <template #testcase-pane>
                        <TestCase />
                    </template>
                    <template #benchmark-pane>
                        <Benchmark />
                    </template>
                </Tabs>
            </div>
        </div>
    </div>
</template>

<script>
import { useResultsStore } from "@stores/ResultsStore.js";
import PageHeading from "@components/structure/PageHeading.vue";
import ButtonGroup from "@components/interactables/ButtonGroup.vue";
import Button from "@components/interactables/Button.vue";
import Alert from "@components/interactables/Alert.vue";
import Tabs from "@components/structure/Tabs.vue";
import Card from "@components/structure/Card.vue";
import Model from "./Model.vue";
import Metric from "./Metric.vue";
import TestCase from "./TestCase.vue";
import Benchmark from "./Benchmark.vue";
import html2pdf from "html2pdf.js";

export default {
    components: {
        PageHeading,
        ButtonGroup,
        Button,
        Alert,
        Tabs,
        Card,
        Model,
        Metric,
        TestCase,
        Benchmark,
    },
    data() {
        return {
            activeTab: "model",
        };
    },
    props: {
        models: {
            type: Array,
            default: () => [],
        },
        metrics: {
            type: Array,
            default: () => [],
        },
        testCases: {
            type: Array,
            default: () => [],
        },
        benchmarks: {
            type: Array,
            default: () => [],
        },
    },
    computed: {
        resultsStore() {
            return useResultsStore();
        },
    },
    mounted() {
        // Initialize store with server data on mount
        this.resultsStore.initialize({
            models: this.models,
            metrics: this.metrics,
            testCases: this.testCases,
            benchmarks: this.benchmarks,
        });

        // Set default deduplication strategy if not already set
        if (!this.resultsStore.deduplicationStrategy) {
            this.resultsStore.deduplicationStrategy = "latest";
        }
    },
    methods: {
        handleTabChange(tabId) {
            this.resultsStore.evaluationData = undefined;
            this.activeTab = tabId;
        },
        handleDeduplicationChange() {
            // Clear current evaluation data to force re-fetch with new strategy
            this.resultsStore.evaluationData = undefined;
        },
        getDeduplicationTooltip() {
            const tooltips = {
                latest: "Uses results from the most recent benchmark when duplicates exist",
                all: "Shows all results including duplicates from multiple benchmarks",
                average:
                    "Creates averaged scores from duplicate results across benchmarks",
            };
            return tooltips[this.resultsStore.deduplicationStrategy] || "";
        },
        getFilename() {
            const now = new Date();
            const timestamp =
                now.toISOString().slice(0, 10).replace(/-/g, "") +
                "_" +
                now.toTimeString().slice(0, 8).replace(/:/g, "");
            return `${timestamp}_LLMevalJudge.pdf`;
        },
        printResults() {
            if (!this.activeTab) {
                this.activeTab = "model";
            }

            const printableId = `printable-${this.activeTab}`;
            const element = document.getElementById(printableId);

            if (!element) {
                console.error(
                    `Printable element with id "${printableId}" not found`
                );
                return;
            }

            const options = {
                margin: 0.5,
                filename: this.getFilename(),
                image: { type: "jpeg", quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: "in", format: "a4", orientation: "portrait" },
            };
            html2pdf().set(options).from(element).save();
        },
    },
};
</script>
