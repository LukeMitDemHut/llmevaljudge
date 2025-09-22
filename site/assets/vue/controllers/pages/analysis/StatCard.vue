<template>
    <div class="d-flex gap-3 flex-wrap">
        <div class="card shadow-sm border-0 flex-grow-1" v-if="model">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <Icon name="tray" class="text-primary fs-3" />
                </div>
                <div>
                    <div class="stat-number fw-bold fs-4">
                        {{ model }}
                    </div>
                    <div class="stat-label text-muted">Model</div>
                </div>
            </div>
        </div>
        <div class="card shadow-sm border-0 flex-grow-1" v-if="metric">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <Icon name="chart-bar" class="text-success fs-3" />
                </div>
                <div>
                    <div class="stat-number fw-bold fs-4">
                        {{ metric }}
                    </div>
                    <div class="stat-label text-muted">Metric</div>
                </div>
            </div>
        </div>
        <div class="card shadow-sm border-0 flex-grow-1">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <Icon name="database" class="text-info fs-3" />
                </div>
                <div>
                    <div class="stat-number fw-bold fs-4">
                        {{ totalDataPoints }}
                    </div>
                    <div class="stat-label text-muted">Total Data Points</div>
                </div>
            </div>
        </div>
        <div
            class="card shadow-sm border-0 flex-grow-1"
            v-if="avgScore !== null && avgScore !== undefined"
        >
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <Icon name="star" class="text-warning fs-3" />
                </div>
                <div>
                    <div class="stat-number fw-bold fs-4">
                        {{ formatScore(avgScore) }}
                    </div>
                    <div class="stat-label text-muted">Average Score</div>
                </div>
            </div>
        </div>
        <div
            class="card shadow-sm border-0 flex-grow-1"
            v-if="minScore !== null && minScore !== undefined"
        >
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <Icon name="arrow-down" class="text-danger fs-3" />
                </div>
                <div>
                    <div class="stat-number fw-bold fs-4">
                        {{ formatScore(minScore) }}
                    </div>
                    <div class="stat-label text-muted">Min Score</div>
                </div>
            </div>
        </div>
        <div
            class="card shadow-sm border-0 flex-grow-1"
            v-if="maxScore !== null && maxScore !== undefined"
        >
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <Icon name="arrow-up" class="text-success fs-3" />
                </div>
                <div>
                    <div class="stat-number fw-bold fs-4">
                        {{ formatScore(maxScore) }}
                    </div>
                    <div class="stat-label text-muted">Max Score</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";
export default {
    name: "StatCard",
    components: {
        Icon,
    },
    props: {
        model: {
            type: String,
            required: false,
        },
        metric: {
            type: String,
            required: false,
        },
        totalDataPoints: {
            type: Number,
            required: false,
            default: 0,
        },
        avgScore: {
            type: Number,
            required: false,
            default: null,
        },
        minScore: {
            type: Number,
            required: false,
            default: null,
        },
        maxScore: {
            type: Number,
            required: false,
            default: null,
        },
    },
    methods: {
        formatScore(score) {
            if (score === null || score === undefined) return "N/A";
            return Number(score).toFixed(3);
        },
    },
};
</script>
