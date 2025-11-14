<template>
    <div class="result-details-modal">
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3">Loading result details...</div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="alert alert-danger">
            <Icon name="alert-triangle" class="me-2" />
            <strong>Error loading result details:</strong> {{ error }}
        </div>

        <!-- Result Details -->
        <div v-else-if="resultData" class="result-details">
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <Icon name="info-circle" class="me-2" />
                        Basic Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="detail-item">
                                <label class="form-label fw-bold text-muted"
                                    >Result ID</label
                                >
                                <div
                                    class="form-control-plaintext bg-light rounded p-2"
                                >
                                    #{{ resultData.id }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item">
                                <label class="form-label fw-bold text-muted"
                                    >Score</label
                                >
                                <div
                                    class="form-control-plaintext bg-light rounded p-2"
                                >
                                    <span
                                        class="badge fs-6 px-3 py-2"
                                        :class="
                                            getScoreBadgeClass(resultData.score)
                                        "
                                    >
                                        {{ formatScore(resultData.score) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prompt Details -->
            <div class="card mb-4" v-if="resultData.prompt">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <Icon name="chat-text" class="me-2" />
                        Prompt Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3" v-if="resultData.prompt.testCase">
                        <label class="form-label fw-bold text-muted"
                            >Test Case</label
                        >
                        <div
                            class="form-control-plaintext bg-light rounded p-2"
                        >
                            {{ resultData.prompt.testCase.name }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted"
                            >Input</label
                        >
                        <div
                            class="form-control-plaintext bg-light rounded p-3 preserved-text"
                            style="max-height: 200px; overflow-y: auto"
                        >
                            <pre class="mb-0">{{
                                resultData.prompt.input
                            }}</pre>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted"
                            >Actual Output</label
                        >
                        <div
                            class="form-control-plaintext bg-light rounded p-3 preserved-text"
                            style="max-height: 200px; overflow-y: auto"
                        >
                            <pre class="mb-0">{{
                                resultData.actualOutput
                            }}</pre>
                        </div>
                    </div>

                    <div class="mb-3" v-if="resultData.prompt.expectedOutput">
                        <label class="form-label fw-bold text-muted"
                            >Expected Output</label
                        >
                        <div
                            class="form-control-plaintext bg-light rounded p-3 preserved-text"
                            style="max-height: 200px; overflow-y: auto"
                        >
                            <pre class="mb-0">{{
                                resultData.prompt.expectedOutput
                            }}</pre>
                        </div>
                    </div>

                    <div class="mb-3" v-if="resultData.prompt.context">
                        <label class="form-label fw-bold text-muted"
                            >Context</label
                        >
                        <div
                            class="form-control-plaintext bg-light rounded p-3 preserved-text"
                            style="max-height: 200px; overflow-y: auto"
                        >
                            <pre class="mb-0">{{
                                resultData.prompt.context
                            }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reason -->
            <div class="card mb-4" v-if="resultData.reason">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <Icon name="message-circle" class="me-2" />
                        Evaluation Reason
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border">
                        <Icon name="info-circle" class="me-2 text-info" />
                        {{ resultData.reason }}
                    </div>
                </div>
            </div>

            <!-- Execution Logs -->
            <div class="card mb-4" v-if="executionLogs.length > 0">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <Icon name="list" class="me-2" />
                        Execution Logs
                    </h5>
                    <small class="text-muted"
                        >{{ executionLogs.length }} log entries</small
                    >
                </div>
                <div class="card-body p-0">
                    <div
                        class="execution-logs"
                        style="max-height: 400px; overflow-y: auto"
                    >
                        <div
                            v-for="(log, index) in executionLogs"
                            :key="index"
                            class="log-entry border-bottom p-3"
                            :class="getLogLevelClass(log.level)"
                        >
                            <div
                                class="d-flex justify-content-between align-items-start"
                            >
                                <div class="log-content flex-grow-1">
                                    <div class="d-flex align-items-center mb-1">
                                        <span
                                            class="badge me-2"
                                            :class="getLogBadgeClass(log.level)"
                                        >
                                            {{ log.level.toUpperCase() }}
                                        </span>
                                        <span class="fw-semibold text-muted">{{
                                            log.component
                                        }}</span>
                                    </div>
                                    <div class="log-message">
                                        <pre class="log-message-content">{{
                                            log.message
                                        }}</pre>
                                    </div>
                                    <div
                                        v-if="
                                            log.data &&
                                            Object.keys(log.data).length > 0
                                        "
                                        class="log-data mt-2"
                                    >
                                        <small class="text-muted">
                                            <strong>Data:</strong>
                                            <div class="log-data-content">
                                                {{ formatLogData(log.data) }}
                                            </div>
                                        </small>
                                    </div>
                                </div>
                                <small class="text-muted ms-2">{{
                                    formatTimestamp(log.timestamp)
                                }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";

export default {
    name: "ResultDetailsModal",
    components: {
        Icon,
    },
    props: {
        resultId: {
            type: Number,
            required: true,
        },
    },
    data() {
        return {
            loading: false,
            error: null,
            resultData: null,
        };
    },
    computed: {
        executionLogs() {
            if (!this.resultData || !this.resultData.logs) {
                return [];
            }

            try {
                const logs = JSON.parse(this.resultData.logs);
                return Array.isArray(logs) ? logs : [];
            } catch (e) {
                console.error("Error parsing execution logs:", e);
                return [];
            }
        },
    },
    async mounted() {
        await this.fetchResultDetails();
    },
    methods: {
        async fetchResultDetails() {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch(`/api/results/${this.resultId}`);

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                this.resultData = await response.json();
            } catch (error) {
                console.error("Error fetching result details:", error);
                this.error = error.message || "Failed to load result details";
            } finally {
                this.loading = false;
            }
        },

        formatScore(score) {
            if (score === null || score === undefined) return "N/A";
            return Number(score).toFixed(3);
        },

        getScoreBadgeClass(score) {
            if (score >= 0.8) return "bg-success text-white";
            if (score >= 0.6) return "bg-primary text-white";
            if (score >= 0.4) return "bg-warning text-dark";
            return "bg-danger text-white";
        },

        getScoreTextClass(score) {
            if (score >= 0.8) return "text-success";
            if (score >= 0.6) return "text-primary";
            if (score >= 0.4) return "text-warning";
            return "text-danger";
        },

        getLogLevelClass(level) {
            const classes = {
                error: "border-start border-danger border-3",
                warning: "border-start border-warning border-3",
                info: "border-start border-info border-3",
                debug: "border-start border-secondary border-3",
                conversation: "border-start border-primary border-3",
                decision: "border-start border-success border-3",
            };
            return classes[level] || "";
        },

        getLogBadgeClass(level) {
            const classes = {
                error: "bg-danger text-white",
                warning: "bg-warning text-dark",
                info: "bg-info text-white",
                debug: "bg-secondary text-white",
                conversation: "bg-primary text-white",
                decision: "bg-success text-white",
            };
            return classes[level] || "bg-light text-dark";
        },

        formatTimestamp(timestamp) {
            try {
                return new Date(timestamp).toLocaleTimeString();
            } catch (e) {
                return timestamp;
            }
        },

        formatLogData(data) {
            if (!data || typeof data !== "object") return "";

            const keys = Object.keys(data).slice(0, 5); // Show first 5 keys
            const formatted = keys.map((key) => `${key}: ${String(data[key])}`);

            if (Object.keys(data).length > 5) {
                formatted.push(`... and ${Object.keys(data).length - 5} more`);
            }

            return formatted.join(", ");
        },
    },
};
</script>

<style scoped>
.result-details-modal {
    max-height: 80vh;
    overflow-y: auto;
}

.detail-item {
    margin-bottom: 1rem;
}

.log-entry {
    transition: background-color 0.2s ease;
}

.log-entry:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.log-message {
    font-size: 0.9rem;
    line-height: 1.4;
}

.log-message-content {
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
    padding: 0;
    background: none;
    border: none;
    font-family: inherit;
    font-size: inherit;
    max-height: 300px;
    overflow-y: auto;
    overflow-x: hidden;
}

.log-data {
    font-family: monospace;
    font-size: 0.8rem;
    color: #6c757d;
}

.log-data-content {
    max-height: 200px;
    overflow-y: auto;
    overflow-x: hidden;
    white-space: pre-wrap;
    word-break: break-word;
    margin-top: 0.25rem;
    padding: 0.5rem;
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: 0.25rem;
    font-family: "Courier New", Courier, monospace;
}

.execution-logs {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}

pre {
    white-space: pre-wrap;
    word-break: break-word;
    font-family: "Courier New", Courier, monospace;
    font-size: 0.875rem;
    line-height: 1.4;
}

.preserved-text pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.evaluation-summary .h4 {
    font-weight: 700;
}

.evaluation-summary .h6 {
    font-weight: 600;
}

.card-header h5 {
    display: flex;
    align-items: center;
}

.border-3 {
    border-width: 3px !important;
}
</style>
