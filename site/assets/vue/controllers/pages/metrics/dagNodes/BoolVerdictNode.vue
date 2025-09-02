<template>
    <div class="w-100 p-3 border rounded" style="background-color: #a7d5e1">
        <div class="w-100 d-flex justify-content-between align-items-center">
            <h3>Bool Verdict Node</h3>
            <Button variant="danger" @click="$emit('remove')">
                <Icon name="trash" />
            </Button>
        </div>

        <InputGroup
            ref="inputGroup"
            errorMessage="Please complete all required fields"
        >
            <InputToggle
                v-model="modelValue.verdict"
                placeholder="The verdict or outcome"
                label="Verdict"
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
    name: "BoolVerdictNode",
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

        // Initialize verdict to false if it's undefined
        if (this.modelValue.verdict === undefined) {
            const updatedModelValue = { ...this.modelValue };
            updatedModelValue.verdict = false;
            this.$emit("update:modelValue", updatedModelValue);
        }
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
        };
    },
    emits: ["update:modelValue", "remove"],
    methods: {
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
