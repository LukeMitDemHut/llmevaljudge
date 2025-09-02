<template>
    <div class="form-group">
        <fieldset :aria-describedby="getAriaDescribedBy">
            <legend v-if="label" class="form-label">
                {{ label }}{{ required ? " *" : "" }}
            </legend>
            <div
                v-for="option in options"
                :key="option.value"
                class="form-check"
                :class="{ 'form-check-inline': inline }"
            >
                <input
                    :id="`${inputId}-${option.value}`"
                    type="radio"
                    class="form-check-input"
                    :class="{ 'is-invalid': !isValid }"
                    :name="inputId"
                    :value="option.value"
                    :checked="currentValue === option.value"
                    :disabled="isDisabled || option.disabled"
                    :required="required"
                    @change="handleInput(option.value)"
                    @blur="validate"
                />
                <label
                    class="form-check-label"
                    :for="`${inputId}-${option.value}`"
                >
                    {{ option.label }}
                </label>
            </div>
        </fieldset>
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
    name: "InputRadio",
    inject: {
        inputGroup: {
            default: null,
        },
    },
    props: {
        modelValue: {
            type: [String, Number, Boolean],
            default: null,
        },
        // Keep 'value' for backward compatibility
        value: {
            type: [String, Number, Boolean],
            default: null,
        },
        options: {
            type: Array,
            required: true,
            validator: (options) => {
                return options.every(
                    (option) =>
                        option &&
                        typeof option === "object" &&
                        "value" in option &&
                        "label" in option
                );
            },
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
            default: "Please select an option",
        },
        validator: {
            type: Function,
        },
        helpText: {
            type: String,
        },
        inline: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            isValid: true,
            inputId: `radio-${Math.random().toString(36).substr(2, 9)}`,
            helpId: `help-${Math.random().toString(36).substr(2, 9)}`,
            errorId: `error-${Math.random().toString(36).substr(2, 9)}`,
        };
    },
    computed: {
        currentValue() {
            // Use modelValue if available (Vue 3 style), otherwise fall back to value (Vue 2 style)
            return this.modelValue !== undefined ? this.modelValue : this.value;
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
        handleInput(value) {
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
                return this.validator(this.currentValue);
            } else {
                if (this.required) {
                    return (
                        this.currentValue !== null &&
                        this.currentValue !== undefined &&
                        this.currentValue !== ""
                    );
                }
                return true;
            }
        },
        getOptionByValue(value) {
            return this.options.find((option) => option.value === value);
        },
        reset() {
            this.$emit("update:modelValue", null);
            this.$emit("input", null);
            this.isValid = true;
        },
    },
};
</script>

<style scoped>
fieldset {
    border: none;
    padding: 0;
    margin: 0;
}

legend.form-label {
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    width: auto;
    padding: 0;
    border: none;
}

.form-check:not(.form-check-inline):not(:last-child) {
    margin-bottom: 0.375rem;
}

.form-check-inline {
    margin-right: 1rem;
}
</style>
