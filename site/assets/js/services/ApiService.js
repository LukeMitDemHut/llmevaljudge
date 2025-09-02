class ApiService {
    constructor(baseURL = "") {
        this.baseURL = baseURL;
    }

    async request(url, options = {}) {
        const config = {
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                ...options.headers,
            },
            ...options,
        };

        // Add CSRF token if available
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        if (csrfToken) {
            config.headers["X-CSRF-TOKEN"] = csrfToken;
        }

        try {
            const response = await fetch(`${this.baseURL}${url}`, config);

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new ApiError(
                    response.status,
                    errorData.message || "Request failed",
                    errorData
                );
            }

            // Handle 204 No Content responses
            if (response.status === 204) {
                return null;
            }

            // Check if there's content to parse
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return await response.json();
            }

            // For non-JSON responses, return the text
            return await response.text();
        } catch (error) {
            if (error instanceof ApiError) {
                throw error;
            }
            throw new ApiError(0, "Network error", { originalError: error });
        }
    }

    // Generic CRUD operations
    async getAll(resource) {
        return this.request(`/api/${resource}`);
    }

    async getById(resource, id) {
        return this.request(`/api/${resource}/${id}`);
    }

    async create(resource, data) {
        return this.request(`/api/${resource}`, {
            method: "POST",
            body: JSON.stringify(data),
        });
    }

    async update(resource, id, data) {
        return this.request(`/api/${resource}/${id}`, {
            method: "PUT",
            body: JSON.stringify(data),
        });
    }

    async delete(resource, id) {
        return this.request(`/api/${resource}/${id}`, {
            method: "DELETE",
        });
    }

    // Specific resource methods (can be extended)
    providers = {
        getAll: () => this.getAll("providers"),
        getById: (id) => this.getById("providers", id),
        create: (data) => this.create("providers", data),
        update: (id, data) => this.update("providers", id, data),
        delete: (id) => this.delete("providers", id),
    };

    models = {
        getAll: () => this.getAll("models"),
        getById: (id) => this.getById("models", id),
        create: (data) => this.create("models", data),
        update: (id, data) => this.update("models", id, data),
        delete: (id) => this.delete("models", id),
    };

    metrics = {
        getAll: (params = {}) => {
            const queryString = new URLSearchParams(params).toString();
            return this.request(
                `/api/metrics${queryString ? `?${queryString}` : ""}`
            );
        },
        getById: (id) => this.getById("metrics", id),
        create: (data) => this.create("metrics", data),
        update: (id, data) => this.update("metrics", id, data),
        delete: (id) => this.delete("metrics", id),
        getTypes: () => this.request("/api/metrics/types"),
        search: (query, limit = 50) =>
            this.request(
                `/api/metrics?search=${encodeURIComponent(
                    query
                )}&limit=${limit}`
            ),
    };

    benchmarks = {
        getAll: () => this.getAll("benchmarks"),
        getById: (id) => this.getById("benchmarks", id),
        create: (data) => this.create("benchmarks", data),
        update: (id, data) => this.update("benchmarks", id, data),
        delete: (id) => this.delete("benchmarks", id),
        start: (id) =>
            this.request(`/api/benchmarks/${id}/start`, { method: "POST" }),
        startMissing: (id) =>
            this.request(`/api/benchmarks/${id}/start-missing`, {
                method: "POST",
            }),
    };

    testCases = {
        getAll: () => this.getAll("test-cases"),
        getById: (id) => this.getById("test-cases", id),
        create: (data) => this.create("test-cases", data),
        update: (id, data) => this.update("test-cases", id, data),
        delete: (id) => this.delete("test-cases", id),
    };

    prompts = {
        getAll: (params = {}) => {
            const queryString = new URLSearchParams(params).toString();
            return this.request(
                `/api/prompts${queryString ? `?${queryString}` : ""}`
            );
        },
        getById: (id) => this.getById("prompts", id),
        create: (data) => this.create("prompts", data),
        update: (id, data) => this.update("prompts", id, data),
        delete: (id) => this.delete("prompts", id),
        getByTestCase: (testCaseId) =>
            this.request(`/api/prompts?testCase=${testCaseId}`),
    };

    results = {
        getAll: (params = {}) => {
            const queryString = new URLSearchParams(params).toString();
            return this.request(
                `/api/results${queryString ? `?${queryString}` : ""}`
            );
        },
        getById: (id) => this.getById("results", id),
        create: (data) => this.create("results", data),
        update: (id, data) => this.update("results", id, data),
        delete: (id) => this.delete("results", id),
        getByPrompt: (promptId) =>
            this.request(`/api/results?prompt=${promptId}`),
        getByMetric: (metricId) =>
            this.request(`/api/results?metric=${metricId}`),
        getByModel: (modelId) => this.request(`/api/results?model=${modelId}`),
        getByBenchmark: (benchmarkId) =>
            this.request(`/api/benchmarks/${benchmarkId}/results`),
    };

    evaluation = {
        getModelAnalysis: (params = {}) => {
            const queryString = new URLSearchParams(params).toString();
            return this.request(
                `/api/evaluation/model-analysis${
                    queryString ? `?${queryString}` : ""
                }`
            );
        },
        getMetricAnalysis: (params = {}) => {
            const queryString = new URLSearchParams(params).toString();
            return this.request(
                `/api/evaluation/metric-analysis${
                    queryString ? `?${queryString}` : ""
                }`
            );
        },
        getTestCaseAnalysis: (params = {}) => {
            const queryString = new URLSearchParams(params).toString();
            return this.request(
                `/api/evaluation/test-case-analysis${
                    queryString ? `?${queryString}` : ""
                }`
            );
        },
        getBenchmarkAnalysis: (params = {}) => {
            const queryString = new URLSearchParams(params).toString();
            return this.request(
                `/api/evaluation/benchmark-analysis${
                    queryString ? `?${queryString}` : ""
                }`
            );
        },
        getResults: (params = {}) => {
            const queryString = new URLSearchParams(params).toString();
            return this.request(
                `/api/evaluation/results${queryString ? `?${queryString}` : ""}`
            );
        },
    };

    settings = {
        getAll: () => this.request("/api/settings"),
        get: (name) => this.request(`/api/settings/${name}`),
        update: (name, value) =>
            this.request(`/api/settings/${name}`, {
                method: "PUT",
                body: JSON.stringify({ value }),
            }),
        bulkUpdate: (data) =>
            this.request("/api/settings", {
                method: "POST",
                body: JSON.stringify(data),
            }),
    };
}

class ApiError extends Error {
    constructor(status, message, data = {}) {
        super(message);
        this.name = "ApiError";
        this.status = status;
        this.data = data;
    }

    get isValidationError() {
        return this.status === 400 && this.data.errors;
    }

    get isNotFound() {
        return this.status === 404;
    }

    get isServerError() {
        return this.status >= 500;
    }
}

// Export singleton instance
export const api = new ApiService();
export { ApiError };
