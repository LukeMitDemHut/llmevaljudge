<template>
    <div class="form-group">
        <label v-if="label" :for="inputId" class="form-label"
            >{{ label }}{{ required ? " *" : "" }}</label
        >
        <div class="form-check form-switch" :class="{ 'is-invalid': !isValid }">
            <input
                :id="inputId"
                type="checkbox"
                class="form-check-input"
                :class="{ 'is-invalid': !isValid }"
                :checked="currentValue"
                :disabled="isDisabled"
                :required="required"
                :aria-describedby="getAriaDescribedBy"
                @change="handleInput"
                @blur="validate"
            />
            <label v-if="toggleLabel" class="form-check-label" :for="inputId">
                {{ toggleLabel }}
            </label>
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
    name: "InputToggle",
    inject: {
        inputGroup: {
            default: null,
        },
    },
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        // Keep 'value' for backward compatibility
        value: {
            type: Boolean,
            default: false,
        },
        isDisabled: {
            type: Boolean,
            default: false,
        },
        label: {
            type: String,
        },
        toggleLabel: {
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
        validator: {
            type: Function,
        },
        helpText: {
            type: String,
        },
        mustBeTrue: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            isValid: true,
            inputId: `toggle-${Math.random().toString(36).substr(2, 9)}`,
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
        handleInput(event) {
            const value = event.target.checked;
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
                if (this.required && this.mustBeTrue) {
                    return this.currentValue === true;
                } else if (this.required) {
                    return (
                        this.currentValue !== null &&
                        this.currentValue !== undefined
                    );
                }
                return true;
            }
        },
        reset() {
            // Reset the value and validation state
            this.$emit("update:modelValue", false);
            this.$emit("input", false);
            this.isValid = true;
        },
    },
};
</script>
