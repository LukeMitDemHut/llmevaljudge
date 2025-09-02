<template>
    <InputText
        class="mt-3"
        v-model="modelValue.task"
        placeholder="Enter the task for the tool. Be specific what criteria is important to look up."
        label="Task"
        required
        ref="taskInput"
    />

    <div class="row mt-3">
        <div class="col-md-4">
            <InputNumber
                v-model="modelValue.max_search_results"
                placeholder="5"
                label="Max Search Results per Iteration"
                type="number"
                min="1"
                max="50"
                step="1"
                required
                ref="maxSearchResultsInput"
            />
        </div>
        <div class="col-md-4">
            <InputNumber
                v-model="modelValue.max_iterations"
                placeholder="3"
                label="Max Iterations"
                type="number"
                min="1"
                step="1"
                max="10"
                required
                ref="maxIterationsInput"
            />
        </div>
        <div class="col-md-4">
            <InputDropdown
                v-model="modelValue.time_range"
                label="Time Range"
                placeholder="Select time range"
                :options="availableTimeRanges"
                required
                ref="timeRangeInput"
            />
        </div>
    </div>

    <h6 class="mt-3">Search-Tool Selection</h6>
    <Accordion
        class="mt-2"
        :items="toolItems"
        accordionId="metricDefinitionEditorAccordion"
        active="Scientific"
    >
        <template v-slot:Scientific-pane>
            <div>
                <div
                    v-for="engine in availableEngines[0].engines"
                    :key="engine.value"
                    class="mb-2"
                >
                    <div class="d-flex align-items-center">
                        <InputCheckbox
                            :label="engine.label"
                            :value="isEngineSelected(engine.value)"
                            :modelValue="isEngineSelected(engine.value)"
                            @update:modelValue="
                                onEngineCheckboxChange(engine.value, $event)
                            "
                            class="flex-grow-1"
                        />
                        <Icon
                            v-if="engine.supportsTimeRange"
                            name="clock"
                            size="sm"
                            class="ms-2 text-info"
                            :title="'Supports time range filtering'"
                        />
                        <Icon
                            v-else
                            name="clock"
                            size="sm"
                            class="ms-2 text-muted opacity-25"
                            :title="'Does not support time range filtering'"
                        />
                    </div>
                </div>
            </div>
        </template>
        <template v-slot:Geographic-pane>
            <div>
                <div
                    v-for="engine in availableEngines[1].engines"
                    :key="engine.value"
                    class="mb-2"
                >
                    <div class="d-flex align-items-center">
                        <InputCheckbox
                            :label="engine.label"
                            :value="isEngineSelected(engine.value)"
                            :modelValue="isEngineSelected(engine.value)"
                            @update:modelValue="
                                onEngineCheckboxChange(engine.value, $event)
                            "
                            class="flex-grow-1"
                        />
                        <Icon
                            v-if="engine.supportsTimeRange"
                            name="clock"
                            size="sm"
                            class="ms-2 text-info"
                            :title="'Supports time range filtering'"
                        />
                        <Icon
                            v-else
                            name="clock"
                            size="sm"
                            class="ms-2 text-muted opacity-25"
                            :title="'Does not support time range filtering'"
                        />
                    </div>
                </div>
            </div>
        </template>
        <template v-slot:News-pane>
            <div>
                <div
                    v-for="engine in availableEngines[2].engines"
                    :key="engine.value"
                    class="mb-2"
                >
                    <div class="d-flex align-items-center">
                        <InputCheckbox
                            :label="engine.label"
                            :value="isEngineSelected(engine.value)"
                            :modelValue="isEngineSelected(engine.value)"
                            @update:modelValue="
                                onEngineCheckboxChange(engine.value, $event)
                            "
                            class="flex-grow-1"
                        />
                        <Icon
                            v-if="engine.supportsTimeRange"
                            name="clock"
                            size="sm"
                            class="ms-2 text-info"
                            :title="'Supports time range filtering'"
                        />
                        <Icon
                            v-else
                            name="clock"
                            size="sm"
                            class="ms-2 text-muted opacity-25"
                            :title="'Does not support time range filtering'"
                        />
                    </div>
                </div>
            </div>
        </template>
        <template v-slot:Search-pane>
            <div>
                <div
                    v-for="engine in availableEngines[3].engines"
                    :key="engine.value"
                    class="mb-2"
                >
                    <div class="d-flex align-items-center">
                        <InputCheckbox
                            :label="engine.label"
                            :value="isEngineSelected(engine.value)"
                            :modelValue="isEngineSelected(engine.value)"
                            @update:modelValue="
                                onEngineCheckboxChange(engine.value, $event)
                            "
                            class="flex-grow-1"
                        />
                        <Icon
                            v-if="engine.supportsTimeRange"
                            name="clock"
                            size="sm"
                            class="ms-2 text-info"
                            :title="'Supports time range filtering'"
                        />
                        <Icon
                            v-else
                            name="clock"
                            size="sm"
                            class="ms-2 text-muted opacity-25"
                            :title="'Does not support time range filtering'"
                        />
                    </div>
                </div>
            </div>
        </template>
    </Accordion>

    <!-- Search Engines Validation Error -->
    <div v-if="showSearchEnginesError" class="alert alert-danger alert-sm mt-2">
        <small>Please select at least one search engine.</small>
    </div>
