<template>
    <form @submit.prevent ref="testCaseForm">
        <InputGroup ref="basicInfoForm">
            <InputText
                v-model="testCase.name"
                label="Name"
                placeholder="Enter test case name"
                required
            />
            <InputText
                v-model="testCase.description"
                label="Description"
                placeholder="Enter test case description (optional)"
                :multiline="true"
                :rows="3"
            />
        </InputGroup>
    </form>
</template>

<script>
import InputGroup from "@components/interactables/inputs/InputGroup.vue";
import InputText from "@components/interactables/inputs/InputText.vue";

export default {
    name: "EditTestCaseModal",
    components: {
        InputGroup,
        InputText,
    },
    props: {
        testCase: {
            type: Object,
            required: true,
        },
    },
    methods: {
        validate() {
            // Validate all form groups
            const basicInfoValid = this.$refs.basicInfoForm
                ? this.$refs.basicInfoForm.validate()
                : true;

            return basicInfoValid;
        },
        reset() {
            // Reset validation state for all form groups
            if (this.$refs.basicInfoForm) {
                this.$refs.basicInfoForm.reset();
            }
        },
    },
};
</script>
