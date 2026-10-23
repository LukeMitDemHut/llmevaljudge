import { registerVueControllerComponents } from "@symfony/ux-vue";
import "./bootstrap.js";
import "./styles/app.scss";
import "bootstrap";
import { createApp } from "vue";
import { createPinia } from "pinia";
import PrimeVue from "primevue/config";
import { auraPreset } from "jsonschema-builder-vue";

console.log("This log comes from assets/app.js - welcome to AssetMapper! 🎉");

// Register all Vue components in the vue directory
registerVueControllerComponents(
    require.context("./vue/controllers", true, /\.vue$/)
);

document.addEventListener("vue:before-mount", (event) => {
    const {
        componentName, // The Vue component's name
        component, // The resolved Vue component
        props, // The props that will be injected to the component
        app, // The Vue application instance
    } = event.detail;

    const pinia = createPinia();
    app.use(pinia);

    // jsonschema-builder-vue renders PrimeVue components internally; without a
    // global PrimeVue config their $variant computed crashes on startup
    // ("Cannot read properties of undefined (reading 'config')").
    app.use(PrimeVue, {
        theme: { preset: auraPreset },
    });
});
