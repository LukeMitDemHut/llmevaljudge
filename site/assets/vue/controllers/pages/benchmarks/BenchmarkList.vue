<template>
    <div class="row">
        <div
            v-for="benchmark in benchmarks"
            :key="benchmark.id"
            class="col-12 col-lg-6 col-lg-4 mb-3"
        >
            <Card class="h-100 position-relative benchmark-card">
                <div class="position-absolute top-0 end-0 m-2">
                    <Dropdown @click.stop>
                        <DropdownItem
                            v-if="canEdit(benchmark)"
                            icon="pencil"
                            @click="$emit('edit', benchmark)"
                        >
                            Edit
                        </DropdownItem>
                        <DropdownItem
                            v-if="canViewResults(benchmark)"
                            icon="chart-line-up"
                            @click="$emit('view-results', benchmark)"
                        >
                            View Results
                        </DropdownItem>
                        <DropdownItem
                            v-if="canReRun(benchmark)"
                            icon="play"
                            @click="$emit('start', benchmark)"
                        >
                            Re-Run Benchmark
                        </DropdownItem>
                        <DropdownItem
                            v-if="canRerunMissing(benchmark)"
                            icon="circle-dashed"
                            @click="$emit('start-missing', benchmark)"
                        >
                            Rerun Failed/Missing
                        </DropdownItem>
                        <DropdownItem
                            v-if="canStart(benchmark)"
                            icon="play"
                            @click="$emit('start', benchmark)"
                        >
                            Start Benchmark
                        </DropdownItem>
                        <hr class="dropdown-divider" />
                        <DropdownItem
                            icon="trash"
                            :danger="true"
                            @click="$emit('delete', benchmark)"
                        >
                            Delete
                        </DropdownItem>
                    </Dropdown>
                </div>

                <div class="d-flex align-items-start">
                    <div class="me-3">
                        <div
                            class="rounded-circle d-flex align-items-center justify-content-center text-white"
                            :class="`bg-${getStatusVariant(benchmark)}`"
                            style="width: 40px; height: 40px"
                        >
                            <Icon :name="getStatusIcon(benchmark)" />
                        </div>
                    </div>
                    <div class="flex-grow-1 pe-4">
                        <h6 class="mb-1">{{ benchmark.name }}</h6>
                        <div class="mb-2">
                            <span
                                class="badge me-2"
                                :class="`bg-${getStatusVariant(benchmark)}`"
                            >
                                {{ getStatusLabel(benchmark) }}
                            </span>
                        </div>

                        <div class="small text-muted">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Models:</span>
                                <span class="fw-bold">{{
                                    getModelCount(benchmark)
                                }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Test Cases:</span>
                                <span class="fw-bold">{{
                                    getTestCaseCount(benchmark)
                                }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Metrics:</span>
                                <span class="fw-bold">{{
                                    getMetricCount(benchmark)
                                }}</span>
                            </div>

                            <div
                                v-if="benchmark.startedAt"
                                class="mt-2 pt-2 border-top"
                            >
                                <!-- Progress bar for running benchmarks -->
                                <div
                                    v-if="
                                        getBenchmarkStatus(benchmark) ===
                                        'running'
                                    "
                                    class="mb-2"
                                >
                                    <div
                                        class="d-flex justify-content-between mb-1"
                                    >
                                        <span>Progress:</span>
                                        <span class="fw-bold"
                                            >{{
                                                benchmark.progress || 0
                                            }}%</span
                                        >
                                    </div>
                                    <ProgressBar
                                        :value="benchmark.progress || 0"
                                    />
                                </div>

                                <div
                                    class="d-flex justify-content-between mb-1"
                                >
                                    <span>Started:</span>
                                    <span class="fw-bold">{{
                                        formatDate(benchmark.startedAt)
                                    }}</span>
                                </div>
                                <div
                                    v-if="benchmark.finishedAt"
                                    class="d-flex justify-content-between"
                                >
                                    <span>Finished:</span>
                                    <span class="fw-bold">{{
                                        formatDate(benchmark.finishedAt)
                                    }}</span>
                                </div>
                                <div
                                    v-else-if="
                                        getBenchmarkStatus(benchmark) ===
                                        'running'
                                    "
                                    class="d-flex justify-content-between mb-1"
                                >
                                    <span>Duration:</span>
                                    <span class="fw-bold">{{
                                        getRunningDuration(benchmark)
                                    }}</span>
                                </div>
                                <div
                                    v-if="
                                        getBenchmarkStatus(benchmark) ===
                                            'running' && getETA(benchmark)
                                    "
                                    class="d-flex justify-content-between"
                                >
                                    <span>ETA:</span>
                                    <span class="fw-bold">{{
                                        getETA(benchmark)
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>

<script>
import Card from "@components/structure/Card.vue";
import Dropdown, { DropdownItem } from "@components/interactables/Dropdown.vue";
import Icon from "@components/structure/Icon.vue";
import ProgressBar from "@components/structure/ProgressBar.vue";

export default {
    name: "BenchmarkList",
    components: {
        Card,
        Dropdown,
        DropdownItem,
        Icon,
        ProgressBar,
    },
    props: {
        benchmarks: {
            type: Array,
            default: () => [],
        },
    },
    emits: ["delete", "edit", "start", "start-missing", "view-results"],
    methods: {
        getBenchmarkStatus(benchmark) {
            if (!benchmark.startedAt) return "not_started";
            if (benchmark.startedAt && !benchmark.finishedAt) return "running";
            return "finished";
        },

        getStatusLabel(benchmark) {
            const addition = this.hasErrors(benchmark) ? " (with errors)" : "";
            const status = this.getBenchmarkStatus(benchmark);
            switch (status) {
                case "not_started":
                    return "Not Started" + addition;
                case "running":
                    return "Running" + addition;
                case "finished":
                    return "Finished" + addition;
                default:
                    return "Unknown";
            }
        },

        getStatusVariant(benchmark) {
            if (this.hasErrors(benchmark)) {
                return "danger";
            }
            const status = this.getBenchmarkStatus(benchmark);
            switch (status) {
                case "not_started":
                    return "secondary";
                case "running":
                    return "warning";
                case "finished":
                    return "success";
                default:
                    return "secondary";
            }
        },

        canEdit(benchmark) {
            return this.getBenchmarkStatus(benchmark) === "not_started";
        },

        canStart(benchmark) {
            return (
                this.getBenchmarkStatus(benchmark) === "not_started" &&
                benchmark.models?.length > 0 &&
                benchmark.testCases?.length > 0 &&
                benchmark.metrics?.length > 0
            );
        },
        canReRun(benchmark) {
            return this.getBenchmarkStatus(benchmark) === "finished";
        },

        canRerunMissing(benchmark) {
            return (
                this.getBenchmarkStatus(benchmark) === "finished" &&
                this.hasErrors(benchmark)
            );
        },

        canViewResults(benchmark) {
            return (
                this.getBenchmarkStatus(benchmark) === "finished" ||
                this.getBenchmarkStatus(benchmark) === "running"
            );
        },

        getStatusIcon(benchmark) {
            if (this.hasErrors(benchmark)) {
                return "warning";
            }
            const status = this.getBenchmarkStatus(benchmark);
            switch (status) {
                case "not_started":
                    return "clock";
                case "running":
                    return "play";
                case "finished":
                    return "check-circle";
                default:
                    return "clock";
            }
        },

        getModelCount(benchmark) {
            return benchmark.models?.length || 0;
        },

        getTestCaseCount(benchmark) {
            return benchmark.testCases?.length || 0;
        },

        getMetricCount(benchmark) {
            return benchmark.metrics?.length || 0;
        },

        formatDate(dateString) {
            if (!dateString) return "-";
            const date = new Date(dateString);
            return (
                date.toLocaleDateString() +
                " " +
                date.toLocaleTimeString([], {
                    hour: "2-digit",
                    minute: "2-digit",
                })
            );
        },

        getRunningDuration(benchmark) {
            if (!benchmark.startedAt) return "-";
            const start = new Date(benchmark.startedAt);
            const now = new Date();
            const diffMs = now - start;
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffMins = Math.floor(
                (diffMs % (1000 * 60 * 60)) / (1000 * 60)
            );

            if (diffHours > 0) {
                return `${diffHours}h ${diffMins}m`;
            }
            return `${diffMins}m`;
        },

        hasErrors(benchmark) {
            return benchmark.errors && benchmark.errors.length > 0;
        },

        getETA(benchmark) {
            if (
                !benchmark.startedAt ||
                !benchmark.progress ||
                benchmark.progress <= 0
            ) {
                return null;
            }

            const start = new Date(benchmark.startedAt);
            const now = new Date();
            const elapsedMs = now - start;

            // Calculate estimated total time based on current progress
            const estimatedTotalMs = (elapsedMs / benchmark.progress) * 100;
            const remainingMs = estimatedTotalMs - elapsedMs;

            if (remainingMs <= 0) {
                return null;
            }

            // Calculate ETA datetime
            const eta = new Date(now.getTime() + remainingMs);
            const etaFormatted =
                eta.toLocaleDateString() +
                " " +
                eta.toLocaleTimeString([], {
                    hour: "2-digit",
                    minute: "2-digit",
                });

            // Calculate remaining minutes
            const remainingMinutes = Math.ceil(remainingMs / (1000 * 60));

            return `${etaFormatted} (${remainingMinutes}min left)`;
        },
    },
};
</script>

<style scoped>
.benchmark-card {
    transition: all 0.2s ease;
}

.benchmark-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>
