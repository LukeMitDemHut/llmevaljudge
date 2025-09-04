<template>
    <form @submit.prevent ref="promptForm">
        <InputGroup ref="promptInfoForm">
            <InputText
                v-model="prompt.input"
                label="Input"
                placeholder="Enter the prompt input"
                required
                :multiline="true"
                :rows="4"
                :isTextarea="true"
            />
            <InputText
                v-model="prompt.expectedOutput"
                label="Expected Output (Optional)"
                placeholder="Enter the expected output (optional)"
                :multiline="true"
                :rows="4"
                :isTextarea="true"
            />
            <InputText
                v-model="prompt.context"
                label="Context (Optional)"
                placeholder="Enter additional context if needed"
                :multiline="true"
                :rows="3"
                :isTextarea="true"
            />
        </InputGroup>
    </form>
</template>

<script>
import InputGroup from "@components/interactables/inputs/InputGroup.vue";
import InputText from "@components/interactables/inputs/InputText.vue";

export default {
    name: "EditPromptModal",
    components: {
        InputGroup,
        InputText,
    },
    props: {
        prompt: {
            type: Object,
            required: true,
        },
        testCaseId: {
            type: Number,
            required: true,
        },
    },
    methods: {
        validate() {
            // Validate all form groups
            const promptInfoValid = this.$refs.promptInfoForm
                ? this.$refs.promptInfoForm.validate()
                : true;

            return promptInfoValid;
        },
        reset() {
            // Reset validation state for all form groups
            if (this.$refs.promptInfoForm) {
                this.$refs.promptInfoForm.reset();
            }
        },
    },
};
</script>
