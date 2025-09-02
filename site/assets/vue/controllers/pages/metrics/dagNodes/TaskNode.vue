<template>
    <div class="w-100 p-3 border rounded" style="background-color: #72bbce">
        <div class="w-100 d-flex justify-content-between align-items-center">
            <h3>Task Node</h3>
            <Button variant="danger" @click="$emit('remove')">
                <Icon name="trash" />
            </Button>
        </div>

        <InputGroup
            ref="inputGroup"
            errorMessage="Please complete all required fields"
        >
            <InputText
                v-model="modelValue.instructions"
                placeholder="Do something with a parameter."
                label="Instructions"
                required
                errorMessage="Instructions are required"
            />
            <InputText
                v-model="modelValue.outputLabel"
                label="Output Label"
                placeholder="Descriptive for the result of this task"
                required
                errorMessage="Output label is required"
            />
        </InputGroup>

        <AddChild
            :model-value="modelValue.children"
            :allowed-children="['tasknode', 'binaryjudge', 'nonbinaryjudge']"
            :max-children="1"
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
import InputGroup from "@components/interactables/inputs/InputGroup.vue";
import InputText from "@components/interactables/inputs/InputText.vue";
import Button from "@components/interactables/Button.vue";
import NextNode from "./NextNode.vue";
import AddChild from "./AddChild.vue";
import Icon from "@components/structure/Icon.vue";

export default {
    name: "TaskNode",
    components: {
        NextNode,
        InputGroup,
        InputText,
        Button,
        Icon,
        AddChild,
    },
    props: {
        modelValue: {
            type: Object,
            required: true,
        },
    },
    emits: ["update:modelValue", "remove"],
    methods: {
        validate() {
            if (this.$refs.inputGroup) {
                return this.$refs.inputGroup.validate();
            }
            return true;
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
