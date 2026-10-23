/**
 * Constants for Tool Use metrics configuration
 */

/** Limits for the tool-use definition editor */
export const TOOL_USE_LIMITS = {
    maxTools: 20,
    maxRounds: { min: 1, max: 20, default: 5 },
};

/** Default parameter schema for a new tool (OpenAI function calling compatible) */
export function defaultParametersSchema() {
    return {
        type: "object",
        properties: {},
        required: [],
        additionalProperties: false,
    };
}

/**
 * Default response schema for a new tool (judge simulation output):
 * one plain string property, extendable in the advanced accordion.
 */
export function defaultResponseSchema() {
    return {
        type: "object",
        properties: {
            tool_result: {
                type: "string",
                description: "The simulated tool response as plain text",
            },
        },
        required: ["tool_result"],
        additionalProperties: false,
    };
}

/** Initial definition for a fresh tool-use metric */
export function initialToolUseDefinition() {
    return {
        tools: [],
        simulation_prompt: "",
        evaluation_prompt: "",
        max_tool_rounds: TOOL_USE_LIMITS.maxRounds.default,
    };
}