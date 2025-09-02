<template>
    <div class="w-100 p-3 border rounded" style="background-color: #8dc8d8">
        <div class="w-100 d-flex justify-content-between align-items-center">
            <h3>Verdict Node</h3>
            <Button variant="danger" @click="$emit('remove')">
                <Icon name="trash" />
            </Button>
        </div>

        <InputGroup
            ref="inputGroup"
            errorMessage="Please complete all required fields"
        >
            <InputText
                v-model="modelValue.verdict"
                placeholder="The verdict or outcome (letters, numbers, and spaces only)"
                label="Verdict"
                required
                errorMessage="Verdict is required"
                :validator="validateVerdict"
                :validationErrorMessage="verdictValidationMessage"
            />
            <InputToggle v-model="isResult" label="Is a Result:" />
            <InputNumber
                v-if="isResult"
                v-model.number="modelValue.score"
                label="Score"
                placeholder="Numerical score (0-1)"
                decimalPlaces="1"
                type="number"
                step="0.1"
                min="0"
                max="1"
                required
                errorMessage="Score is required when this is a result"
            />
        </InputGroup>

        <AddChild
            v-if="!isResult"
            ref="addChild"
            :model-value="modelValue.children"
            :allowed-children="['tasknode', 'binaryjudge', 'nonbinaryjudge']"
            :max-children="5"
            :min-children="1"
            @update:model-value="updateChildren"
        />

        <!-- Render child nodes -->
        <div
            v-if="modelValue.children && modelValue.children.length > 0"
            class="mt-3 ms-4"
        >
            <NextNode
                v-for="(child, index) in modelValue.children"
                :key="index"
                :model-value="child"
                @update:model-value="updateChild(index, $event)"
                @remove="removeChild(index)"
            />
        </div>
    </div>
</template>
<script>
import NextNode from "./NextNode.vue";
import AddChild from "./AddChild.vue";
import InputGroup from "@components/interactables/inputs/InputGroup.vue";
import InputText from "@components/interactables/inputs/InputText.vue";
import InputNumber from "@components/interactables/inputs/InputNumber.vue";
import InputToggle from "@components/interactables/inputs/InputToggle.vue";
import Button from "@components/interactables/Button.vue";
import Icon from "@components/structure/Icon.vue";

export default {
    name: "VerdictNode",
    components: {
        NextNode,
        AddChild,
        InputGroup,
        InputText,
        InputNumber,
        InputToggle,
        Button,
        Icon,
    },
    watch: {
        isResult(newVal) {
            if (!newVal) {
                const updatedModelValue = { ...this.modelValue };
                delete updatedModelValue.score;
                updatedModelValue.children = [];
                this.$emit("update:modelValue", updatedModelValue);
            } else {
                if (this.modelValue.score === undefined) {
                    const updatedModelValue = { ...this.modelValue };
                    updatedModelValue.score = 0;
                    delete updatedModelValue.children;
                    this.$emit("update:modelValue", updatedModelValue);
                }
            }
        },
    },
    mounted() {
        this.isResult = this.modelValue.score !== undefined;
    },
    props: {
        modelValue: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            isResult: false,
            verdictValidationMessage:
                "Verdict must be 100 characters or less and can ONLY contain letters (A-Z), numbers (0-9), and spaces. Special characters like @#$% cause LLM evaluation failures because different AI models format responses inconsistently.",
        };
    },
    emits: ["update:modelValue", "remove"],
    methods: {
        validateVerdict(value) {
            // Check if value exists and is not empty
            if (!value || value.trim() === "") {
                return true; // Allow empty values, required validation handles this separately
            }

            // Convert to string to be safe
            const stringValue = String(value).trim();

            // Check length first
            if (stringValue.length > 100) {
                return false;
            }

            // Test for alphanumeric characters and spaces only
            // This will catch any special characters like @#$%"'(){}[]<>|\/
            const allowedPattern = /^[a-zA-Z0-9\s]+$/;
            if (!allowedPattern.test(stringValue)) {
                return false;
            }

            return true;
        },
        validate() {
            let isValid = true;

            // Validate input group
            if (this.$refs.inputGroup) {
                isValid = this.$refs.inputGroup.validate() && isValid;
            }

            // Validate AddChild component if not a result
            if (!this.isResult && this.$refs.addChild) {
                isValid = this.$refs.addChild.validate() && isValid;
            }

            return isValid;
        },
        updateChild(index, updatedChild) {
            const updatedModelValue = { ...this.modelValue };
            if (!updatedModelValue.children) {
                updatedModelValue.children = [];
            }
            updatedModelValue.children = [...updatedModelValue.children];
            updatedModelValue.children[index] = updatedChild;
            this.$emit("update:modelValue", updatedModelValue);
        },
        removeChild(index) {
            const updatedModelValue = { ...this.modelValue };
            if (!updatedModelValue.children) {
                return;
            }
            updatedModelValue.children = [...updatedModelValue.children];
            updatedModelValue.children.splice(index, 1);
            this.$emit("update:modelValue", updatedModelValue);
        },
        updateChildren(newChildren) {
            const updatedModelValue = { ...this.modelValue };
            updatedModelValue.children = newChildren;
            this.$emit("update:modelValue", updatedModelValue);
        },
    },
};
</script>
