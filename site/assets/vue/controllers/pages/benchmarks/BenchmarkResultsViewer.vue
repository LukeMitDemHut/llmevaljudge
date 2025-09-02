<template>
    <div class="benchmark-results-viewer">
        <div v-if="loading" class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading results...</span>
            </div>
        </div>

        <div
            v-else-if="!results || results.length === 0"
            class="text-center py-4"
        >
            <EmptyState
                title="No results available"
                description="This benchmark hasn't generated any results yet."
                icon="chart-line-up"
            />
        </div>

        <div v-else>
            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <label class="form-label">Model</label>
                    <select v-model="selectedModel" class="form-select">
                        <option value="">All Models</option>
                        <option
                            v-for="model in availableModels"
                            :key="model.id"
                            :value="model.id"
                        >
                            {{ model.name }}
                        </option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Metric</label>
                    <select v-model="selectedMetric" class="form-select">
                        <option value="">All Metrics</option>
                        <option
                            v-for="metric in availableMetrics"
                            :key="metric.id"
                            :value="metric.id"
                        >
                            {{ metric.name }}
                        </option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Test Case</label>
                    <select v-model="selectedTestCase" class="form-select">
                        <option value="">All Test Cases</option>
                        <option
                            v-for="testCase in availableTestCases"
                            :key="testCase.id"
                            :value="testCase.id"
                        >
                            {{ testCase.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <Card class="text-center">
                        <h6 class="text-muted">Average Score</h6>
                        <h3 class="text-primary">{{ averageScore }}%</h3>
                    </Card>
                </div>
                <div class="col-md-3">
                    <Card class="text-center">
                        <h6 class="text-muted">Total Results</h6>
                        <h3 class="text-info">{{ filteredResults.length }}</h3>
                    </Card>
                </div>
                <div class="col-md-3">
                    <Card class="text-center">
                        <h6 class="text-muted">Models Tested</h6>
                        <h3 class="text-success">{{ uniqueModels }}</h3>
                    </Card>
                </div>
                <div class="col-md-3">
                    <Card class="text-center">
                        <h6 class="text-muted">Prompts Evaluated</h6>
                        <h3 class="text-warning">{{ uniquePrompts }}</h3>
                    </Card>
                </div>
            </div>

            <!-- Prompt Navigation (when filtered) -->
            <div v-if="promptsToShow.length > 0" class="mb-4">
                <h5 class="mb-3">Prompts</h5>
                <div class="prompt-navigation">
                    <div class="row">
                        <div
                            v-for="(prompt, index) in promptsToShow"
                            :key="prompt.id"
                            class="col-12 col-md-6 col-lg-4 mb-3"
                        >
                            <Card
                                class="cursor-pointer prompt-card"
                                :class="{
                                    'border-primary bg-primary bg-opacity-10':
                                        selectedPrompt?.id === prompt.id,
                                }"
                                @click="selectPrompt(prompt)"
                            >
                                <div
                                    class="d-flex justify-content-between align-items-start"
                                >
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            Prompt {{ index + 1 }}
                                        </h6>
                                        <small class="text-muted d-block mb-2">
                                            {{ prompt.testCase?.name }}
                                        </small>
                                        <div
                                            class="small text-truncate"
                                            style="
                                                max-height: 2.5em;
                                                overflow: hidden;
                                            "
                                        >
                                            {{ prompt.input || "No input" }}
                                        </div>
                                    </div>
                                    <div class="ms-2">
                                        <span
                                            class="badge"
                                            :class="
                                                getScoreBadgeClass(
                                                    getPromptAverageScore(
                                                        prompt
                                                    )
                                                )
                                            "
                                        >
                                            {{ getPromptAverageScore(prompt) }}%
                                        </span>
                                    </div>
                                </div>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selected Prompt Results -->
            <div v-if="selectedPrompt && promptResults.length > 0" class="mb-4">
                <h5 class="mb-3">
                    Results for Prompt: {{ getPromptTitle(selectedPrompt) }}
                </h5>

                <!-- Prompt Details -->
                <Card class="mb-3">
                    <h6>Input</h6>
                    <div class="bg-light p-3 rounded">
                        <code>{{
                            selectedPrompt.input || "No input provided"
                        }}</code>
                    </div>
                    <div v-if="selectedPrompt.expectedOutput" class="mt-3">
                        <h6>Expected Output</h6>
                        <div class="bg-light p-3 rounded">
                            <code>{{ selectedPrompt.expectedOutput }}</code>
                        </div>
                    </div>
                    <div v-if="selectedPrompt.context" class="mt-3">
                        <h6>Context</h6>
                        <div class="bg-light p-3 rounded">
                            <code>{{ selectedPrompt.context }}</code>
                        </div>
                    </div>
                </Card>

                <!-- Results for this prompt -->
                <div class="row">
                    <div
                        v-for="result in promptResults"
                        :key="result.id"
                        class="col-12 mb-3"
                    >
                        <Card>
                            <div
                                class="d-flex justify-content-between align-items-start mb-3"
                            >
                                <div>
                                    <h6 class="mb-1">
                                        {{ result.model.name }}
                                        <span class="text-muted">with</span>
                                        {{ result.metric.name }}
                                    </h6>
                                    <small class="text-muted">
                                        Provider:
                                        {{ result.model.provider?.name }}
                                    </small>
                                </div>
                                <div>
                                    <span
                                        class="badge fs-6"
                                        :class="
                                            getScoreBadgeClass(
                                                result.score * 100
                                            )
                                        "
                                    >
                                        {{ Math.round(result.score * 100) }}%
                                    </span>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Actual Output</h6>
                                    <div
                                        class="bg-light p-3 rounded"
                                        style="
                                            max-height: 200px;
                                            overflow-y: auto;
                                        "
                                    >
                                        <small
                                            ><code>{{
                                                result.actualOutput
                                            }}</code></small
                                        >
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Reasoning</h6>
                                    <div
                                        class="bg-light p-3 rounded"
                                        style="
                                            max-height: 200px;
                                            overflow-y: auto;
                                        "
                                    >
                                        <small>{{
                                            result.reason ||
                                            "No reasoning provided"
                                        }}</small>
                                    </div>
                                </div>
                            </div>

                            <div v-if="result.logs" class="mt-3">
                                <Button
                                    variant="outline-secondary"
                                    size="sm"
                                    @click="toggleLogs(result.id)"
                                >
                                    {{
                                        showingLogs.includes(result.id)
                                            ? "Hide"
                                            : "Show"
                                    }}
                                    Logs
                                </Button>
                                <div
                                    v-if="showingLogs.includes(result.id)"
                                    class="mt-2 bg-dark text-light p-3 rounded"
                                    style="max-height: 300px; overflow-y: auto"
                                >
                                    <pre
                                        class="mb-0 text-light"
                                    ><code>{{ result.logs }}</code></pre>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- All Results Table (when no specific prompt selected) -->
            <div v-else-if="!selectedPrompt && filteredResults.length > 0">
                <h5 class="mb-3">All Results</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Metric</th>
                                <th>Test Case</th>
                                <th>Prompt</th>
                                <th>Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="result in paginatedResults"
                                :key="result.id"
                            >
                                <td>{{ result.model.name }}</td>
                                <td>{{ result.metric.name }}</td>
                                <td>
                                    {{
                                        result.prompt.testCase?.name ||
                                        "Unknown"
                                    }}
                                </td>
                                <td
                                    class="text-truncate"
                                    style="max-width: 200px"
                                >
                                    {{ result.prompt.input || "No input" }}
                                </td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="
                                            getScoreBadgeClass(
                                                result.score * 100
                                            )
                                        "
                                    >
                                        {{ Math.round(result.score * 100) }}%
                                    </span>
                                </td>
                                <td>
                                    <Button
                                        variant="outline-primary"
                                        size="sm"
                                        @click="selectPrompt(result.prompt)"
                                    >
                                        View Details
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav v-if="totalPages > 1" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li
                            class="page-item"
                            :class="{ disabled: currentPage === 1 }"
                        >
                            <a
                                class="page-link"
                                href="#"
                                @click.prevent="currentPage = 1"
                                >First</a
                            >
                        </li>
                        <li
                            class="page-item"
                            :class="{ disabled: currentPage === 1 }"
                        >
                            <a
                                class="page-link"
                                href="#"
                                @click.prevent="currentPage--"
                                >Previous</a
                            >
                        </li>
                        <li
                            v-for="page in visiblePages"
                            :key="page"
                            class="page-item"
                            :class="{ active: currentPage === page }"
                        >
                            <a
                                class="page-link"
                                href="#"
                                @click.prevent="currentPage = page"
                                >{{ page }}</a
                            >
                        </li>
                        <li
                            class="page-item"
                            :class="{ disabled: currentPage === totalPages }"
                        >
                            <a
                                class="page-link"
                                href="#"
                                @click.prevent="currentPage++"
                                >Next</a
                            >
                        </li>
                        <li
                            class="page-item"
                            :class="{ disabled: currentPage === totalPages }"
                        >
                            <a
                                class="page-link"
                                href="#"
                                @click.prevent="currentPage = totalPages"
                                >Last</a
                            >
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Errors Section -->
        <div v-if="hasErrors" class="mb-4">
            <Card>
                <div class="d-flex align-items-center mb-3">
                    <Icon
                        name="exclamation-triangle"
                        class="text-danger me-2"
                    />
                    <h5 class="mb-0 text-danger">Errors from Last Run</h5>
                </div>
                <div
                    v-for="(error, index) in benchmark.errors"
                    :key="index"
                    class="mb-2"
                >
                    <div class="alert alert-danger mb-2">
                        <small class="text-muted d-block mb-1">
                            {{ formatDateTime(error.timestamp) }}
                        </small>
                        {{ error.message }}
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>

<script>
import { api } from "@services/ApiService.js";
import EmptyState from "@components/structure/EmptyState.vue";
import Card from "@components/structure/Card.vue";
import Button from "@components/interactables/Button.vue";

export default {
    name: "BenchmarkResultsViewer",
    components: {
        EmptyState,
        Card,
        Button,
    },
    props: {
        benchmark: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            loading: false,
            results: [],
            selectedModel: "",
            selectedMetric: "",
            selectedTestCase: "",
            selectedPrompt: null,
            showingLogs: [],
            currentPage: 1,
            itemsPerPage: 10,
        };
    },
    computed: {
        availableModels() {
            const models = new Map();
            this.results.forEach((result) => {
                if (result.model) {
                    models.set(result.model.id, result.model);
                }
            });
            return Array.from(models.values());
        },

        availableMetrics() {
            const metrics = new Map();
            this.results.forEach((result) => {
                if (result.metric) {
                    metrics.set(result.metric.id, result.metric);
                }
            });
            return Array.from(metrics.values());
        },

        availableTestCases() {
            const testCases = new Map();
            this.results.forEach((result) => {
                if (result.prompt?.testCase) {
                    testCases.set(
                        result.prompt.testCase.id,
                        result.prompt.testCase
                    );
                }
            });
            return Array.from(testCases.values());
        },

        filteredResults() {
            let filtered = [...this.results];

            if (this.selectedModel) {
                filtered = filtered.filter(
                    (r) => r.model?.id == this.selectedModel
                );
            }

            if (this.selectedMetric) {
                filtered = filtered.filter(
                    (r) => r.metric?.id == this.selectedMetric
                );
            }

            if (this.selectedTestCase) {
                filtered = filtered.filter(
                    (r) => r.prompt?.testCase?.id == this.selectedTestCase
                );
            }

            return filtered;
        },

        promptsToShow() {
            if (
                this.selectedModel ||
                this.selectedMetric ||
                this.selectedTestCase
            ) {
                const prompts = new Map();
                this.filteredResults.forEach((result) => {
                    if (result.prompt) {
                        prompts.set(result.prompt.id, result.prompt);
                    }
                });
                return Array.from(prompts.values());
            }
            return [];
        },

        promptResults() {
            if (!this.selectedPrompt) return [];

            return this.filteredResults.filter(
                (r) => r.prompt?.id === this.selectedPrompt.id
            );
        },

        averageScore() {
            if (this.filteredResults.length === 0) return 0;
            const sum = this.filteredResults.reduce(
                (acc, result) => acc + (result.score || 0),
                0
            );
            return Math.round((sum / this.filteredResults.length) * 100);
        },

        uniqueModels() {
            return new Set(
                this.filteredResults.map((r) => r.model?.id).filter(Boolean)
            ).size;
        },

        uniquePrompts() {
            return new Set(
                this.filteredResults.map((r) => r.prompt?.id).filter(Boolean)
            ).size;
        },

        paginatedResults() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredResults.slice(start, end);
        },

        totalPages() {
            return Math.ceil(this.filteredResults.length / this.itemsPerPage);
        },

        visiblePages() {
            const pages = [];
            const start = Math.max(1, this.currentPage - 2);
            const end = Math.min(this.totalPages, this.currentPage + 2);

            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            return pages;
        },

        hasErrors() {
            return this.benchmark.errors && this.benchmark.errors.length > 0;
        },
    },

    async mounted() {
        await this.loadResults();
    },

    watch: {
        selectedModel() {
            this.selectedPrompt = null;
            this.currentPage = 1;
        },
        selectedMetric() {
            this.selectedPrompt = null;
            this.currentPage = 1;
        },
        selectedTestCase() {
            this.selectedPrompt = null;
            this.currentPage = 1;
        },
    },

    methods: {
        async loadResults() {
            this.loading = true;
            try {
                // Load benchmark results using the ApiService
                const results = await api.results.getByBenchmark(
                    this.benchmark.id
                );
                this.results = results || [];
            } catch (error) {
                console.error("Failed to load results:", error);
                this.results = [];
            } finally {
                this.loading = false;
            }
        },

        selectPrompt(prompt) {
            this.selectedPrompt = prompt;
        },

        getPromptTitle(prompt) {
            const input = prompt.input || "No input";
            return input.length > 50 ? input.substring(0, 50) + "..." : input;
        },

        getPromptAverageScore(prompt) {
            const promptResults = this.filteredResults.filter(
                (r) => r.prompt?.id === prompt.id
            );
            if (promptResults.length === 0) return 0;

            const sum = promptResults.reduce(
                (acc, result) => acc + (result.score || 0),
                0
            );
            return Math.round((sum / promptResults.length) * 100);
        },

        getScoreBadgeClass(score) {
            if (score >= 80) return "bg-success";
            if (score >= 60) return "bg-warning";
            return "bg-danger";
        },

        toggleLogs(resultId) {
            const index = this.showingLogs.indexOf(resultId);
            if (index > -1) {
                this.showingLogs.splice(index, 1);
            } else {
                this.showingLogs.push(resultId);
            }
        },

        formatDateTime(dateString) {
            if (!dateString) return "-";
            return new Date(dateString).toLocaleString();
        },
    },
};
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}

.prompt-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
