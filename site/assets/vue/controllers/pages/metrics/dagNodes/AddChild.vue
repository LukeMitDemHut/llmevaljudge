<template>
    <div class="w-100 my-3">
        <!-- Warning message for insufficient children -->
        <div v-if="!hasMinChildren" class="alert alert-warning py-2 mb-2">
            <small>
                <Icon name="exclamation-triangle" class="me-1" />
                This node requires at least {{ minChildren }} child{{
                    minChildren === 1 ? "" : "ren"
                }}. Currently has {{ currentChildrenCount }}.
            </small>
        </div>

        <div class="d-flex gap-3 align-items-center flex-wrap">
            <Button
                v-for="allowedChild in allowedChildren"
                @click="addChild(allowedChild)"
                :disabled="modelValue && modelValue.length >= maxChildren"
                >+ {{ allowedChild }}</Button
            >
        </div>
    </div>
</template>
<script>
import Button from "@components/interactables/Button.vue";
import Icon from "@components/structure/Icon.vue";

export default {
    name: "AddChild",
    components: {
        Button,
        Icon,
    },
    props: {
        // children Array
        modelValue: {
            type: Array,
            required: false,
            default: () => [],
        },
        allowedChildren: {
            type: Array,
            required: true,
            validator(value) {
                const allowedTypes = [
                    "tasknode",
                    "binaryjudge",
                    "nonbinaryjudge",
                    "verdict",
                ];
                return value.every((type) => allowedTypes.includes(type));
            },
        },
        maxChildren: {
            type: Number,
            required: true,
            validator(value) {
                return value > 0;
            },
        },
        minChildren: {
            type: Number,
            required: true,
            validator(value) {
                return value >= 0;
            },
        },
    },
    computed: {
        currentChildrenCount() {
            return this.modelValue ? this.modelValue.length : 0;
        },
        hasMinChildren() {
            return this.currentChildrenCount >= this.minChildren;
        },
    },
    methods: {
        validate() {
            // Validation method that can be called by parent components
            return this.hasMinChildren;
        },
        addChild(childType) {
            const newChild = this.createNodeByType(childType);
            const updatedChildren = [...(this.modelValue || [])];
            updatedChildren.push(newChild);
            this.$emit("update:modelValue", updatedChildren);
        },
        createNodeByType(nodeType) {
            const baseNode = {
                node: nodeType,
                children: [],
            };

            switch (nodeType) {
                case "tasknode":
                    return {
                        ...baseNode,
                        instructions: "",
                        label: "",
                    };
                case "binaryjudge":
                    return {
                        ...baseNode,
                        criteria: "",
                        outputLabel: "",
                    };
                case "nonbinaryjudge":
                    return {
                        ...baseNode,
                        criteria: "",
                        outputLabel: "",
                    };
                case "verdict":
                    return {
                        ...baseNode,
                        verdict: "",
                        score: 0,
                    };
                case "boolverdict":
                    return {
                        ...baseNode,
                        verdict: false,
                        score: 0,
                    };
                default:
                    return baseNode;
            }
        },
    },
};
</script>
