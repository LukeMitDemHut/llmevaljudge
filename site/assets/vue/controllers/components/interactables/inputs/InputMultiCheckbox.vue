<template>
    <div class="form-group">
        <label v-if="label" class="form-label">
            {{ label }}{{ required ? " *" : "" }}
        </label>

        <!-- Select All/None controls -->
        <div class="mb-2" v-if="showSelectAll">
            <div class="btn-group btn-group-sm" role="group">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="selectAll"
                    :disabled="isDisabled"
                >
                    Select All
                </button>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="selectNone"
                    :disabled="isDisabled"
                >
                    Select None
                </button>
            </div>
            <small class="text-muted ms-2">
                {{ modelValue.length }} of {{ options.length }} selected
            </small>
        </div>

        <div
            class="multi-checkbox-container"
            :style="{ maxHeight: maxHeight, overflowY: 'auto' }"
        >
            <div class="d-flex flex-wrap">
                <div
                    class="form-check me-3 mb-2"
                    v-for="option in options"
                    :key="getOptionValue(option)"
                >
                    <input
                        type="checkbox"
                        class="form-check-input"
                        :id="`${inputId}-${getOptionValue(option)}`"
                        :value="getOptionValue(option)"
                        :checked="isChecked(option)"
                        :disabled="isDisabled"
                        @change="handleChange(option, $event)"
                    />
                    <label
                        class="form-check-label"
                        :for="`${inputId}-${getOptionValue(option)}`"
                    >
                        {{ getOptionLabel(option) }}
                    </label>
                </div>
            </div>
        </div>

        <small v-if="errorMessage && !isValid" class="form-text text-danger">
            {{ errorMessage }}
        </small>
        <small v-if="helpText" class="form-text text-muted">
            {{ helpText }}
        </small>
    </div>
</template>

<script>
export default {
    name: "InputMultiCheckbox",
    inject: {
        inputGroup: {
            default: null,
        },
    },
    props: {
        modelValue: {
            type: Array,
            default: () => [],
        },
        options: {
            type: Array,
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
        label: String,
        required: Boolean,
        isDisabled: Boolean,
        helpText: String,
        errorMessage: {
            type: String,
            default: "This field is required",
        },
        validator: {
            type: Function,
        },
        showSelectAll: {
            type: Boolean,
            default: true,
        },
        maxHeight: {
            type: String,
            default: "300px",
        },
        allSelected: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["update:modelValue", "validation", "select-all", "select-none"],
    data() {
        return {
            inputId: `multi-checkbox-${Math.random()
                .toString(36)
                .substr(2, 9)}`,
            isValid: true,
        };
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
        isChecked(option) {
            const value = this.getOptionValue(option);
            return this.modelValue.includes(value);
        },
        handleChange(option, event) {
            const value = this.getOptionValue(option);
            const newValue = [...this.modelValue];

            if (event.target.checked) {
                if (!newValue.includes(value)) {
                    newValue.push(value);
                }
            } else {
                const index = newValue.indexOf(value);
                if (index > -1) {
                    newValue.splice(index, 1);
                }
            }

            this.$emit("update:modelValue", newValue);
            this.validate();
        },
        selectAll() {
            const allValues = this.options.map((option) =>
                this.getOptionValue(option)
            );
            this.$emit("update:modelValue", allValues);
            this.$emit("select-all");
            this.validate();
        },
        selectNone() {
            this.$emit("update:modelValue", []);
            this.$emit("select-none");
            this.validate();
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
                    return this.modelValue.length > 0;
                }
                return true;
            }
        },
        reset() {
            this.$emit("update:modelValue", []);
            this.isValid = true;
        },
    },
};
</script>

<style scoped>
.multi-checkbox-container {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.75rem;
    background-color: #fff;
}
</style>
