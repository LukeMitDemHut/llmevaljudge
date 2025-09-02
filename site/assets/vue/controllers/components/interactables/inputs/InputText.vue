<template>
    <div class="form-group">
        <label v-if="label" :for="inputId"
            >{{ label }}{{ required ? " *" : "" }}</label
        >
        <textarea
            v-if="isTextarea"
            :id="inputId"
            class="form-control"
            :class="{ 'is-invalid': !isValid }"
            :value="currentValue"
            :placeholder="placeholder"
            :maxlength="maxLength === 0 ? undefined : maxLength"
            :disabled="isDisabled"
            :required="required"
            :aria-describedby="getAriaDescribedBy"
            :rows="textareaRows"
            @input="handleInput"
            @blur="validate"
        ></textarea>
        <input
            v-else
            :id="inputId"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': !isValid }"
            :value="currentValue"
            :placeholder="placeholder"
            :maxlength="maxLength === 0 ? undefined : maxLength"
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
    name: "InputText",
    inject: {
        inputGroup: {
            default: null,
        },
    },
    props: {
        modelValue: {
            type: String,
            default: "",
        },
        // Keep 'value' for backward compatibility
        value: {
            type: String,
            default: "",
        },
        placeholder: {
            type: String,
        },
        maxLength: {
            type: Number,
            default: 0,
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
        isTextarea: {
            type: Boolean,
            default: false,
        },
        textareaRows: {
            type: Number,
            default: 3,
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
            // For default validation (required, maxLength), use errorMessage
            return this.errorMessage;
        },
    },
    mounted() {
        // Register with parent InputGroup if it exists
        if (this.inputGroup) {
            this.inputGroup.registerInput(this);
        }

        // Validate initial value if it exists and we have a custom validator
        if (this.currentValue && this.validator) {
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
            const value = event.target.value;
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
            const value = this.currentValue || "";

            // First check required validation
            if (this.required && value.trim() === "") {
                return false;
            }

            // Check max length (only if maxLength is set)
            if (this.maxLength > 0 && value.length > this.maxLength) {
                return false;
            }

            // Finally, run custom validator if provided
            if (this.validator) {
                return this.validator(value);
            }

            // If no custom validator and basic validation passed
            return true;
        },
        reset() {
            // Reset the value and validation state
            this.$emit("update:modelValue", "");
            this.$emit("input", "");
            this.isValid = true;
        },
    },
};
</script>
