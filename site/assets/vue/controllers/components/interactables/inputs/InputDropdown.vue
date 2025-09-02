<template>
    <div class="form-group">
        <label v-if="label" :for="inputId"
            >{{ label }}{{ required ? " *" : "" }}</label
        >
        <div class="dropdown" :class="{ 'is-invalid': !isValid }">
            <button
                :id="inputId"
                class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                :class="{ 'is-invalid': !isValid }"
                type="button"
                :disabled="isDisabled"
                :aria-describedby="getAriaDescribedBy"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                @blur="validate"
            >
                {{ selectedLabel || placeholder || "Select an option..." }}
            </button>
            <ul
                class="dropdown-menu w-100"
                style="max-height: 200px; overflow-y: auto"
            >
                <li v-if="searchable">
                    <div class="px-3 py-2">
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="Search options..."
                            v-model="searchQuery"
                            @click.stop
                        />
                    </div>
                </li>
                <li v-if="clearable && modelValue">
                    <a
                        class="dropdown-item text-muted"
                        href="#"
                        @click.prevent="selectOption(null)"
                    >
                        <em>Clear selection</em>
                    </a>
                </li>
                <li
                    v-for="option in filteredOptions"
                    :key="getOptionValue(option)"
                >
                    <a
                        class="dropdown-item"
                        :class="{
                            active: modelValue === getOptionValue(option),
                        }"
                        href="#"
                        @click.prevent="selectOption(option)"
                    >
                        {{ getOptionLabel(option) }}
                    </a>
                </li>
                <li v-if="filteredOptions.length === 0 && searchQuery">
                    <span class="dropdown-item-text text-muted"
                        >No options found</span
                    >
                </li>
            </ul>
        </div>
        <small
            v-if="errorMessage && !isValid"
            :id="errorId"
            class="form-text text-danger"
        >
            {{ errorMessage }}
            <br />
        </small>
        <small v-if="helpText" :id="helpId" class="form-text text-muted">
            {{ helpText }}
        </small>
    </div>
</template>

<script>
export default {
    name: "InputDropdown",
    inject: {
        inputGroup: {
            default: null,
        },
    },
    props: {
        modelValue: {
            type: [String, Number],
            default: "",
        },
        options: {
            type: Array,
            required: true,
            default: () => [],
        },
        valueKey: {
            type: String,
            default: "value",
        },
        labelKey: {
            type: String,
            default: "label",
        },
        placeholder: {
            type: String,
        },
        searchable: {
            type: Boolean,
            default: true,
        },
        clearable: {
            type: Boolean,
            default: true,
        },
        isDisabled: {
            type: Boolean,
            default: false,
        },
        label: {
            type: String,
        },
        required: {
            type: Boolean,
            default: false,
        },
        errorMessage: {
            type: String,
            default: "This field is required",
        },
        validationErrorMessage: {
            type: String,
            default: "",
        },
        validator: {
            type: Function,
        },
        helpText: {
            type: String,
        },
    },
    data() {
        return {
            isValid: true,
            searchQuery: "",
            inputId: `dropdown-${Math.random().toString(36).substr(2, 9)}`,
            helpId: `help-${Math.random().toString(36).substr(2, 9)}`,
            errorId: `error-${Math.random().toString(36).substr(2, 9)}`,
        };
    },
    computed: {
        selectedLabel() {
            if (!this.modelValue) return null;
            const selectedOption = this.options.find(
                (option) => this.getOptionValue(option) === this.modelValue
            );
            return selectedOption ? this.getOptionLabel(selectedOption) : null;
        },
        filteredOptions() {
            if (!this.searchable || !this.searchQuery) {
                return this.options;
            }
            return this.options.filter((option) => {
                const label = this.getOptionLabel(option).toLowerCase();
                return label.includes(this.searchQuery.toLowerCase());
            });
        },
        getAriaDescribedBy() {
            const ids = [];
            if (this.helpText) {
                ids.push(this.helpId);
            }
            if (this.errorMessage && !this.isValid) {
                ids.push(this.errorId);
            }
            return ids.length > 0 ? ids.join(" ") : null;
        },
    },
    mounted() {
        // Register with parent InputGroup if it exists
        if (this.inputGroup) {
            this.inputGroup.registerInput(this);
        }
    },
    beforeUnmount() {
        // Unregister from parent InputGroup if it exists
        if (this.inputGroup) {
            this.inputGroup.unregisterInput(this);
        }
    },
    methods: {
        getOptionValue(option) {
            // Handle both object and primitive options
            if (typeof option === "object" && option !== null) {
                return option[this.valueKey];
            }
            return option;
        },
        getOptionLabel(option) {
            // Handle both object and primitive options
            if (typeof option === "object" && option !== null) {
                return option[this.labelKey];
            }
            return option;
        },
        selectOption(option) {
            const value = option ? this.getOptionValue(option) : "";
            // Clear search query when option is selected
            this.searchQuery = "";
            // Emit both events for compatibility
            this.$emit("update:modelValue", value);
            this.$emit("input", value);
        },
        validate() {
            this.isValid = this.validateInput();
            this.$emit("validation", this.isValid);
            return this.isValid;
        },
        validateInput() {
            if (this.validator) {
                return this.validator(this.modelValue);
            } else {
                if (this.required) {
                    return (
                        this.modelValue !== null &&
                        this.modelValue !== undefined &&
                        this.modelValue !== ""
                    );
                }
                return true;
            }
        },
        reset() {
            // Reset the value, search query, and validation state
            this.$emit("update:modelValue", "");
            this.$emit("input", "");
            this.searchQuery = "";
            this.isValid = true;
        },
    },
};
</script>
