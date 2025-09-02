<template>
    <div class="form-group">
        <label v-if="label" :for="inputId"
            >{{ label }}{{ required ? " *" : "" }}</label
        >
        <input
            :id="inputId"
            type="number"
            class="form-control"
            :class="{ 'is-invalid': !isValid }"
            :value="currentValue"
            :placeholder="placeholder"
            :min="min"
            :max="max"
            :step="step"
            :disabled="isDisabled"
            :required="required"
            :aria-describedby="getAriaDescribedBy"
            @input="handleInput"
            @blur="validate"
        />
        <small v-if="!isValid" :id="errorId" class="form-text text-danger">
            {{ getErrorMessage }}
            <br />
        </small>
        <small v-if="helpText" :id="helpId" class="form-text text-muted">
            {{ helpText }}
        </small>
    </div>
</template>
<script>
export default {
    name: "InputNumber",
    inject: {
        inputGroup: {
            default: null,
        },
    },
    props: {
        modelValue: {
            type: [Number, String],
            default: null,
        },
        // Keep 'value' for backward compatibility
        value: {
            type: [Number, String],
            default: null,
        },
        placeholder: {
            type: String,
            default: "Enter number",
        },
        min: {
            type: Number,
            default: null,
        },
        max: {
            type: Number,
            default: null,
        },
        step: {
            type: [Number, String],
            default: 1,
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
            default: "Please enter a valid number",
        },
        validationErrorMessage: {
            type: String,
            default: "",
        },
        validator: {
            type: Function,
        },
        allowDecimals: {
            type: Boolean,
            default: true,
        },
        decimalPlaces: {
            type: Number,
            default: null,
        },
        helpText: {
            type: String,
        },
    },
    data() {
        return {
            isValid: true,
            inputId: `input-${Math.random().toString(36).substr(2, 9)}`,
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
            if (!this.isValid) {
                ids.push(this.errorId);
            }
            return ids.length > 0 ? ids.join(" ") : null;
        },
        getErrorMessage() {
            // If we have a custom validator and validation failed
            if (this.validator && !this.isValid) {
                // Use custom validation error message if provided, otherwise default
                return this.validationErrorMessage || this.errorMessage;
            }
            // For default validation, use errorMessage
            return this.errorMessage;
        },
    },
    mounted() {
        // Register with parent InputGroup if it exists
        if (this.inputGroup) {
            this.inputGroup.registerInput(this);
        }

        // Validate initial value if it exists and we have a custom validator
        if (
            this.currentValue !== null &&
            this.currentValue !== undefined &&
            this.validator
        ) {
            this.validate();
        }
    },
    beforeUnmount() {
        // Unregister from parent InputGroup if it exists
        if (this.inputGroup) {
            this.inputGroup.unregisterInput(this);
        }
    },
    methods: {
        handleInput(event) {
            const parsedValue = parseFloat(event.target.value);
            const value = isNaN(parsedValue) ? null : parsedValue;
            // Emit both events for compatibility
            this.$emit("update:modelValue", value);
            this.$emit("input", value);

            // Trigger validation on input if we have a custom validator
            // This provides real-time feedback for custom validation rules
            if (this.validator) {
                this.validate();
            }
        },
        validate() {
            this.isValid = this.validateInput();
            this.$emit("validation", this.isValid);
            return this.isValid;
        },
        validateInput() {
            // Handle null/undefined values
            const currentVal = this.currentValue;

            // First check required validation
            if (
                this.required &&
                (currentVal === null ||
                    currentVal === "" ||
                    currentVal === undefined)
            ) {
                return false;
            }

            // If not required and empty, it's valid
            if (
                !this.required &&
                (currentVal === null ||
                    currentVal === "" ||
                    currentVal === undefined)
            ) {
                return true;
            }

            const numValue = parseFloat(currentVal);

            // Check if it's a valid number
            if (isNaN(numValue)) {
                return false;
            }

            // Check min/max constraints
            if (this.min !== null && numValue < this.min) {
                return false;
            }
            if (this.max !== null && numValue > this.max) {
                return false;
            }

            // Check decimal places if specified
            if (!this.allowDecimals && numValue % 1 !== 0) {
                return false;
            }

            if (this.decimalPlaces !== null) {
                const decimals = (numValue.toString().split(".")[1] || "")
                    .length;
                if (decimals > this.decimalPlaces) {
                    return false;
                }
            }

            // Finally, run custom validator if provided
            if (this.validator) {
                return this.validator(numValue);
            }

            // If no custom validator and basic validation passed
            return true;
        },
        reset() {
            // Reset the value and validation state
            this.$emit("update:modelValue", null);
            this.$emit("input", null);
            this.isValid = true;
        },
    },
};
</script>
