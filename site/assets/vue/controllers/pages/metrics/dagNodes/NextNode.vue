<template>
    <div class="w-100">
        <TaskNode
            v-if="modelValue.node === 'tasknode'"
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
            @remove="$emit('remove')"
        />
        <BinaryJudgeNode
            v-if="modelValue.node === 'binaryjudge'"
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
            @remove="$emit('remove')"
        />
        <NonBinaryJudgeNode
            v-if="modelValue.node === 'nonbinaryjudge'"
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
            @remove="$emit('remove')"
        />
        <VerdictNode
            v-if="modelValue.node === 'verdict'"
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
            @remove="$emit('remove')"
        />
        <BoolVerdictNode
            v-if="modelValue.node === 'boolverdict'"
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
            @remove="$emit('remove')"
        />
    </div>
</template>
<script>
import { defineAsyncComponent } from "vue";

export default {
    name: "NextNode",
    components: {
        TaskNode: defineAsyncComponent(() => import("./TaskNode.vue")),
        BinaryJudgeNode: defineAsyncComponent(() =>
            import("./BinaryJudgeNode.vue")
        ),
        NonBinaryJudgeNode: defineAsyncComponent(() =>
            import("./NonBinaryJudgeNode.vue")
        ),
        VerdictNode: defineAsyncComponent(() => import("./VerdictNode.vue")),
        BoolVerdictNode: defineAsyncComponent(() =>
            import("./BoolVerdictNode.vue")
        ),
    },
    props: {
        modelValue: {
            type: Object,
            required: true,
        },
    },
    emits: ["update:modelValue", "remove"],
};
</script>
