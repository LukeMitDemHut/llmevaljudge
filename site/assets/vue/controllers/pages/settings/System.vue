<template>
    <Card title="System Settings">
        <InputText
            label="System Prompt"
            v-model="systemPrompt"
            :disabled="settingsStore.saving"
            :isTextarea="true"
            helpText="This prompt will be used with all test cases. Use {context} to place the context of the given Prompt in the system prompt. If {context} is not used it will not be included in the final prompt."
        />
        <Button
            variant="primary"
            class="mt-3 float-end"
            @click="saveSystemPrompt"
            :disabled="settingsStore.saving"
            :loading="settingsStore.saving"
        >
            Save
        </Button>
        <div v-if="settingsStore.hasErrors" class="mt-3">
            <Alert
                variant="danger"
                :message="Object.values(settingsStore.errors)[0]"
            />
        </div>
        <div v-if="saveSuccess" class="mt-3">
            <Alert
                variant="success"
                message="System prompt saved successfully!"
            />
        </div>
    </Card>
</template>
<script>
import { useSettingsStore } from "@stores/SettingsStore.js";
import Card from "@components/structure/Card.vue";
import InputText from "@components/interactables/inputs/InputText.vue";
import Button from "@components/interactables/Button.vue";
import Alert from "@components/interactables/Alert.vue";

export default {
    name: "SystemSettings",
    components: {
        Card,
        InputText,
        Button,
        Alert,
    },
    data() {
        return {
            systemPrompt: "",
            saveSuccess: false,
        };
    },
    computed: {
        settingsStore() {
            return useSettingsStore();
        },
    },
    watch: {
        // Watch for changes in the store settings
        "settingsStore.settings": {
            handler(newSettings) {
                if (newSettings.system_prompt !== undefined) {
                    this.systemPrompt = newSettings.system_prompt;
                }
            },
            deep: true,
            immediate: true,
        },
    },
    methods: {
        async saveSystemPrompt() {
            this.saveSuccess = false;
            this.settingsStore.clearErrors();

            try {
                await this.settingsStore.updateSetting(
                    "system_prompt",
                    this.systemPrompt
                );
                this.saveSuccess = true;

                // Hide success message after 3 seconds
                setTimeout(() => {
                    this.saveSuccess = false;
                }, 3000);
            } catch (error) {
                console.error("Failed to save system prompt:", error);
            }
        },
    },
};
</script>
