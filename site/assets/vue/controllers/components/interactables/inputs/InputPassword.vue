<template>
    <div class="form-group">
        <label v-if="label" :for="inputId"
            >{{ label }}{{ required ? " *" : "" }}</label
        >
        <div class="input-group">
            <input
                :id="inputId"
                :type="showPassword ? 'text' : 'password'"
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
            <div class="input-group-append">
                <button
                    class="btn btn-outline-secondary"
                    type="button"
                    :disabled="isDisabled"
                    @click="togglePasswordVisibility"
                    :aria-label="
                        showPassword ? 'Hide password' : 'Show password'
                    "
                >
                    <Icon name="eye" v-if="!showPassword" />
                    <Icon name="eye-slash" v-if="showPassword" />
                </button>
            </div>
        </div>
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
import Icon from "@components/structure/Icon.vue";

export default {
    name: "InputPassword",
    inject: {
        inputGroup: {
            default: null,
        },
    },
    components: {
        Icon,
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
            default: "Enter password",
        },
        maxLength: {
            type: Number,
            default: 0,
        },
        minLength: {
            type: Number,
            default: 8,
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
            default: "Password must be at least 8 characters long",
        },
        validationErrorMessage: {
            type: String,
            default: "",
        },
        validator: {
            type: Function,
        },
        requireUppercase: {
            type: Boolean,
            default: false,
        },
        requireLowercase: {
            type: Boolean,
            default: false,
        },
        requireNumbers: {
            type: Boolean,
            default: false,
        },
        requireSpecialChars: {
            type: Boolean,
            default: false,
        },
        helpText: {
            type: String,
        },
    },
    data() {
        return {
            isValid: true,
            showPassword: false,
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
        togglePasswordVisibility() {
            this.showPassword = !this.showPassword;
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

            // Check length constraints (only if value exists or is required)
            if (value || this.required) {
                if (value.length < this.minLength) {
                    return false;
                }
                if (this.maxLength > 0 && value.length > this.maxLength) {
                    return false;
                }

                // Check password complexity requirements
                if (this.requireUppercase && !/[A-Z]/.test(value)) {
                    return false;
                }
                if (this.requireLowercase && !/[a-z]/.test(value)) {
                    return false;
                }
                if (this.requireNumbers && !/\d/.test(value)) {
                    return false;
                }
                if (
                    this.requireSpecialChars &&
                    !/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(value)
                ) {
                    return false;
                }
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
            this.showPassword = false; // Also reset password visibility
        },
    },
};
</script>
