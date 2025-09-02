import { defineStore } from "pinia";
import { api } from "@services/ApiService.js";

export const useMetricsStore = defineStore("metrics", {
    state: () => ({
        metrics: [],
        models: [],
        loading: false,
        saving: false,
        errors: {},
    }),

    getters: {
        getMetricById: (state) => (id) =>
            state.metrics.find((m) => m.id === id),
        getModelById: (state) => (id) => state.models.find((m) => m.id === id),
        hasErrors: (state) => Object.keys(state.errors).length > 0,
    },

    actions: {
        // Initialize from server props (called once on page load)
        initialize(initialData) {
            this.metrics = initialData.metrics || [];
            this.models = initialData.models || [];
            this.errors = {};
        },

        // Metric actions
        async createMetric(metricData) {
            this.saving = true;
            this.errors = {};

            try {
                const newMetric = await api.metrics.create(metricData);
                this.metrics.push(newMetric);
                return newMetric;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateMetric(id, metricData) {
            this.saving = true;
            this.errors = {};

            try {
                const updatedMetric = await api.metrics.update(id, metricData);
                const index = this.metrics.findIndex((m) => m.id === id);
                if (index !== -1) {
                    this.metrics[index] = updatedMetric;
                }
                return updatedMetric;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deleteMetric(id) {
            this.saving = true;
            this.errors = {};

            try {
                await api.metrics.delete(id);
                this.metrics = this.metrics.filter((m) => m.id !== id);
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
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
