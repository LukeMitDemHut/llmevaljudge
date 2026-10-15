/**
 * Constants for Tool Use metrics configuration
 */

/** Datentypen, die ein Tool-Parameter annehmen kann (OpenAI function calling + JSON-Schema-Teilmenge) */
export const AVAILABLE_PARAM_TYPES = [
    { value: "string", label: "String" },
    { value: "number", label: "Number" },
    { value: "boolean", label: "Boolean" },
    { value: "object", label: "Object" },
    { value: "array", label: "Array" },
];

/** Limits for the tool-use definition editor */
export const TOOL_USE_LIMITS = {
    maxTools: 20,
    maxParamsPerTool: 30,
    maxRounds: { min: 1, max: 20, default: 5 },
};

/** Structure used when initializing a fresh tool-use definition */
export function newTool() {
    return {
        name: "",
        description: "",
        parameters: [],
    };
}

export function newToolParameter() {
    return {
        name: "",
        type: "string",
        description: "",
        required: false,
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