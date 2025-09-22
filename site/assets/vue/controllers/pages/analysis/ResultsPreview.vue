<template>
    <div class="card shadow-sm border-0">
        <div class="card-header">
            <h6 class="mb-0">
                <Icon
                    :name="title === 'Best' ? 'trophy' : 'alert-triangle'"
                    :class="title === 'Best' ? 'text-success' : 'text-danger'"
                    class="me-2"
                />
                {{ title }} Results
            </h6>
            <p class="text-muted small mb-0">
                {{ title === "Best" ? "Top performing" : "Lowest performing" }}
                test cases
            </p>
        </div>
        <div class="card-body">
            <div v-if="results?.length" class="results-list">
                <div
                    v-for="result in results"
                    :key="result.id"
                    class="result-item d-flex justify-content-between align-items-center p-3 mb-2 border rounded cursor-pointer"
                    :class="resultItemClass"
                    @click="onResultClick(result)"
                    @mouseover="hoveredResult = result.id"
                    @mouseleave="hoveredResult = null"
                >
                    <div class="result-info">
                        <div class="result-id fw-semibold">
                            <Icon name="file-text" class="me-1 text-muted" />
                            Result #{{ result.id }}
                        </div>
                        <div class="result-meta text-muted small">
                            Click to view details
                        </div>
                    </div>
                    <div class="result-score">
                        <span
                            class="badge fs-6 px-3 py-2"
                            :class="getScoreBadgeClass(result.score)"
                        >
                            {{ formatScore(result.score) }}
                        </span>
                    </div>
                </div>
            </div>
            <div v-else class="text-center text-muted py-4">
                <Icon name="inbox" class="fs-1 mb-2" />
                <div>No {{ title.toLowerCase() }} results available</div>
            </div>
        </div>

        <!-- Result Details Modal -->
        <Modal
            v-if="showModal"
            :visible="showModal"
            @close="closeModal"
            :title="modalTitle"
            :showFooter="false"
        >
            <ResultDetailsModal
                v-if="selectedResultId"
                :result-id="selectedResultId"
            />
        </Modal>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";
import Modal from "@components/structure/Modal.vue";
import ResultDetailsModal from "./ResultDetailsModal.vue";

export default {
    name: "ResultsPreview",
    components: {
        Icon,
        Modal,
        ResultDetailsModal,
    },
    props: {
        title: {
            type: String,
            required: true,
            validator(value) {
                return ["Best", "Worst"].includes(value);
            },
        },
        results: {
            type: Array,
            required: true,
            default: () => [],
        },
    },
    data() {
        return {
            hoveredResult: null,
            showModal: false,
            selectedResultId: null,
        };
    },
    computed: {
        resultItemClass() {
            return this.title === "Best"
                ? "border-success-subtle bg-success-subtle"
                : "border-danger-subtle bg-danger-subtle";
        },
        modalTitle() {
            return this.selectedResultId
                ? `Result Details #${this.selectedResultId}`
                : "Result Details";
        },
    },
    methods: {
        formatScore(score) {
            if (score === null || score === undefined) return "N/A";
            return Number(score).toFixed(3);
        },
        getScoreBadgeClass(score) {
            if (this.title === "Best") {
                return "bg-success text-white";
            } else {
                return "bg-danger text-white";
            }
        },
        onResultClick(result) {
            this.selectedResultId = result.id;
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.selectedResultId = null;
        },
    },
};
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}

.result-item {
    transition: all 0.2s ease-in-out;
}

.result-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.results-list {
    max-height: 400px;
    overflow-y: auto;
}

.result-id {
    font-size: 0.95rem;
}

.result-meta {
    font-size: 0.8rem;
}

.badge {
    min-width: 60px;
}
</style>
