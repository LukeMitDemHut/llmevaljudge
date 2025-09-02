<template>
    <div class="container-fluid py-4" data-bs-theme="light">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <PageHeading title="Settings" icon="gear"></PageHeading>

                <Tabs
                    :tabs="[
                        {
                            id: 'providers',
                            label: 'Providers',
                            icon: 'plugs',
                        },
                        {
                            id: 'models',
                            label: 'Models',
                            icon: 'tray',
                        },
                        {
                            id: 'system',
                            label: 'System',
                            icon: 'hard-drives',
                        },
                    ]"
                >
                    <template v-slot:providers-pane>
                        <Providers />
                    </template>
                    <template v-slot:models-pane>
                        <Models />
                    </template>
                    <template v-slot:system-pane>
                        <System />
                    </template>
                </Tabs>
            </div>
        </div>
    </div>
</template>

<script>
import { useSettingsStore } from "@stores/SettingsStore.js";
import PageHeading from "@components/structure/PageHeading.vue";
import ButtonGroup from "@components/interactables/ButtonGroup.vue";
import Button from "@components/interactables/Button.vue";
import Alert from "@components/interactables/Alert.vue";
import Tabs from "@components/structure/Tabs.vue";
import Card from "@components/structure/Card.vue";
import Providers from "./Providers.vue";
import Models from "./Models.vue";
import System from "./System.vue";

export default {
    components: {
        PageHeading,
        ButtonGroup,
        Button,
        Alert,
        Tabs,
        Card,
        Providers,
        Models,
        System,
    },
    props: {
        providers: {
            type: Array,
            default: () => [],
        },
        models: {
            type: Array,
            default: () => [],
        },
        settings: {
            type: Object,
            default: () => ({}),
        },
    },
    computed: {
        settingsStore() {
            return useSettingsStore();
        },
    },
    mounted() {
        // Initialize store with server data on mount
        this.settingsStore.initialize({
            providers: this.providers,
            models: this.models,
            settings: this.settings,
        });
    },
};
</script>
