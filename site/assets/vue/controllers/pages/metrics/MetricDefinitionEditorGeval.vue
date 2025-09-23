<template>
    <div class="my-3">
        <label class="form-label">Evaluation Mode</label>
        <div class="btn-group w-100" role="group">
            <input
                type="radio"
                class="btn-check"
                name="geval-mode"
                id="mode-steps"
                value="steps"
                v-model="modelValue.type"
            />
            <label class="btn btn-outline-primary" for="mode-steps">
                <Icon name="check-square" class="me-1" />
                Steps-based
            </label>

            <input
                type="radio"
                class="btn-check"
                name="geval-mode"
                id="mode-criteria"
                value="criteria"
                v-model="modelValue.type"
            />
            <label class="btn btn-outline-primary" for="mode-criteria">
                <Icon name="article" class="me-1" />
                Criteria-based
            </label>
        </div>
    </div>
    <div v-if="modelValue.type === 'steps'">
        <label class="form-label">Steps</label>
        <div class="step-list">
            <draggable
                v-model="modelValue.steps"
                item-key="index"
                class="step-list"
                :animation="200"
            >
                <template #item="{ element, index }">
                    <div class="step-item d-flex align-items-start mb-3">
                        <Button
                            variant="outline-secondary"
                            size="sm"
                            class="step-handle me-1"
                            style="cursor: grab"
                        >
                            <Icon name="dots-six-vertical" />
                            <span class="ms-1">{{ index + 1 }}</span>
                        </Button>
                        <div class="flex-grow-1">
                            <InputText
                                v-model="modelValue.steps[index]"
                                :placeholder="`Step ${index + 1}...`"
                                :isTextarea="true"
                            />
                        </div>
                        <Button
                            @click="removeStep(index)"
                            variant="outline-danger"
                            size="sm"
                            class="ms-1"
                            :disabled="modelValue.steps.length <= 1"
                        >
                            <Icon name="trash" />
                        </Button>
                    </div>
                </template>
            </draggable>
        </div>
        <div class="mt-2">
            <Button
                @click="addStep"
                variant="outline-primary"
                size="sm"
                class="w-100"
            >
                <Icon name="plus" class="me-1" />
                Add Step
            </Button>
        </div>
    </div>
    <div v-else-if="modelValue.type === 'criteria'">
        <InputText
            label="Criteria"
            v-model="modelValue.criteria"
            placeholder="Define the criteria for evaluation"
            class="mb-3"
        />
    </div>
</template>
<script>
import draggable from "vuedraggable";
import InputText from "@components/interactables/inputs/InputText.vue";
import Icon from "@components/structure/Icon.vue";
import Button from "@components/interactables/Button.vue";

export default {
    name: "MetricDefinitionEditor",
    components: {
        draggable,
        InputText,
        Icon,
        Button,
    },
    props: {
        modelValue: {
            type: Object,
            default: () => ({}),
        },
    },
    mounted() {
        // Ensure proper initialization on mount
        if (this.modelValue.type) {
            if (this.modelValue.type === "steps") {
                this.modelValue.criteria = "";
                if (
                    !this.modelValue.steps ||
                    this.modelValue.steps.length === 0
                ) {
                    this.modelValue.steps = [""];
                }
            } else if (this.modelValue.type === "criteria") {
                this.modelValue.steps = [];
                if (!this.modelValue.criteria) {
                    this.modelValue.criteria = "";
                }
            }
        }
    },
    watch: {
        "modelValue.type"(newType) {
            // Clear the opposite data when type changes to ensure mutual exclusivity
            if (newType === "steps") {
                // Clear criteria data and initialize steps
                this.modelValue.criteria = "";
                if (
                    !this.modelValue.steps ||
                    this.modelValue.steps.length === 0
                ) {
                    this.modelValue.steps = [""];
                }
            } else if (newType === "criteria") {
                // Clear steps data and initialize criteria
                this.modelValue.steps = [];
                if (!this.modelValue.criteria) {
                    this.modelValue.criteria = "";
                }
            }
        },
    },
    methods: {
        removeStep(index) {
            if (this.modelValue.steps.length > 1) {
                this.modelValue.steps.splice(index, 1);
            }
        },
        addStep() {
            if (!this.modelValue.steps) {
                this.modelValue.steps = [];
            }
            this.modelValue.steps.push("");
        },
        validate() {
            if (!this.modelValue.type) {
                return false;
            }

            if (this.modelValue.type === "steps") {
                // For steps mode: must have at least one non-empty step, and criteria should be empty
                return (
                    this.modelValue.steps &&
                    this.modelValue.steps.length > 0 &&
                    this.modelValue.steps.every(
                        (step) => step && step.trim().length > 0
                    ) &&
                    (!this.modelValue.criteria ||
                        this.modelValue.criteria.trim() === "")
                );
            } else if (this.modelValue.type === "criteria") {
                // For criteria mode: must have non-empty criteria, and steps should be empty
                return (
                    this.modelValue.criteria &&
                    this.modelValue.criteria.trim().length > 0 &&
                    (!this.modelValue.steps ||
                        this.modelValue.steps.length === 0)
                );
            }

            return false;
        },
    },
};
</script>
