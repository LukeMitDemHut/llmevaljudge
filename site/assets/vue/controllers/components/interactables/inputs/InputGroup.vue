<template>
    <div class="form-group">
        <label v-if="label" class="form-label">{{ label }}</label>
        <div
            class="input-group-wrapper"
            :class="{ 'is-invalid': !isGroupValid }"
            :aria-describedby="getAriaDescribedBy"
        >
            <slot ref="inputSlot" />
        </div>
        <small
            v-if="errorMessage && !isGroupValid"
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
    name: "InputGroup",
    props: {
        label: {
            type: String,
        },
        helpText: {
            type: String,
        },
        errorMessage: {
            type: String,
            default: "Please check the fields above",
        },
        required: {
            type: Boolean,
            default: false,
        },
        validateOnMount: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            isGroupValid: true,
            childInputs: [],
            helpId: `group-help-${Math.random().toString(36).substr(2, 9)}`,
            errorId: `group-error-${Math.random().toString(36).substr(2, 9)}`,
        };
    },
    provide() {
        return {
            inputGroup: this,
        };
    },
    computed: {
        getAriaDescribedBy() {
            const ids = [];
            if (this.helpText) {
                ids.push(this.helpId);
            }
            if (this.errorMessage && !this.isGroupValid) {
                ids.push(this.errorId);
            }
            return ids.length > 0 ? ids.join(" ") : null;
        },
    },
    mounted() {
        if (this.validateOnMount) {
            this.$nextTick(() => {
                this.validate();
            });
        }
    },
    methods: {
        registerInput(inputComponent) {
            // Method for child inputs to register themselves
            if (!this.childInputs.includes(inputComponent)) {
                this.childInputs.push(inputComponent);
                // Setup listener for this input
                if (inputComponent.$emit) {
                    inputComponent.$on?.("validation", this.onChildValidation);
                }
            }
        },

        unregisterInput(inputComponent) {
            // Method for child inputs to unregister themselves
            const index = this.childInputs.indexOf(inputComponent);
            if (index > -1) {
                this.childInputs.splice(index, 1);
                // Remove listener
                if (inputComponent.$off) {
                    inputComponent.$off("validation", this.onChildValidation);
                }
            }
        },

        onChildValidation() {
            // Debounce group validation to avoid multiple rapid calls
            clearTimeout(this.validationTimeout);
            this.validationTimeout = setTimeout(() => {
                this.validateGroup();
            }, 100);
        },

        validate() {
            // Trigger validation on all child inputs
            this.childInputs.forEach((input) => {
                if (input.validate && typeof input.validate === "function") {
                    input.validate();
                }
            });

            // Then validate the group
            return this.validateGroup();
        },

        validateGroup() {
            // Check if all child inputs are valid
            const allValid = this.childInputs.every((input) => {
                if (
                    input.validateInput &&
                    typeof input.validateInput === "function"
                ) {
                    return input.validateInput();
                }
                // If no validateInput method, check isValid data property
                return input.isValid !== false;
            });

            this.isGroupValid = allValid;
            this.$emit("validation", this.isGroupValid);

            return this.isGroupValid;
        },

        getValues() {
            // Helper method to get all values from child inputs
            const values = {};
            this.childInputs.forEach((input, index) => {
                if (input.value !== undefined) {
                    // Use input's name prop if available, otherwise use index
                    const key =
                        input.$props.name || input.inputId || `input_${index}`;
                    values[key] = input.value;
                }
            });
            return values;
        },

        reset() {
            // Reset all child inputs
            this.childInputs.forEach((input) => {
                input.reset();
            });
            this.isGroupValid = true;
        },

        focus() {
            // Focus the first input in the group
            if (this.childInputs.length > 0) {
                const firstInput = this.childInputs[0];
                if (firstInput.$el && firstInput.$el.focus) {
                    firstInput.$el.focus();
                } else if (firstInput.$el && firstInput.$el.querySelector) {
                    const input = firstInput.$el.querySelector(
                        "input, select, textarea"
                    );
                    if (input && input.focus) {
                        input.focus();
                    }
                }
            }
        },
    },

    beforeDestroy() {
        // Clean up listeners
        clearTimeout(this.validationTimeout);
        this.childInputs.forEach((input) => {
            if (input.$off) {
                input.$off("validation", this.onChildValidation);
            }
        });
    },
};
</script>

<style scoped>
.input-group-wrapper {
    position: relative;
}

.input-group-wrapper.is-invalid {
    border-left: 3px solid #dc3545;
    padding-left: 0.75rem;
    margin-left: -0.75rem;
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

/* Add some spacing between grouped inputs */
.input-group-wrapper :deep(.form-group:not(:last-child)) {
    margin-bottom: 1rem;
}

/* Reduce bottom margin for the last input in group */
.input-group-wrapper :deep(.form-group:last-child) {
    margin-bottom: 0;
}
</style>
