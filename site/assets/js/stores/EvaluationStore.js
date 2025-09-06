import { defineStore } from "pinia";
import { api } from "@services/ApiService.js";

export const useEvaluationStore = defineStore("evaluation", {
    state: () => ({
        errors: {},
        availableDimensions: [
            {
                id: "model",
                label: "Models",
            },
            {
                id: "metric",
                label: "Metrics",
            },
            {
                id: "test_case",
                label: "Test Cases",
            },
            {
                id: "benchmark",
                label: "Benchmarks",
            },
            {
                id: "prompt",
                label: "Prompts",
            },
        ],
        data: {
            models: [],
            metrics: [],
            testCases: [],
            benchmarks: [],
            prompts: [],
        },
        loading: {
            models: false,
            metrics: false,
            testCases: false,
            benchmarks: false,
            prompts: false,
            evaluation: false,
        },
        evaluation: {
            data: [],
            summary: null,
            isLoaded: false,
        },
    }),

    getters: {
        getAvailableDimensions() {
            return this.availableDimensions;
        },
        getAvailableModels() {
            return this.data.models.map((model) => ({
                id: model.id,
                label: `[${model.id}] ${model.name}`,
            }));
        },
        getAvailableMetrics() {
            return this.data.metrics.map((metric) => ({
                id: metric.id,
                label: `[${metric.id}] ${metric.name}`,
            }));
        },
        getAvailableTestCases() {
            return this.data.testCases.map((testCase) => ({
                id: testCase.id,
                label: `[${testCase.id}] ${testCase.name}`,
            }));
        },
        getAvailableBenchmarks() {
            return this.data.benchmarks.map((benchmark) => ({
                id: benchmark.id,
                label: `[${benchmark.id}] Benchmark ${benchmark.id} (${
                    benchmark.testCases?.length || 0
                } test cases)`,
            }));
        },
        getAvailablePrompts() {
            return this.data.prompts.map((prompt) => {
                // Truncate long inputs for display
                const truncatedInput =
                    prompt.input && prompt.input.length > 80
                        ? prompt.input.substring(0, 80) + "..."
                        : prompt.input || "No input";

                return {
                    id: prompt.id,
                    label: `[${prompt.id}] ${truncatedInput}`,
                };
            });
        },
        isAnyLoading() {
            return Object.values(this.loading).some((loading) => loading);
        },
        getDedupeOptions() {
            return [
                {
                    id: "latest",
                    label: "Latest - Use only the most recent results",
                },
                {
                    id: "avg",
                    label: "Average - Calculate average across all runs",
                },
                {
                    id: "all",
                    label: "All - Include all results from different runs",
                },
            ];
        },
        isEvaluationValid() {
            return this.evaluation.isLoaded && this.evaluation.data.length > 0;
        },
    },

    actions: {
        // Data loading methods
        async loadModels() {
            if (this.data.models.length > 0) return;

            this.loading.models = true;
            try {
                const response = await api.models.getAll({
                    paginate: false,
                });
                this.data.models = Array.isArray(response)
                    ? response
                    : response.data || [];
            } catch (error) {
                this.handleError(error);
                console.error("Error loading models:", error);
            } finally {
                this.loading.models = false;
            }
        },

        async loadMetrics() {
            if (this.data.metrics.length > 0) return;

            this.loading.metrics = true;
            try {
                const response = await api.metrics.getAll({
                    paginate: false,
                });
                this.data.metrics = Array.isArray(response)
                    ? response
                    : response.data || [];
            } catch (error) {
                this.handleError(error);
                console.error("Error loading metrics:", error);
            } finally {
                this.loading.metrics = false;
            }
        },

        async loadTestCases() {
            if (this.data.testCases.length > 0) return;

            this.loading.testCases = true;
            try {
                const response = await api.testCases.getAll({
                    paginate: false,
                });
                this.data.testCases = Array.isArray(response)
                    ? response
                    : response.data || [];
            } catch (error) {
                this.handleError(error);
                console.error("Error loading test cases:", error);
            } finally {
                this.loading.testCases = false;
            }
        },

        async loadBenchmarks() {
            if (this.data.benchmarks.length > 0) return;

            this.loading.benchmarks = true;
            try {
                const response = await api.benchmarks.getAll({
                    paginate: false,
                });
                this.data.benchmarks = Array.isArray(response)
                    ? response
                    : response.data || [];
            } catch (error) {
                this.handleError(error);
                console.error("Error loading benchmarks:", error);
            } finally {
                this.loading.benchmarks = false;
            }
        },

        async loadPrompts() {
            if (this.data.prompts.length > 0) return;

            this.loading.prompts = true;
            try {
                const response = await api.prompts.getAll({
                    paginate: false,
                });
                this.data.prompts = Array.isArray(response)
                    ? response
                    : response.data || [];
            } catch (error) {
                this.handleError(error);
                console.error("Error loading prompts:", error);
            } finally {
                this.loading.prompts = false;
            }
        },

        async loadDataForDimension(dimension) {
            switch (dimension) {
                case "model":
                    await this.loadModels();
                    break;
                case "metric":
                    await this.loadMetrics();
                    break;
                case "test_case":
                    await this.loadTestCases();
                    break;
                case "benchmark":
                    await this.loadBenchmarks();
                    break;
                case "prompt":
                    await this.loadPrompts();
                    break;
            }
        },

        async loadAllData() {
            await Promise.all([
                this.loadModels(),
                this.loadMetrics(),
                this.loadTestCases(),
                this.loadBenchmarks(),
                this.loadPrompts(),
            ]);
        },

        async loadEvaluation(params) {
            this.loading.evaluation = true;
            this.evaluation.isLoaded = false;

            try {
                const response = await api.evaluation.getAll(params);

                const rawData = Array.isArray(response)
                    ? response
                    : response.data || [];

                // Enrich data with display names
                this.evaluation.data = this.enrichEvaluationData(rawData);
                this.evaluation.summary = this.calculateSummary(
                    this.evaluation.data
                );
                this.evaluation.isLoaded = true;

                this.clearErrors();
            } catch (error) {
                this.handleError(error);
                console.error("Error loading evaluation:", error);
                this.evaluation.data = [];
                this.evaluation.summary = null;
                this.evaluation.isLoaded = false;
            } finally {
                this.loading.evaluation = false;
            }
        },
        enrichEvaluationData(data) {
            return data.map((item) => {
                const enrichedItem = { ...item };

                // Add model name
                if (item.model_id) {
                    const model = this.data?.models?.find(
                        (m) => m.id == item.model_id
                    );
                    enrichedItem.model_name = model
                        ? model.name
                        : `Model ${item.model_id}`;
                }

                // Add metric name
                if (item.metric_id) {
                    const metric = this.data?.metrics?.find(
                        (m) => m.id == item.metric_id
                    );
                    enrichedItem.metric_name = metric
                        ? metric.name
                        : `Metric ${item.metric_id}`;
                }

                // Add test case name
                if (item.test_case_id) {
                    const testCase = this.data?.testCases?.find(
                        (tc) => tc.id == item.test_case_id
                    );
                    enrichedItem.test_case_name = testCase
                        ? testCase.name
                        : `Test Case ${item.test_case_id}`;
                }

                // Add benchmark name
                if (item.benchmark_id) {
                    const benchmark = this.data?.benchmarks?.find(
                        (b) => b.id == item.benchmark_id
                    );
                    enrichedItem.benchmark_name = benchmark
                        ? benchmark.name || `Benchmark ${benchmark.id}`
                        : `Benchmark ${item.benchmark_id}`;
                }

                // Add prompt name (using input field, truncated for display)
                if (item.prompt_id) {
                    const prompt = this.data?.prompts?.find(
                        (p) => p.id == item.prompt_id
                    );
                    if (prompt && prompt.input) {
                        // Truncate long prompt inputs for display
                        enrichedItem.prompt_name =
                            prompt.input.length > 50
                                ? prompt.input.substring(0, 50) + "..."
                                : prompt.input;
                    } else {
                        enrichedItem.prompt_name = `Prompt ${item.prompt_id}`;
                    }
                }

                return enrichedItem;
            });
        },

        calculateSummary(data) {
            if (!data || data.length === 0) {
                return { totalDatapoints: 0, uniqueConfigurations: 0 };
            }

            return {
                totalDatapoints: data.length,
                uniqueConfigurations: new Set(
                    data.map(
                        (item) =>
                            `${item.model_id}-${item.metric_id}-${item.prompt_id}`
                    )
                ).size,
            };
        },

        resetEvaluation() {
            this.evaluation.data = [];
            this.evaluation.summary = null;
            this.evaluation.isLoaded = false;
            this.loading.evaluation = false;
        },

        // Error handling
        handleError(error) {
            if (error.isValidationError) {
                this.errors = error.data.errors || {};
            } else {
                this.errors = { general: error.message };
            }
        },

        clearErrors() {
            this.errors = {};
        },
    },
});
