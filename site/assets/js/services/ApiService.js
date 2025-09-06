class ApiService {
    constructor(baseURL = "") {
        this.baseURL = baseURL;
        this.defaultPageSize = 50;
        this.maxPageSize = 200;
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

    // Enhanced method to handle paginated requests
    async getAllPaginated(resource, options = {}) {
        const {
            filters = {},
            pageSize = this.defaultPageSize,
            maxResults = null,
            onProgress = null,
        } = options;

        let allData = [];
        let page = 1;
        let hasMore = true;
        let totalFetched = 0;

        while (hasMore) {
            const params = new URLSearchParams({
                ...filters,
                page: page.toString(),
                // Use page_size for evaluation endpoint, pageSize for others
                [resource === "evaluation" ? "page_size" : "pageSize"]:
                    Math.min(pageSize, this.maxPageSize).toString(),
                paginate: "true",
            });

            const url = `/api/${resource}?${params.toString()}`;
            const response = await this.request(url);

            // Handle both paginated and non-paginated responses
            if (response && response.pagination) {
                // Paginated response
                allData = allData.concat(response.data || []);
                hasMore =
                    response.pagination.hasNext ||
                    (response.data && response.data.length === pageSize);
                totalFetched += (response.data || []).length;

                if (onProgress) {
                    onProgress({
                        currentPage: page,
                        totalPages: response.pagination.totalPages,
                        totalItems: response.pagination.totalItems,
                        fetchedItems: totalFetched,
                    });
                }
            } else {
                // Non-paginated response (fallback)
                allData = response || [];
                hasMore = false;
                totalFetched = allData.length;
            }

            // Stop if we've reached the maximum requested results
            if (maxResults && totalFetched >= maxResults) {
                allData = allData.slice(0, maxResults);
                break;
            }

            page++;

            // Safety break to prevent infinite loops
            if (page > 1000) {
                console.warn(
                    `Pagination safety break triggered for ${resource}`
                );
                break;
            }
        }

        return allData;
    }

    // Generic CRUD operations
    async getAll(resource, params = {}) {
        // Check if this should use pagination
        const shouldPaginate =
            params.paginate !== false &&
            (params.pageSize ||
                params.maxResults ||
                this.shouldUsePagination(resource));

        if (shouldPaginate) {
            return this.getAllPaginated(resource, {
                filters: params,
                pageSize: params.pageSize,
                maxResults: params.maxResults,
                onProgress: params.onProgress,
            });
        }

        // Use the original single request method
        const queryString = new URLSearchParams(params).toString();
        return this.request(
            `/api/${resource}${queryString ? `?${queryString}` : ""}`
        );
    }

    // Determine if a resource should use pagination by default
    shouldUsePagination(resource) {
        // These resources are likely to have large datasets
        const largDatasetResources = ["results", "prompts", "metrics"];
        return largDatasetResources.includes(resource);
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
        getAll: (params = {}) => this.getAll("providers", params),
        getById: (id) => this.getById("providers", id),
        create: (data) => this.create("providers", data),
        update: (id, data) => this.update("providers", id, data),
        delete: (id) => this.delete("providers", id),
    };

    models = {
        getAll: (params = {}) => this.getAll("models", params),
        getById: (id) => this.getById("models", id),
        create: (data) => this.create("models", data),
        update: (id, data) => this.update("models", id, data),
        delete: (id) => this.delete("models", id),
    };

    metrics = {
        getAll: (params = {}) => this.getAll("metrics", params),
        getById: (id) => this.getById("metrics", id),
        create: (data) => this.create("metrics", data),
        update: (id, data) => this.update("metrics", id, data),
        delete: (id) => this.delete("metrics", id),
        getTypes: () => this.request("/api/metrics/types"),
        search: (query, limit = 50) =>
            this.getAll("metrics", { search: query, limit, paginate: false }),
    };

    benchmarks = {
        getAll: (params = {}) => this.getAll("benchmarks", params),
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
        getAll: (params = {}) => this.getAll("test-cases", params),
        getById: (id) => this.getById("test-cases", id),
        create: (data) => this.create("test-cases", data),
        update: (id, data) => this.update("test-cases", id, data),
        delete: (id) => this.delete("test-cases", id),
    };

    prompts = {
        getAll: (params = {}) => this.getAll("prompts", params),
        getById: (id) => this.getById("prompts", id),
        create: (data) => this.create("prompts", data),
        update: (id, data) => this.update("prompts", id, data),
        delete: (id) => this.delete("prompts", id),
        getByTestCase: (testCaseId) =>
            this.getAll("prompts", { testCase: testCaseId }),
    };

    results = {
        getAll: (params = {}) => this.getAll("results", params),
        getById: (id) => this.getById("results", id),
        create: (data) => this.create("results", data),
        update: (id, data) => this.update("results", id, data),
        delete: (id) => this.delete("results", id),
        getByPrompt: (promptId) => this.getAll("results", { prompt: promptId }),
        getByMetric: (metricId) => this.getAll("results", { metric: metricId }),
        getByModel: (modelId) => this.getAll("results", { model: modelId }),
        getByBenchmark: (benchmarkId) =>
            this.getAllPaginated(`benchmarks/${benchmarkId}/results`),
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

    evaluation = {
        getAll: (params = {}) => this.getAll("evaluation", params),
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
