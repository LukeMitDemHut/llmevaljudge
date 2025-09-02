<template>
    <div class="metric-details">
        <!-- Basic Information Section -->
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
                                >Name</label
                            >
                            <div
                                class="form-control-plaintext bg-light rounded p-2"
                            >
                                {{ metric.name || "N/A" }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-item">
                            <label class="form-label fw-bold text-muted"
                                >Type</label
                            >
                            <div
                                class="form-control-plaintext bg-light rounded p-2"
                            >
                                <span class="badge bg-info text-dark">
                                    {{ getTypeLabel(metric.type) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3" v-if="metric.description">
                    <label class="form-label fw-bold text-muted"
                        >Description</label
                    >
                    <div class="form-control-plaintext bg-light rounded p-2">
                        {{ metric.description }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Evaluation Configuration Section -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <Icon name="chart-line" class="me-2" />
                    Evaluation Configuration
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="detail-item">
                            <label class="form-label fw-bold text-muted"
                                >Evaluation Model</label
                            >
                            <div
                                class="form-control-plaintext bg-light rounded p-2"
                            >
                                {{ metric.ratingModel?.name || "N/A" }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-item">
                            <label class="form-label fw-bold text-muted"
                                >Threshold</label
                            >
                            <div
                                class="form-control-plaintext bg-light rounded p-2"
                            >
                                <span class="badge bg-warning text-dark">
                                    {{ metric.threshold || "N/A" }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Parameters Section -->
        <div class="card mb-4" v-if="metric.param && metric.param.length > 0">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <Icon name="settings" class="me-2" />
                    Parameters
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <span
                        v-for="param in metric.param"
                        :key="param"
                        class="badge bg-secondary fs-6"
                    >
                        {{ param }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Metric Definition Section -->
        <div
            class="card mb-4"
            v-if="
                metric.definition && Object.keys(metric.definition).length > 0
            "
        >
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <Icon name="article" class="me-2" />
                    Metric Definition
                </h5>
            </div>
            <div class="card-body">
                <!-- G-Eval Definition -->
                <div v-if="metric.type === 'g-eval'">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted"
                            >Evaluation Mode</label
                        >
                        <div class="form-control-plaintext">
                            <span class="badge bg-primary">
                                <Icon
                                    :name="
                                        getEvaluationModeIcon(
                                            metric.definition.type
                                        )
                                    "
                                    class="me-1"
                                />
                                {{
                                    getEvaluationModeLabel(
                                        metric.definition.type
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Steps-based Definition -->
                    <div
                        v-if="
                            metric.definition.type === 'steps' &&
                            metric.definition.steps
                        "
                    >
                        <label class="form-label fw-bold text-muted"
                            >Evaluation Steps</label
                        >
                        <div class="steps-list">
                            <div
                                v-for="(step, index) in metric.definition.steps"
                                :key="index"
                                class="step-item d-flex align-items-start mb-2"
                            >
                                <span class="badge bg-secondary me-2 mt-1">{{
                                    index + 1
                                }}</span>
                                <div class="flex-grow-1 bg-light rounded p-2">
                                    {{ step }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Criteria-based Definition -->
                    <div
                        v-if="
                            metric.definition.type === 'criteria' &&
                            metric.definition.criteria
                        "
                    >
                        <label class="form-label fw-bold text-muted"
                            >Evaluation Criteria</label
                        >
                        <div
                            class="form-control-plaintext bg-light rounded p-2"
                        >
                            {{ metric.definition.criteria }}
                        </div>
                    </div>
                </div>

                <!-- DAG Definition -->
                <div v-else-if="metric.type === 'dag'">
                    <div v-if="metric.definition">
                        <!-- Root Node Info -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted"
                                >Root Node</label
                            >
                            <div class="d-flex align-items-center">
                                <span
                                    class="badge me-2"
                                    :class="
                                        getNodeBadgeClass(
                                            metric.definition.node
                                        )
                                    "
                                >
                                    <Icon
                                        :name="
                                            getNodeIcon(metric.definition.node)
                                        "
                                        class="me-1"
                                    />
                                    {{
                                        getNodeTypeLabel(metric.definition.node)
                                    }}
                                </span>
                                <span class="text-muted small">
                                    {{ getNodeDescription(metric.definition) }}
                                </span>
                            </div>
                        </div>

                        <!-- DAG Structure Summary -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted"
                                >Structure Summary</label
                            >
                            <div class="bg-light rounded p-2">
                                <div class="row g-2 text-center">
                                    <div class="col-3">
                                        <div class="small text-muted">
                                            Total Nodes
                                        </div>
                                        <div class="fw-bold">
                                            {{
                                                countTotalNodes(
                                                    metric.definition
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="small text-muted">
                                            Judges
                                        </div>
                                        <div class="fw-bold">
                                            {{
                                                countNodesByType(
                                                    metric.definition,
                                                    [
                                                        "binaryjudge",
                                                        "nonbinaryjudge",
                                                    ]
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="small text-muted">
                                            Verdicts
                                        </div>
                                        <div class="fw-bold">
                                            {{
                                                countNodesByType(
                                                    metric.definition,
                                                    ["verdict", "boolverdict"]
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="small text-muted">
                                            Max Depth
                                        </div>
                                        <div class="fw-bold">
                                            {{
                                                calculateMaxDepth(
                                                    metric.definition
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Key Instructions/Criteria (if any) -->
                        <div
                            v-if="getRootInstructions(metric.definition)"
                            class="mb-3"
                        >
                            <label class="form-label fw-bold text-muted"
                                >Primary Instructions</label
                            >
                            <div
                                class="form-control-plaintext bg-light rounded p-2 small"
                            >
                                {{ getRootInstructions(metric.definition) }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="alert alert-warning">
                        <Icon name="warning" class="me-1" />
                        DAG definition is empty or not configured.
                    </div>
                </div>

                <!-- TALE Definition -->
                <div v-else-if="metric.type === 'tale'">
                    <div v-if="metric.definition">
                        <!-- Task -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted"
                                >Task</label
                            >
                            <div
                                class="form-control-plaintext bg-light rounded p-2"
                            >
                                {{
                                    metric.definition.task ||
                                    "No task specified"
                                }}
                            </div>
                        </div>

                        <!-- Configuration Summary -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted"
                                >Configuration</label
                            >
                            <div class="bg-light rounded p-2">
                                <div class="row g-2 text-center">
                                    <div class="col-4">
                                        <div class="small text-muted">
                                            Max Search Results
                                        </div>
                                        <div class="fw-bold">
                                            <span class="badge bg-info">
                                                {{
                                                    metric.definition
                                                        .max_search_results || 5
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-muted">
                                            Max Iterations
                                        </div>
                                        <div class="fw-bold">
                                            <span class="badge bg-info">
                                                {{
                                                    metric.definition
                                                        .max_iterations || 3
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-muted">
                                            Time Range
                                        </div>
                                        <div class="fw-bold">
                                            <span
                                                class="badge bg-warning text-dark"
                                            >
                                                {{
                                                    getTimeRangeLabel(
                                                        metric.definition
                                                            .time_range
                                                    ) || "All Time"
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search Engines -->
                        <div
                            class="mb-3"
                            v-if="
                                metric.definition.search_engines &&
                                metric.definition.search_engines.length > 0
                            "
                        >
                            <label class="form-label fw-bold text-muted"
                                >Search Engines</label
                            >
                            <div class="d-flex flex-wrap gap-2">
                                <span
                                    v-for="engine in metric.definition
                                        .search_engines"
                                    :key="engine"
                                    class="badge bg-secondary fs-6"
                                >
                                    {{ formatEngineLabel(engine) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="alert alert-warning">
                        <Icon name="warning" class="me-1" />
                        TALE definition is empty or not configured.
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state if no definition -->
        <div
            v-if="
                !metric.definition ||
                Object.keys(metric.definition).length === 0
            "
            class="card"
        >
            <div class="card-body text-center text-muted">
                <Icon name="article" size="3x" class="mb-3 opacity-50" />
                <p class="mb-0">No metric definition configured</p>
            </div>
        </div>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";
import {
    formatEngineLabel,
    getTimeRangeLabel,
} from "@constants/taleConstants.js";

export default {
    name: "DetailsMetricModal",
    components: {
        Icon,
    },
    props: {
        metric: {
            type: Object,
            required: true,
        },
    },
    methods: {
        getTypeLabel(type) {
            const typeLabels = {
                "g-eval": "G-Eval",
                dag: "DAG",
                tale: "TALE",
            };
            return typeLabels[type] || type || "Unknown";
        },
        getEvaluationModeLabel(mode) {
            const modeLabels = {
                steps: "Steps-based",
                criteria: "Criteria-based",
            };
            return modeLabels[mode] || mode || "Unknown";
        },
        getEvaluationModeIcon(mode) {
            const modeIcons = {
                steps: "check-square",
                criteria: "article",
            };
            return modeIcons[mode] || "question-circle";
        },
        // DAG-related helper methods
        getNodeTypeLabel(nodeType) {
            const labels = {
                tasknode: "Task Node",
                binaryjudge: "Binary Judge",
                nonbinaryjudge: "Non-Binary Judge",
                verdict: "Verdict",
                boolverdict: "Boolean Verdict",
            };
            return labels[nodeType] || nodeType || "Unknown";
        },
        getNodeIcon(nodeType) {
            const icons = {
                tasknode: "tasks",
                binaryjudge: "check-circle",
                nonbinaryjudge: "list-check",
                verdict: "bookmark",
                boolverdict: "toggle-on",
            };
            return icons[nodeType] || "circle";
        },
        getNodeBadgeClass(nodeType) {
            const classes = {
                tasknode: "bg-primary",
                binaryjudge: "bg-warning text-dark",
                nonbinaryjudge: "bg-info text-dark",
                verdict: "bg-success",
                boolverdict: "bg-secondary",
            };
            return classes[nodeType] || "bg-light text-dark";
        },
        getNodeDescription(node) {
            if (node.instructions)
                return (
                    node.instructions.substring(0, 60) +
                    (node.instructions.length > 60 ? "..." : "")
                );
            if (node.criteria)
                return (
                    node.criteria.substring(0, 60) +
                    (node.criteria.length > 60 ? "..." : "")
                );
            if (node.verdict !== undefined) return `Verdict: ${node.verdict}`;
            return "No description available";
        },
        getRootInstructions(definition) {
            if (definition.instructions) return definition.instructions;
            if (definition.criteria) return definition.criteria;
            return null;
        },
        countTotalNodes(node) {
            if (!node) return 0;
            let count = 1;
            if (node.children && node.children.length > 0) {
                for (const child of node.children) {
                    count += this.countTotalNodes(child);
                }
            }
            return count;
        },
        countNodesByType(node, types) {
            if (!node) return 0;
            let count = types.includes(node.node) ? 1 : 0;
            if (node.children && node.children.length > 0) {
                for (const child of node.children) {
                    count += this.countNodesByType(child, types);
                }
            }
            return count;
        },
        calculateMaxDepth(node, currentDepth = 1) {
            if (!node || !node.children || node.children.length === 0) {
                return currentDepth;
            }
            let maxChildDepth = currentDepth;
            for (const child of node.children) {
                const childDepth = this.calculateMaxDepth(
                    child,
                    currentDepth + 1
                );
                maxChildDepth = Math.max(maxChildDepth, childDepth);
            }
            return maxChildDepth;
        },
        formatEngineLabel,
        getTimeRangeLabel,
    },
};
</script>

<style scoped>
.metric-details {
    max-height: 70vh;
    overflow-y: auto;
}

.detail-item {
    margin-bottom: 0;
}

.form-control-plaintext {
    min-height: 38px;
    display: flex;
    align-items: center;
}

.step-item {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.steps-list {
    max-height: 300px;
    overflow-y: auto;
}

.card-header h5 {
    display: flex;
    align-items: center;
}

.badge {
    font-size: 0.85em;
}
</style>
