import { defineStore } from "pinia";
import { api } from "@services/ApiService.js";

export const useSettingsStore = defineStore("settings", {
    state: () => ({
        providers: [],
        models: [],
        settings: {},
        loading: false,
        saving: false,
        errors: {},
    }),

    getters: {
        getProviderById: (state) => (id) =>
            state.providers.find((p) => p.id === id),
        getModelById: (state) => (id) => state.models.find((m) => m.id === id),
        getModelsByProvider: (state) => (providerId) =>
            state.models.filter((m) => m.provider?.id === providerId),
        getSetting: (state) => (name) => state.settings[name] || "",
        hasErrors: (state) => Object.keys(state.errors).length > 0,
    },

    actions: {
        // Initialize from server props (called once on page load)
        initialize(initialData) {
            this.providers = initialData.providers || [];
            this.models = initialData.models || [];
            this.settings = initialData.settings || {};
            this.errors = {};
        },

        // Settings actions
        async loadSettings() {
            this.loading = true;
            this.errors = {};

            try {
                const settings = await api.settings.getAll();
                this.settings = settings;
                return settings;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async updateSetting(name, value) {
            this.saving = true;
            this.errors = {};

            try {
                const result = await api.settings.update(name, value);
                this.settings[name] = result.value;
                return result;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateMultipleSettings(settingsData) {
            this.saving = true;
            this.errors = {};

            try {
                const result = await api.settings.bulkUpdate(settingsData);
                this.settings = { ...this.settings, ...result };
                return result;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        // Provider actions
        async createProvider(providerData) {
            this.saving = true;
            this.errors = {};

            try {
                const newProvider = await api.providers.create(providerData);
                this.providers.push(newProvider);
                return newProvider;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateProvider(id, providerData) {
            this.saving = true;
            this.errors = {};

            try {
                const updatedProvider = await api.providers.update(
                    id,
                    providerData
                );
                const index = this.providers.findIndex((p) => p.id === id);
                if (index !== -1) {
                    this.providers[index] = updatedProvider;
                }
                return updatedProvider;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deleteProvider(id) {
            this.saving = true;
            this.errors = {};

            try {
                await api.providers.delete(id);
                this.providers = this.providers.filter((p) => p.id !== id);
                // Also remove associated models
                this.models = this.models.filter((m) => m.provider?.id !== id);
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        // Model actions
        async createModel(modelData) {
            this.saving = true;
            this.errors = {};

            try {
                const newModel = await api.models.create(modelData);
                this.models.push(newModel);
                return newModel;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateModel(id, modelData) {
            this.saving = true;
            this.errors = {};

            try {
                const updatedModel = await api.models.update(id, modelData);
                const index = this.models.findIndex((m) => m.id === id);
                if (index !== -1) {
                    this.models[index] = updatedModel;
                }
                return updatedModel;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deleteModel(id) {
            this.saving = true;
            this.errors = {};

            try {
                await api.models.delete(id);
                this.models = this.models.filter((m) => m.id !== id);
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
