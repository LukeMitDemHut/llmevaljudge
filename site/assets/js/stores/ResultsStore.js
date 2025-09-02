import { defineStore } from "pinia";
import { api } from "@services/ApiService.js";

export const useResultsStore = defineStore("results", {
    state: () => ({
        results: [],
        prompts: [],
        metrics: [],
        models: [],
        testCases: [],
        benchmarks: [],
        evaluationData: null,
        deduplicationStrategy: "latest", // Default strategy
        selectedFilters: {
            modelIds: [],
            metricIds: [],
            testCaseIds: [],
        },
        loading: false,
        saving: false,
        errors: {},
    }),

    getters: {
        getResultById: (state) => (id) =>
            state.results.find((r) => r.id === id),
        getResultsByPrompt: (state) => (promptId) =>
            state.results.filter((r) => r.prompt?.id === promptId),
        getResultsByMetric: (state) => (metricId) =>
            state.results.filter((r) => r.metric?.id === metricId),
        getResultsByModel: (state) => (modelId) =>
            state.results.filter((r) => r.model?.id === modelId),
        getPromptById: (state) => (id) =>
            state.prompts.find((p) => p.id === id),
        getMetricById: (state) => (id) =>
            state.metrics.find((m) => m.id === id),
        getModelById: (state) => (id) => state.models.find((m) => m.id === id),
        getTestCaseById: (state) => (id) =>
            state.testCases.find((tc) => tc.id === id),
        getBenchmarkById: (state) => (id) =>
            state.benchmarks.find((b) => b.id === id),
        hasErrors: (state) => Object.keys(state.errors).length > 0,
        hasActiveAnalysis: (state) =>
            state.evaluationData !== null && state.evaluationData !== undefined,
    },

    actions: {
        // Initialize from server props (called once on page load)
        initialize(initialData) {
            this.results = initialData.results || [];
            this.prompts = initialData.prompts || [];
            this.metrics = initialData.metrics || [];
            this.models = initialData.models || [];
            this.testCases = initialData.testCases || [];
            this.benchmarks = initialData.benchmarks || [];
            this.errors = {};
        },

        // Result actions
        async createResult(resultData) {
            this.saving = true;
            this.errors = {};

            try {
                const newResult = await api.results.create(resultData);
                this.results.push(newResult);
                return newResult;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateResult(id, resultData) {
            this.saving = true;
            this.errors = {};

            try {
                const updatedResult = await api.results.update(id, resultData);
                const index = this.results.findIndex((r) => r.id === id);
                if (index !== -1) {
                    this.results[index] = updatedResult;
                }
                return updatedResult;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deleteResult(id) {
            this.saving = true;
            this.errors = {};

            try {
                await api.results.delete(id);
                this.results = this.results.filter((r) => r.id !== id);
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async loadResults(filters = {}) {
            this.loading = true;
            this.errors = {};

            try {
                const results = await api.results.getAll(filters);
                this.results = results;
                return results;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async loadResultsByPrompt(promptId) {
            this.loading = true;
            this.errors = {};

            try {
                const results = await api.results.getByPrompt(promptId);
                // Merge with existing results, removing duplicates
                const existingIds = new Set(this.results.map((r) => r.id));
                const newResults = results.filter(
                    (r) => !existingIds.has(r.id)
                );
                this.results.push(...newResults);
                return results;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async loadResultsByMetric(metricId) {
            this.loading = true;
            this.errors = {};

            try {
                const results = await api.results.getByMetric(metricId);
                // Merge with existing results, removing duplicates
                const existingIds = new Set(this.results.map((r) => r.id));
                const newResults = results.filter(
                    (r) => !existingIds.has(r.id)
                );
                this.results.push(...newResults);
                return results;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async loadResultsByModel(modelId) {
            this.loading = true;
            this.errors = {};

            try {
                const results = await api.results.getByModel(modelId);
                // Merge with existing results, removing duplicates
                const existingIds = new Set(this.results.map((r) => r.id));
                const newResults = results.filter(
                    (r) => !existingIds.has(r.id)
                );
                this.results.push(...newResults);
                return results;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        // Load available entities for creating/editing results
        async loadPrompts() {
            try {
                const prompts = await api.prompts.getAll();
                this.prompts = prompts;
                return prompts;
            } catch (error) {
                this.handleError(error);
                throw error;
            }
        },

        async loadMetrics() {
            try {
                const metrics = await api.metrics.getAll();
                this.metrics = metrics;
                return metrics;
            } catch (error) {
                this.handleError(error);
                throw error;
            }
        },

        async loadModels() {
            try {
                const models = await api.models.getAll();
                this.models = models;
                return models;
            } catch (error) {
                this.handleError(error);
                throw error;
            }
        },

        async loadTestCases() {
            try {
                const testCases = await api.testCases.getAll();
                this.testCases = testCases;
                return testCases;
            } catch (error) {
                this.handleError(error);
                throw error;
            }
        },

        // Evaluation-specific methods
        async loadModelAnalysis(modelId, metricIds = [], testCaseIds = []) {
            this.loading = true;
            this.errors = {};

            try {
                const params = {
                    modelId,
                    deduplication: this.deduplicationStrategy,
                };
                if (metricIds.length > 0) {
                    params.metricIds = metricIds.join(",");
                }
                if (testCaseIds.length > 0) {
                    params.testCaseIds = testCaseIds.join(",");
                }

                const data = await api.evaluation.getModelAnalysis(params);
                this.evaluationData = data;
                return data;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async loadMetricAnalysis(metricId, modelIds = [], testCaseIds = []) {
            this.loading = true;
            this.errors = {};

            try {
                const params = {
                    metricId,
                    deduplication: this.deduplicationStrategy,
                };
                if (modelIds.length > 0) {
                    params.modelIds = modelIds.join(",");
                }
                if (testCaseIds.length > 0) {
                    params.testCaseIds = testCaseIds.join(",");
                }

                const data = await api.evaluation.getMetricAnalysis(params);
                this.evaluationData = data;
                return data;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async loadTestCaseAnalysis(testCaseId, modelIds = [], metricIds = []) {
            this.loading = true;
            this.errors = {};

            try {
                const params = {
                    testCaseId,
                    deduplication: this.deduplicationStrategy,
                };
                if (modelIds.length > 0) {
                    params.modelIds = modelIds.join(",");
                }
                if (metricIds.length > 0) {
                    params.metricIds = metricIds.join(",");
                }

                const data = await api.evaluation.getTestCaseAnalysis(params);
                this.evaluationData = data;
                return data;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async loadBenchmarkAnalysis(benchmarkId, modelIds = []) {
            this.loading = true;
            this.errors = {};

            try {
                const params = {
                    benchmarkId,
                    deduplication: this.deduplicationStrategy,
                };
                if (modelIds.length > 0) {
                    params.modelIds = modelIds.join(",");
                }

                const data = await api.evaluation.getBenchmarkAnalysis(params);
                this.evaluationData = data;
                return data;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async loadEvaluationResults(
            modelIds = [],
            metricIds = [],
            testCaseIds = []
        ) {
            this.loading = true;
            this.errors = {};

            try {
                const params = {
                    deduplication: this.deduplicationStrategy,
                };
                if (modelIds.length > 0) {
                    params.modelIds = modelIds.join(",");
                }
                if (metricIds.length > 0) {
                    params.metricIds = metricIds.join(",");
                }
                if (testCaseIds.length > 0) {
                    params.testCaseIds = testCaseIds.join(",");
                }

                const results = await api.evaluation.getResults(params);
                this.results = results;
                return results;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        // Filter management
        updateFilters(filters) {
            this.selectedFilters = { ...this.selectedFilters, ...filters };
        },

        clearFilters() {
            this.selectedFilters = {
                modelIds: [],
                metricIds: [],
                testCaseIds: [],
            };
        },

        clearEvaluationData() {
            this.evaluationData = null;
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
