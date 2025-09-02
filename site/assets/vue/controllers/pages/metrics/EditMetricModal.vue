<template>
    <InputGroup
        label="Basic information"
        ref="basicInfoForm"
        errorMessage="Please complete all required fields"
    >
        <div class="row g-3">
            <div class="col-md-6">
                <InputText
                    v-model="metric.name"
                    label="Name"
                    placeholder="Enter metric name"
                    required
                    :maxLength="255"
                    errorMessage="Metric name is required"
                />
            </div>
            <div class="col-md-6">
                <InputDropdown
                    v-model="metric.type"
                    label="Type"
                    placeholder="Select metric type"
                    :options="typeOptions"
                    required
                    errorMessage="Metric type is required"
                />
            </div>
        </div>
        <InputText
            class="mt-3"
            v-model="metric.description"
            label="Description"
            placeholder="Enter metric description"
            :maxLength="500"
        />
    </InputGroup>
    <InputGroup
        label="Evaluation"
        class="mt-3"
        ref="evaluationForm"
        errorMessage="Please complete the evaluation configuration"
    >
        <div class="d-flex flex-row">
            <InputDropdown
                v-model="metric.ratingModelId"
                label="evaluation model"
                placeholder="Select evaluation model"
                :options="modelOptions"
                required
                class="col-6"
                errorMessage="Evaluation model is required"
            />
            <InputNumber
                v-model="metric.threshold"
                label="Threshold"
                placeholder="Enter threshold"
                min="0"
                max="1"
                step="0.01"
                required
                class="col-6 ps-3"
                errorMessage="Threshold is required (0-1)"
            />
        </div>
    </InputGroup>

    <InputMultiCheckbox
        v-model="metric.param"
        label="Parameters"
        :options="availableParams"
        helpText="Select which parameters this metric will use for evaluation"
    />

    <MetricDefinitionEditorGeval
        v-if="metric.type === 'g-eval'"
        v-model="metric.definition"
        ref="definitionEditor"
    />
    <MetricDefinitionEditorDag
        v-if="metric.type === 'dag'"
        v-model="metric.definition"
        ref="definitionEditor"
    />
    <MetricDefinitionEditorTale
        v-if="metric.type === 'tale'"
        v-model="metric.definition"
        ref="definitionEditor"
    />

    <!-- DAG Validation Errors -->
    <div
        v-if="metric.type === 'dag' && dagValidationErrors.length > 0"
        class="alert alert-danger mt-3"
    >
        <h6 class="alert-heading">DAG Validation Errors:</h6>
        <ul class="mb-0">
            <li v-for="error in dagValidationErrors" :key="error">
                {{ error }}
            </li>
        </ul>
    </div>
</template>
<script>
import InputGroup from "@components/interactables/inputs/InputGroup.vue";
import InputText from "@components/interactables/inputs/InputText.vue";
import InputDropdown from "@components/interactables/inputs/InputDropdown.vue";
import InputNumber from "@components/interactables/inputs/InputNumber.vue";
import InputMultiCheckbox from "@components/interactables/inputs/InputMultiCheckbox.vue";
import MetricDefinitionEditorGeval from "./MetricDefinitionEditorGeval.vue";
import MetricDefinitionEditorDag from "./MetricDefinitionEditorDag.vue";
import MetricDefinitionEditorTale from "./MetricDefinitionEditorTale.vue";