</template>
<script>
import InputText from "@components/interactables/inputs/InputText.vue";
import InputNumber from "@components/interactables/inputs/InputNumber.vue";
import InputCheckbox from "@components/interactables/inputs/InputCheckbox.vue";
import InputDropdown from "@components/interactables/inputs/InputDropdown.vue";
import Icon from "@components/structure/Icon.vue";
import Button from "@components/interactables/Button.vue";
import Accordion from "@components/structure/Accordion.vue";
import {
    AVAILABLE_TIME_RANGES,
    AVAILABLE_ENGINES,
    getEnginesForCategory,
    getTimeRangeLabel,
} from "@constants/taleConstants.js";

export default {
    name: "MetricDefinitionEditor",
    components: {
        InputText,
        InputNumber,
        InputCheckbox,
        InputDropdown,
        Accordion,
        Icon,
        Button,
    },
    data() {
        return {
            showSearchEnginesError: false,
            availableTimeRanges: AVAILABLE_TIME_RANGES,
            availableEngines: AVAILABLE_ENGINES,
        };
    },
    props: {
        modelValue: {
            type: Object,
            default: () => ({}),
        },
    },
    computed: {
        toolItems() {
            // Return the available engine categories as tool items
            return this.availableEngines.map((cat) => ({
                id: cat.key,
                label: cat.label,
                icon: cat.icon,
            }));
        },
        showTimeRangeWarning() {
            // Show warning if time range is not "all" and some selected engines don't support time range
            if (
                !this.modelValue.time_range ||
                this.modelValue.time_range === "all"
            ) {
                return false;
            }

            if (
                !Array.isArray(this.modelValue.search_engines) ||
                this.modelValue.search_engines.length === 0
            ) {
                return false;
            }

            return this.unsupportedEnginesCount > 0;
        },
        unsupportedEnginesCount() {
            if (!Array.isArray(this.modelValue.search_engines)) {
                return 0;
            }

            let count = 0;
            const allEngines = this.availableEngines.flatMap(
                (cat) => cat.engines
            );

            this.modelValue.search_engines.forEach((engineValue) => {
                const engine = allEngines.find((e) => e.value === engineValue);
                if (engine && !engine.supportsTimeRange) {
                    count++;
                }
            });

            return count;
        },
    },
    methods: {
        getEnginesForCategory,
        getTimeRangeLabel,
        isEngineSelected(engineValue) {
            return (
                Array.isArray(this.modelValue.search_engines) &&
                this.modelValue.search_engines.includes(engineValue)
            );
        },
        onEngineCheckboxChange(engineValue, checked) {
            let arr = Array.isArray(this.modelValue.search_engines)
                ? [...this.modelValue.search_engines]
                : [];
            if (checked && !arr.includes(engineValue)) {
                arr.push(engineValue);
            } else if (!checked && arr.includes(engineValue)) {
                arr = arr.filter((v) => v !== engineValue);
            }

            // Clear search engines error if at least one engine is selected
            if (arr.length > 0) {
                this.showSearchEnginesError = false;
            }

            this.$emit("update:modelValue", {
                ...this.modelValue,
                search_engines: arr,
            });
        },
        validate() {
            let isValid = true;

            // check if at least one search engine is selected
            const hasSearchEngines =
                Array.isArray(this.modelValue.search_engines) &&
                this.modelValue.search_engines.length > 0;
            this.showSearchEnginesError = !hasSearchEngines;

            isValid = isValid && hasSearchEngines;

            // validate all inputs
            if (this.$refs.taskInput) {
                isValid = this.$refs.taskInput.validate() && isValid;
            }

            if (this.$refs.maxSearchResultsInput) {
                isValid =
                    this.$refs.maxSearchResultsInput.validate() && isValid;
            }

            if (this.$refs.timeRangeInput) {
                isValid = this.$refs.timeRangeInput.validate() && isValid;
            }

            return isValid;
        },
    },
    mounted() {
        // Initialize default values if not already set
        const defaults = {
            max_search_results: this.modelValue.max_search_results || 5,
            max_iterations: this.modelValue.max_iterations || 3,
            time_range: this.modelValue.time_range || "all",
            search_engines: this.modelValue.search_engines || [],
            task: this.modelValue.task || "",
        };

        // Only emit if there are actual changes
        if (
            JSON.stringify({ ...this.modelValue, ...defaults }) !==
            JSON.stringify(this.modelValue)
        ) {
            this.$emit("update:modelValue", {
                ...this.modelValue,
                ...defaults,
            });
        }
    },
};
</script>
