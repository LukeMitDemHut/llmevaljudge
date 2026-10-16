<template>
    <div class="mt-3">
        <InputGroup
            label="Tools"
            ref="toolsGroup"
            errorMessage="At least one tool with a name and description is required"
        >
            <div
                v-for="(tool, index) in modelValue.tools"
                :key="index"
                class="border rounded p-3 mb-3 bg-light"
            >
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Tool {{ index + 1 }}</strong>
                    <button
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        @click="removeTool(index)"
                    >
                        Remove Tool
                    </button>
                </div>

                <div class="row g-2">
                    <div class="col-md-5">
                        <InputText
                            v-model="tool.name"
                            :label="'Name'"
                            placeholder="e.g. search_documents"
                            required
                            :maxLength="64"
                            errorMessage="Tool name is required"
                        />
                    </div>
                    <div class="col-md-7">
                        <InputText
                            v-model="tool.description"
                            :label="'Description'"
                            placeholder="What the tool does and how it works"
                            required
                            :maxLength="500"
                            :isTextarea="true"
                            errorMessage="Tool description is required"
                        />
                    </div>
                </div>

                <!-- Parameter Schema (visual JSON Schema editor) -->
                <div class="mt-3">
                    <h6 class="mb-1">Parameter Schema</h6>
                    <p class="text-muted small mb-2">
                        JSON Schema for the tool's parameters (OpenAI function calling
                        strict mode is applied automatically).
                    </p>
                    <div class="bg-white border rounded p-2">
                        <JsonSchemaEditor
                            :schema="getToolParametersSchema(tool)"
                            :show-json-editor="false"
                            :show-fullscreen="false"
                            @update:schema="setToolParametersSchema(tool, $event)"
                        />
                    </div>
                </div>

                <!-- Response Schema (advanced) -->
                <Accordion
                    class="mt-3"
                    :items="responseAccordionItems"
                    :accordionId="'toolResponseAccordion' + index"
                >
                    <template v-slot:response-pane>
                        <p class="text-muted small mb-2">
                            JSON Schema the judge must follow when simulating this
                            tool's response. Defaults to a single string property.
                        </p>
                        <div class="bg-white border rounded p-2">
                            <JsonSchemaEditor
                                :schema="getToolResponseSchema(tool)"
                                :show-json-editor="false"
                                :show-fullscreen="false"
                                @update:schema="setToolResponseSchema(tool, $event)"
                            />
                        </div>
                    </template>
                </Accordion>
            </div>

            <button
                type="button"
                class="btn btn-outline-primary"
                @click="addTool"
            >
                Add Tool
            </button>
        </InputGroup>

        <InputGroup
            label="Simulation & Evaluation"
            class="mt-3"
            ref="promptsGroup"
            errorMessage="Simulation and evaluation prompts are required"
        >
            <InputText
                v-model="modelValue.simulation_prompt"
                label="Simulation Prompt"
                placeholder="Describes how the system should respond to tool calls, e.g. 'Simulate that the document is only found after five tool-call rounds...'"
                required
                :isTextarea="true"
                ref="simulationPromptInput"
                errorMessage="Simulation prompt is required"
            />
            <InputText
                class="mt-3"
                v-model="modelValue.evaluation_prompt"
                label="Evaluation Prompt"
                placeholder="Describes the criteria for evaluating the tool usage, e.g. 'Assess whether the model chooses tools sensibly, passes reasonable parameters...'"
                required
                :isTextarea="true"
                ref="evaluationPromptInput"
                errorMessage="Evaluation prompt is required"
            />
            <div class="row mt-3">
                <div class="col-md-4">
                    <InputNumber
                        v-model="modelValue.max_tool_rounds"
                        placeholder="5"
                        label="Max Tool-Call Rounds"
                        type="number"
                        :min="TOOL_USE_LIMITS.maxRounds.min"
                        :max="TOOL_USE_LIMITS.maxRounds.max"
                        step="1"
                        required
                        ref="maxRoundsInput"
                        errorMessage="Max rounds must be between 1 and 20"
                    />
                </div>
            </div>
        </InputGroup>
    </div>
</template>

<script>
import InputGroup from "@components/interactables/inputs/InputGroup.vue";
import InputText from "@components/interactables/inputs/InputText.vue";
import InputNumber from "@components/interactables/inputs/InputNumber.vue";
import Accordion from "@components/structure/Accordion.vue";
import { JsonSchemaEditor } from "jsonschema-builder-vue";
import "jsonschema-builder-vue/styles.css";
import {
    TOOL_USE_LIMITS,
    defaultParametersSchema,
    defaultResponseSchema,
} from "@constants/tooluseConstants.js";

export default {
    name: "MetricDefinitionEditorToolUse",
    components: {
        InputGroup,
        InputText,
        InputNumber,
        Accordion,
        JsonSchemaEditor,
    },
    props: {
        modelValue: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            TOOL_USE_LIMITS,
            responseAccordionItems: [
                { id: "response", label: "Response Schema (advanced)", icon: "brackets-curly" },
            ],
        };
    },
    methods: {
        addTool() {
            const tools = this.modelValue.tools || [];
            if (tools.length >= TOOL_USE_LIMITS.maxTools) return;
            tools.push({
                name: "",
                description: "",
                parameters: defaultParametersSchema(),
                response_schema: defaultResponseSchema(),
            });
            this.modelValue.tools = tools;
        },
        removeTool(index) {
            this.modelValue.tools.splice(index, 1);
        },
        getToolParametersSchema(tool) {
            return tool.parameters || defaultParametersSchema();
        },
        setToolParametersSchema(tool, schema) {
            tool.parameters = schema;
        },
        getToolResponseSchema(tool) {
            return tool.response_schema || defaultResponseSchema();
        },
        setToolResponseSchema(tool, schema) {
            tool.response_schema = schema;
        },
        validate() {
            const toolsValid =
                Array.isArray(this.modelValue.tools) &&
                this.modelValue.tools.length > 0 &&
                this.modelValue.tools.every(
                    (tool) =>
                        tool.name &&
                        tool.name.trim().length > 0 &&
                        tool.description &&
                        tool.description.trim().length > 0
                );

            const promptsValid =
                this.modelValue.simulation_prompt &&
                this.modelValue.simulation_prompt.trim().length > 0 &&
                this.modelValue.evaluation_prompt &&
                this.modelValue.evaluation_prompt.trim().length > 0;

            const roundsValid =
                Number.isInteger(Number(this.modelValue.max_tool_rounds)) &&
                Number(this.modelValue.max_tool_rounds) >=
                    TOOL_USE_LIMITS.maxRounds.min &&
                Number(this.modelValue.max_tool_rounds) <=
                    TOOL_USE_LIMITS.maxRounds.max;

            const groupResults = [];
            if (this.$refs.toolsGroup) {
                groupResults.push(this.$refs.toolsGroup.validate());
            }
            if (this.$refs.promptsGroup) {
                groupResults.push(this.$refs.promptsGroup.validate());
            }

            return toolsValid && promptsValid && roundsValid;
        },
        reset() {
            if (this.$refs.toolsGroup) {
                this.$refs.toolsGroup.reset();
            }
            if (this.$refs.promptsGroup) {
                this.$refs.promptsGroup.reset();
            }
        },
    },
};
</script>