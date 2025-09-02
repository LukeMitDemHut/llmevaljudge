import { defineStore } from "pinia";
import { api } from "@services/ApiService.js";

export const useBenchmarksStore = defineStore("benchmarks", {
    state: () => ({
        benchmarks: [],
        testCases: [],
        metrics: [],
        models: [],
        loading: false,
        saving: false,
        errors: {},
    }),

    getters: {
        getBenchmarkById: (state) => (id) =>
            state.benchmarks.find((b) => b.id === id),
        getTestCaseById: (state) => (id) =>
            state.testCases.find((tc) => tc.id === id),
        getMetricById: (state) => (id) =>
            state.metrics.find((m) => m.id === id),
        getModelById: (state) => (id) => state.models.find((m) => m.id === id),
        hasErrors: (state) => Object.keys(state.errors).length > 0,

        getBenchmarkStatus: (state) => (benchmark) => {
            if (!benchmark.startedAt) return "not_started";
            if (benchmark.startedAt && !benchmark.finishedAt) return "running";
            return "finished";
        },

        getStatusLabel: (state) => (benchmark) => {
            const status = state.getBenchmarkStatus(benchmark);
            switch (status) {
                case "not_started":
                    return "Not Started";
                case "running":
                    return "Running";
                case "finished":
                    return "Finished";
                default:
                    return "Unknown";
            }
        },

        getStatusVariant: (state) => (benchmark) => {
            const status = state.getBenchmarkStatus(benchmark);
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

        canEdit: (state) => (benchmark) => {
            return state.getBenchmarkStatus(benchmark) === "not_started";
        },

        canStart: (state) => (benchmark) => {
            return (
                state.getBenchmarkStatus(benchmark) === "not_started" &&
                benchmark.models?.length > 0 &&
                benchmark.testCases?.length > 0 &&
                benchmark.metrics?.length > 0
            );
        },

        canViewResults: (state) => (benchmark) => {
            return state.getBenchmarkStatus(benchmark) === "finished";
        },

        canRerunMissing: (state) => (benchmark) => {
            return (
                state.getBenchmarkStatus(benchmark) === "finished" &&
                benchmark.errors?.length > 0
            );
        },
    },

    actions: {
        // Initialize from server props (called once on page load)
        initialize(initialData) {
            this.benchmarks = initialData.benchmarks || [];
            this.testCases = initialData.testCases || [];
            this.metrics = initialData.metrics || [];
            this.models = initialData.models || [];
            this.errors = {};
        },

        // Benchmark actions
        async createBenchmark(benchmarkData) {
            this.saving = true;
            this.errors = {};

            try {
                const newBenchmark = await api.benchmarks.create(benchmarkData);
                this.benchmarks.push(newBenchmark);
                return newBenchmark;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateBenchmark(id, benchmarkData) {
            this.saving = true;
            this.errors = {};

            try {
                const updatedBenchmark = await api.benchmarks.update(
                    id,
                    benchmarkData
                );
                const index = this.benchmarks.findIndex((b) => b.id === id);
                if (index !== -1) {
                    this.benchmarks[index] = updatedBenchmark;
                }
                return updatedBenchmark;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deleteBenchmark(id) {
            this.saving = true;
            this.errors = {};

            try {
                await api.benchmarks.delete(id);
                this.benchmarks = this.benchmarks.filter((b) => b.id !== id);
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async loadBenchmarks() {
            this.loading = true;
            this.errors = {};

            try {
                const benchmarks = await api.benchmarks.getAll();
                this.benchmarks = benchmarks;
                return benchmarks;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        // Silent refresh - updates data without showing loading spinner
        async refreshBenchmarksQuietly() {
            try {
                const benchmarks = await api.benchmarks.getAll();
                this.benchmarks = benchmarks;
                return benchmarks;
            } catch (error) {
                // Don't update errors state for silent refresh to avoid disrupting UI
                console.debug("Silent refresh failed:", error);
                throw error;
            }
        },

        // Load available entities for creating/editing benchmarks
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

        async startBenchmark(id) {
            this.saving = true;
            this.errors = {};

            try {
                const updatedBenchmark = await api.benchmarks.start(id);
                const index = this.benchmarks.findIndex((b) => b.id === id);
                if (index !== -1) {
                    this.benchmarks[index] = updatedBenchmark;
                }
                return updatedBenchmark;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async startMissingBenchmark(id) {
            this.saving = true;
            this.errors = {};

            try {
                const updatedBenchmark = await api.benchmarks.startMissing(id);
                const index = this.benchmarks.findIndex((b) => b.id === id);
                if (index !== -1) {
                    this.benchmarks[index] = updatedBenchmark;
                }
                return updatedBenchmark;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async loadBenchmarkResults(benchmarkId) {
            this.loading = true;
            this.errors = {};

            try {
                const results = await api.results.getByBenchmark(benchmarkId);
                return results;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
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
