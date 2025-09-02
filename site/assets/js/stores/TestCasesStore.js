import { defineStore } from "pinia";
import { api } from "@services/ApiService.js";

export const useTestCasesStore = defineStore("testCases", {
    state: () => ({
        testCases: [],
        prompts: [],
        loading: false,
        saving: false,
        errors: {},
    }),

    getters: {
        getTestCaseById: (state) => (id) =>
            state.testCases.find((tc) => tc.id === id),
        getPromptById: (state) => (id) =>
            state.prompts.find((p) => p.id === id),
        getPromptsByTestCase: (state) => (testCaseId) =>
            state.prompts.filter((p) => p.testCase?.id === testCaseId),
        hasErrors: (state) => Object.keys(state.errors).length > 0,
    },

    actions: {
        // Initialize from server props (called once on page load)
        initialize(initialData) {
            this.testCases = initialData.testCases || [];
            this.prompts = initialData.prompts || [];
            this.errors = {};
        },

        // Test Case actions
        async createTestCase(testCaseData) {
            this.saving = true;
            this.errors = {};

            try {
                const newTestCase = await api.testCases.create(testCaseData);
                this.testCases.push(newTestCase);
                return newTestCase;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateTestCase(id, testCaseData) {
            this.saving = true;
            this.errors = {};

            try {
                const updatedTestCase = await api.testCases.update(
                    id,
                    testCaseData
                );
                const index = this.testCases.findIndex((tc) => tc.id === id);
                if (index !== -1) {
                    this.testCases[index] = updatedTestCase;
                }
                return updatedTestCase;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deleteTestCase(id) {
            this.saving = true;
            this.errors = {};

            try {
                await api.testCases.delete(id);
                this.testCases = this.testCases.filter((tc) => tc.id !== id);
                // Also remove related prompts
                this.prompts = this.prompts.filter(
                    (p) => p.testCase?.id !== id
                );
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async loadTestCases() {
            this.loading = true;
            this.errors = {};

            try {
                const testCases = await api.testCases.getAll();
                this.testCases = testCases;
                return testCases;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        // Load specific test case with prompts (for details modal)
        async loadTestCaseWithPrompts(testCaseId) {
            this.loading = true;
            this.errors = {};

            try {
                const testCase = await api.testCases.getById(testCaseId);

                // Update the test case in our list with full data
                const index = this.testCases.findIndex(
                    (tc) => tc.id === testCaseId
                );
                if (index !== -1) {
                    this.testCases[index] = testCase;
                }

                return testCase;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        // Prompt actions
        async createPrompt(promptData) {
            this.saving = true;
            this.errors = {};

            try {
                const newPrompt = await api.prompts.create(promptData);
                this.prompts.push(newPrompt);

                // Update the test case in our list to include the new prompt
                const testCaseIndex = this.testCases.findIndex(
                    (tc) => tc.id === promptData.testCaseId
                );
                if (testCaseIndex !== -1) {
                    if (!this.testCases[testCaseIndex].prompts) {
                        this.testCases[testCaseIndex].prompts = [];
                    }
                    this.testCases[testCaseIndex].prompts.push(newPrompt);
                }

                return newPrompt;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updatePrompt(id, promptData) {
            this.saving = true;
            this.errors = {};

            try {
                const updatedPrompt = await api.prompts.update(id, promptData);
                const index = this.prompts.findIndex((p) => p.id === id);
                if (index !== -1) {
                    this.prompts[index] = updatedPrompt;
                }

                // Update the prompt in the test case as well
                const testCase = this.testCases.find(
                    (tc) => tc.prompts && tc.prompts.some((p) => p.id === id)
                );
                if (testCase) {
                    const promptIndex = testCase.prompts.findIndex(
                        (p) => p.id === id
                    );
                    if (promptIndex !== -1) {
                        testCase.prompts[promptIndex] = updatedPrompt;
                    }
                }

                return updatedPrompt;
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deletePrompt(id) {
            this.saving = true;
            this.errors = {};

            try {
                await api.prompts.delete(id);
                this.prompts = this.prompts.filter((p) => p.id !== id);

                // Remove the prompt from the test case as well
                const testCase = this.testCases.find(
                    (tc) => tc.prompts && tc.prompts.some((p) => p.id === id)
                );
                if (testCase) {
                    testCase.prompts = testCase.prompts.filter(
                        (p) => p.id !== id
                    );
                }
            } catch (error) {
                this.handleError(error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async loadPrompts(testCaseId = null) {
            this.loading = true;
            this.errors = {};

            try {
                let prompts;
                if (testCaseId) {
                    prompts = await api.prompts.getByTestCase(testCaseId);
                } else {
                    prompts = await api.prompts.getAll();
                }
                this.prompts = prompts;
                return prompts;
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