export default {
    name: "MetricModal",
    components: {
        InputGroup,
        InputText,
        InputDropdown,
        InputNumber,
        InputMultiCheckbox,
        MetricDefinitionEditorGeval,
        MetricDefinitionEditorDag,
        MetricDefinitionEditorTale,
    },
    props: {
        metric: {
            type: Object,
            required: true,
        },
        models: {
            type: Array,
            default: () => [],
        },
        availableParams: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            typeOptions: [
                { value: "g-eval", label: "G-Eval" },
                { value: "dag", label: "DAG" },
                { value: "tale", label: "TALE" },
            ],
            dagValidationErrors: [],
        };
    },
    computed: {
        modelOptions() {
            return this.models.map((model) => ({
                value: model.id,
                label: model.name,
            }));
        },
    },
    watch: {
        "metric.type"(newType, oldType) {
            // Initialize definition structure when type changes
            if (
                newType === "g-eval" &&
                (!this.metric.definition ||
                    Object.keys(this.metric.definition).length === 0)
            ) {
                this.metric.definition = {
                    type: "steps",
                    steps: [""],
                    criteria: "", // Empty since we're defaulting to steps mode
                };
            } else if (
                newType === "dag" &&
                (!this.metric.definition ||
                    Object.keys(this.metric.definition).length === 0)
            ) {
                this.metric.definition = null; // Start with empty DAG
            } else if (
                newType === "tale" &&
                (!this.metric.definition ||
                    Object.keys(this.metric.definition).length === 0)
            ) {
                this.metric.definition = {
                    task: "",
                    max_search_results: 5,
                    max_iterations: 3,
                    search_engines: [],
                };
            } else if (
                newType !== "g-eval" &&
                newType !== "dag" &&
                newType !== "tale"
            ) {
                // Clear definition if not a supported type
                this.metric.definition = {};
            }

            // Clear validation errors when type changes
            this.dagValidationErrors = [];
        },
    },
    methods: {
        validate() {
            // Validate all form groups
            const basicInfoValid = this.$refs.basicInfoForm
                ? this.$refs.basicInfoForm.validate()
                : true;
            const evaluationValid = this.$refs.evaluationForm
                ? this.$refs.evaluationForm.validate()
                : true;

            // Additional custom validation for metric definitions
            let definitionValid = true;
            if (this.metric.type === "g-eval") {
                if (
                    this.$refs.definitionEditor &&
                    this.$refs.definitionEditor.validate
                ) {
                    definitionValid = this.$refs.definitionEditor.validate();
                } else {
                    // Fallback validation - ensure mutual exclusivity
                    if (this.metric.definition) {
                        if (this.metric.definition.type === "steps") {
                            definitionValid =
                                this.metric.definition.steps &&
                                this.metric.definition.steps.length > 0 &&
                                this.metric.definition.steps.every(
                                    (step) => step && step.trim().length > 0
                                ) &&
                                (!this.metric.definition.criteria ||
                                    this.metric.definition.criteria.trim() ===
                                        "");
                        } else if (this.metric.definition.type === "criteria") {
                            definitionValid =
                                this.metric.definition.criteria &&
                                this.metric.definition.criteria.trim().length >
                                    0 &&
                                (!this.metric.definition.steps ||
                                    this.metric.definition.steps.length === 0);
                        }
                    } else {
                        definitionValid = false;
                    }
                }
            } else if (this.metric.type === "dag") {
                if (
                    this.$refs.definitionEditor &&
                    this.$refs.definitionEditor.validate
                ) {
                    definitionValid = this.$refs.definitionEditor.validate();
                    if (!definitionValid) {
                        // Store DAG validation errors for display
                        this.dagValidationErrors = this.$refs.definitionEditor
                            .getValidationErrors
                            ? this.$refs.definitionEditor.getValidationErrors()
                            : ["DAG definition validation failed"];
                    } else {
                        this.dagValidationErrors = [];
                    }
                } else {
                    definitionValid = false;
                    this.dagValidationErrors = ["DAG definition is required"];
                }
            } else if (this.metric.type === "tale") {
                if (
                    this.$refs.definitionEditor &&
                    this.$refs.definitionEditor.validate
                ) {
                    definitionValid = this.$refs.definitionEditor.validate();
                } else {
                    definitionValid = false;
                }
            }

            return basicInfoValid && evaluationValid && definitionValid;
        },
        reset() {
            // Reset validation state
            if (this.$refs.basicInfoForm) {
                this.$refs.basicInfoForm.reset();
            }
            if (this.$refs.evaluationForm) {
                this.$refs.evaluationForm.reset();
            }
        },
    },
};
</script>
