/**
 * Constants for TALE metrics configuration
 */

export const AVAILABLE_TIME_RANGES = [
    { value: "day", label: "Past Day" },
    { value: "week", label: "Past Week" },
    { value: "month", label: "Past Month" },
    { value: "year", label: "Past Year" },
    { value: "all", label: "All Time" },
];

export const AVAILABLE_ENGINES = [
    {
        key: "Scientific",
        label: "Scientific",
        icon: "flask",
        engines: [
            {
                value: "arxiv",
                label: "ArXiv",
                supportsTimeRange: false,
            },
            {
                value: "crossref",
                label: "Crossref",
                supportsTimeRange: false,
            },
            {
                value: "google scholar",
                label: "Google Scholar",
                supportsTimeRange: true,
            },
            {
                value: "openalex",
                label: "OpenAlex",
                supportsTimeRange: false,
            },
            {
                value: "pubmed",
                label: "PubMed",
                supportsTimeRange: false,
            },
            {
                value: "semantic scholar",
                label: "Semantic Scholar",
                supportsTimeRange: false,
            },
        ],
    },
    {
        key: "Geographic",
        label: "Geographic",
        icon: "map-trifold",
        engines: [
            {
                value: "openstreetmap",
                label: "Open Street Map",
                supportsTimeRange: false,
            },
        ],
    },
    {
        key: "News",
        label: "News",
        icon: "newspaper",
        engines: [
            {
                value: "bing news",
                label: "Bing News",
                supportsTimeRange: true,
            },
            {
                value: "reuters",
                label: "Reuters",
                supportsTimeRange: true,
            },
            {
                value: "presearch news",
                label: "Presearch News",
                supportsTimeRange: true,
            },
            {
                value: "startpage news",
                label: "Startpage News",
                supportsTimeRange: true,
            },
        ],
    },
    {
        key: "Search",
        label: "Search",
        icon: "magnifying-glass",
        engines: [
            {
                value: "bing",
                label: "Bing",
                supportsTimeRange: true,
            },
            {
                value: "brave",
                label: "Brave",
                supportsTimeRange: true,
            },
            {
                value: "duckduckgo",
                label: "DuckDuckGo",
                supportsTimeRange: true,
            },
            {
                value: "google",
                label: "Google",
                supportsTimeRange: true,
            },
            {
                value: "yahoo",
                label: "Yahoo",
                supportsTimeRange: true,
            },
        ],
    },
];

/**
 * Create a mapping of engine values to human-readable labels
 */
export const ENGINE_LABELS = (() => {
    const labels = {};
    AVAILABLE_ENGINES.forEach(category => {
        category.engines.forEach(engine => {
            labels[engine.value] = engine.label;
        });
    });
    
    // Add any additional mappings that might be needed
    return {
        ...labels,
        "open-streetmap": "Open Street Map", // Alternative key format
        presearch_news: "Presearch News", // Alternative key format
        startpage_news: "Startpage News", // Alternative key format
    };
})();

/**
 * Utility function to format engine labels
 * @param {string} engine - The engine value
 * @returns {string} The formatted label
 */
export function formatEngineLabel(engine) {
    return ENGINE_LABELS[engine] || engine;
}

/**
 * Get engines for a specific category
 * @param {string} categoryKey - The category key
 * @returns {Array} Array of engines for the category
 */
export function getEnginesForCategory(categoryKey) {
    const category = AVAILABLE_ENGINES.find(cat => cat.key === categoryKey);
    return category ? category.engines : [];
}

/**
 * Get time range label by value
 * @param {string} value - The time range value
 * @returns {string} The time range label
 */
export function getTimeRangeLabel(value) {
    const timeRange = AVAILABLE_TIME_RANGES.find(tr => tr.value === value);
    return timeRange ? timeRange.label : value;
}
