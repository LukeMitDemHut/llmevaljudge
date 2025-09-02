<template>
    <div class="w-100">
        <!-- Show AddChild when there's no root node -->
        <AddChild
            v-if="!hasRootNode"
            ref="rootAddChild"
            :model-value="[]"
            :allowed-children="['tasknode', 'binaryjudge', 'nonbinaryjudge']"
            :max-children="1"
            :min-children="1"
            @update:model-value="setRootNode"
        />

        <!-- Show existing root node -->
        <NextNode
            v-if="hasRootNode"
            :model-value="localValue"
            @update:model-value="updateValue"
            @remove="removeRootNode"
        />
    </div>
</template>
<script>
import NextNode from "./dagNodes/NextNode.vue";
import AddChild from "./dagNodes/AddChild.vue";

export default {
    name: "MetricDefinitionEditorDag",
    components: {
        NextNode,
        AddChild,
    },
    props: {
        modelValue: {
            type: Object,
            required: true,
        },
    },
    emits: ["update:modelValue"],
    computed: {
        localValue() {
            return this.modelValue;
        },
        hasRootNode() {
            return this.modelValue && this.modelValue.node;
        },
    },
    methods: {
        updateValue(newValue) {
            this.$emit("update:modelValue", newValue);
        },
        setRootNode(children) {
            if (children && children.length > 0) {
                this.$emit("update:modelValue", children[0]);
            }
        },
        removeRootNode() {
            // Reset to empty state
            this.$emit("update:modelValue", null);
        },
        validate() {
            let isValid = true;

            // Validate that there is a root node
            if (!this.hasRootNode) {
                if (this.$refs.rootAddChild) {
                    isValid = this.$refs.rootAddChild.validate() && isValid;
                }
                return isValid;
            }

            // Validate the entire DAG structure
            const validation = this.validateNode(this.modelValue);
            return validation.isValid && isValid;
        },
        getValidationErrors() {
            // Check for empty DAG first
            if (!this.hasRootNode) {
                return [
                    "DAG definition cannot be empty - please add a root node",
                ];
            }

            // Get detailed validation errors
            const validation = this.validateNode(this.modelValue);
            return validation.errors;
        },
        validateNode(node) {
            if (!node || !node.node) {
                return { isValid: false, errors: ["Invalid node structure"] };
            }

            const errors = [];

            // Validate node-specific requirements
            const nodeValidation = this.validateNodeFields(node);
            if (!nodeValidation.isValid) {
                errors.push(...nodeValidation.errors);
            }

            // Validate binary judge children requirements
            if (node.node === "binaryjudge") {
                const binaryValidation = this.validateBinaryJudgeChildren(node);
                if (!binaryValidation.isValid) {
                    errors.push(...binaryValidation.errors);
                }
            }

            // Validate that the tree ends with verdict nodes that have scores
            const terminalValidation = this.validateTerminalNodes(node);
            if (!terminalValidation.isValid) {
                errors.push(...terminalValidation.errors);
            }

            // Recursively validate children
            if (node.children && node.children.length > 0) {
                for (let i = 0; i < node.children.length; i++) {
                    const childValidation = this.validateNode(node.children[i]);
                    if (!childValidation.isValid) {
                        errors.push(
                            ...childValidation.errors.map(
                                (error) => `Child ${i + 1}: ${error}`
                            )
                        );
                    }
                }
            }

            return {
                isValid: errors.length === 0,
                errors: errors,
            };
        },
        validateNodeFields(node) {
            const errors = [];

            switch (node.node) {
                case "tasknode":
                    if (!node.instructions || node.instructions.trim() === "") {
                        errors.push("Task node must have instructions");
                    }
                    if (!node.outputLabel || node.outputLabel.trim() === "") {
                        errors.push("Task node must have an output label");
                    }
                    break;
                case "binaryjudge":
                case "nonbinaryjudge":
                    if (!node.criteria || node.criteria.trim() === "") {
                        errors.push("Judge node must have criteria");
                    }
                    if (!node.outputLabel || node.outputLabel.trim() === "") {
                        errors.push("Judge node must have an output label");
                    }
                    break;
                case "verdict":
                    if (
                        node.verdict === undefined ||
                        node.verdict === null ||
                        (typeof node.verdict === "string" &&
                            node.verdict.trim() === "")
                    ) {
                        errors.push("Verdict node must have a verdict value");
                    }
                    break;
                case "boolverdict":
                    if (node.verdict === undefined || node.verdict === null) {
                        errors.push(
                            "Boolean verdict node must have a verdict value"
                        );
                    }
                    break;
            }

            return {
                isValid: errors.length === 0,
                errors: errors,
            };
        },
        validateBinaryJudgeChildren(node) {
            const errors = [];

            if (!node.children || node.children.length !== 2) {
                errors.push("Binary judge must have exactly 2 children");
                return { isValid: false, errors: errors };
            }

            // Check that both children are boolean verdict nodes
            const boolVerdictChildren = node.children.filter(
                (child) => child.node === "boolverdict"
            );
            if (boolVerdictChildren.length !== 2) {
                errors.push(
                    "Binary judge children must be boolean verdict nodes"
                );
                return { isValid: false, errors: errors };
            }

            // Check for one true and one false verdict
            const trueVerdicts = boolVerdictChildren.filter(
                (child) => child.verdict === true
            );
            const falseVerdicts = boolVerdictChildren.filter(
                (child) => child.verdict === false
            );

            if (trueVerdicts.length !== 1) {
                errors.push(
                    "Binary judge must have exactly one boolean verdict child with verdict = true"
                );
            }
            if (falseVerdicts.length !== 1) {
                errors.push(
                    "Binary judge must have exactly one boolean verdict child with verdict = false"
                );
            }

            return {
                isValid: errors.length === 0,
                errors: errors,
            };
        },
        validateTerminalNodes(node) {
            const errors = [];

            // If this is a leaf node (no children), it must be a verdict with a score
            if (!node.children || node.children.length === 0) {
                if (node.node !== "verdict" && node.node !== "boolverdict") {
                    errors.push("Tree branches must end with verdict nodes");
                } else if (node.score === undefined || node.score === null) {
                    errors.push("Terminal verdict nodes must have a score");
                }
            }

            return {
                isValid: errors.length === 0,
                errors: errors,
            };
        },
    },
};
</script>
