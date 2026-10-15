<template>
    <div class="mt-3">
        <InputGroup
            label="Tools"
            ref="toolsGroup"
            errorMessage="At least one complete tool is required"
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

                <!-- Parameters -->
                <div class="mt-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Parameters</h6>
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            @click="addParameter(tool)"
                        >
                            Add Parameter
                        </button>
                    </div>

                    <div
                        v-for="(param, pIndex) in tool.parameters"
                        :key="pIndex"
                        class="row g-2 align-items-end mt-1 ps-2 border-start"
                    >
                        <div class="col-md-3">
                            <InputText
                                v-model="param.name"
                                :label="'Name'"
                                placeholder="e.g. query"
                                required
                                :maxLength="64"
                            />
                        </div>
                        <div class="col-md-2">
                            <InputDropdown
                                v-model="param.type"
                                :label="'Type'"
                                placeholder="Type"
                                :options="paramTypeOptions"
                                required
                            />
                        </div>
                        <div class="col-md-5">
                            <InputText
                                v-model="param.description"
                                :label="'Description'"
                                placeholder="What the parameter is used for"
                                :maxLength="300"
                            />
                        </div>
                        <div class="col-md-1">
                            <InputCheckbox
                                label="Required"
                                :modelValue="param.required"
                                :value="param.required"
                                @update:modelValue="param.required = $event"
                            />
                        </div>
                        <div class="col-md-1 pb-2">
                            <button
                                type="button"
                                class="btn btn-outline-danger btn-sm"
                                @click="removeParameter(tool, pIndex)"
                            >
                                ×
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="tool.parameters.length === 0"
                        class="text-muted small mt-1"
                    >
                        No parameters defined.
                    </div>
                </div>
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
import InputDropdown from "@components/interactables/inputs/InputDropdown.vue";
import InputNumber from "@components/interactables/inputs/InputNumber.vue";
import InputCheckbox from "@components/interactables/inputs/InputCheckbox.vue";
import {
    AVAILABLE_PARAM_TYPES,
    TOOL_USE_LIMITS,
    newTool,
    newToolParameter,
} from "@constants/tooluseConstants.js";

export default {
    name: "MetricDefinitionEditorToolUse",
    components: {
        InputGroup,
        InputText,
        InputDropdown,
        InputNumber,
        InputCheckbox,
    },
    props: {
        modelValue: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            paramTypeOptions: AVAILABLE_PARAM_TYPES,
            TOOL_USE_LIMITS,
        };
    },
    methods: {
        addTool() {
            const tools = this.modelValue.tools || [];
            if (tools.length >= TOOL_USE_LIMITS.maxTools) return;
            tools.push(newTool());
            this.modelValue.tools = tools;
        },
        removeTool(index) {
            this.modelValue.tools.splice(index, 1);
        },
        addParameter(tool) {
            const params = tool.parameters || [];
            if (params.length >= TOOL_USE_LIMITS.maxParamsPerTool) return;
            params.push(newToolParameter());
            tool.parameters = params;
        },
        removeParameter(tool, index) {
            tool.parameters.splice(index, 1);
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