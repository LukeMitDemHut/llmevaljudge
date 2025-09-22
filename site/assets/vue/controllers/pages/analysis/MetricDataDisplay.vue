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
    </div>
</template>
<script>
import StatCard from "./StatCard.vue";
import ResultsPreview from "./ResultsPreview.vue";

export default {
    name: "MetricDataDisplay",
    components: {
        StatCard,
        ResultsPreview,
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
    },
    data() {
        return {};
    },
    computed: {},
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
