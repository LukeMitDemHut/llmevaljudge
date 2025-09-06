<template>
    <div
        class="multiselect-container"
        :class="{ 'is-open': isOpen, 'is-invalid': !isValid }"
    >
        <!-- Main input field -->
        <div
            class="multiselect-input"
            @click="toggleDropdown"
            ref="inputContainer"
        >
            <div class="selected-items">
                <span
                    v-for="item in selectedItems"
                    :key="item.id"
                    class="selected-tag"
                    :title="item.label"
                >
                    {{ truncateLabel(item.label) }}
                    <Icon
                        name="x"
                        size="sm"
                        @click.stop="removeItem(item)"
                        class="remove-icon"
                    />
                </span>
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="selectedItems.length === 0 ? placeholder : ''"
                    class="search-input"
                    @focus="openDropdown"
                    @keydown="handleKeydown"
                    ref="searchInput"
                />
            </div>
            <div class="dropdown-arrow">
                <Icon name="caret-down" :class="{ rotated: isOpen }" />
            </div>
        </div>

        <!-- Dropdown list -->
        <transition name="dropdown">
            <div v-if="isOpen" class="dropdown-list" ref="dropdown">
                <!-- Select All / Clear All buttons -->
                <div v-if="filteredOptions.length > 0" class="dropdown-actions">
                    <button
                        type="button"
                        class="action-button select-all"
                        @click="selectAll"
                        :disabled="allSelected"
                    >
                        <Icon name="check-circle" size="sm" />
                        Select All ({{ filteredOptions.length }})
                    </button>
                    <button
                        type="button"
                        class="action-button clear-all"
                        @click="clearAll"
                        :disabled="selectedItems.length === 0"
                    >
                        <Icon name="x-circle" size="sm" />
                        Clear All
                    </button>
                </div>

                <div v-if="filteredOptions.length === 0" class="no-options">
                    {{
                        searchQuery
                            ? "No options found"
                            : "No options available"
                    }}
                </div>
                <div
                    v-for="(option, index) in filteredOptions"
                    :key="option.id"
                    :class="[
                        'dropdown-option',
                        {
                            selected: isSelected(option),
                            highlighted: highlightedIndex === index,
                        },
                    ]"
                    @click="toggleOption(option)"
                    @mouseenter="highlightedIndex = index"
                >
                    <Icon
                        v-if="isSelected(option)"
                        name="check"
                        size="sm"
                        class="check-icon"
                    />
                    <span class="option-label">{{ option.label }}</span>
                </div>
            </div>
        </transition>

        <!-- Validation message -->
        <div v-if="!isValid && validationMessage" class="validation-message">
            {{ validationMessage }}
        </div>
    </div>
</template>

<script>
import Icon from "../../structure/Icon.vue";

export default {
    name: "InputMultiSelect",
    components: {
        Icon,
    },
    props: {
        options: {
            type: Array,
            required: true,
            default: () => [],
        },
        modelValue: {
            type: Array,
            default: () => [],
        },
        placeholder: {
            type: String,
            default: "Select options...",
        },
        required: {
            type: Boolean,
            default: false,
        },
        minItems: {
            type: Number,
            default: 0,
        },
        maxItems: {
            type: Number,
            default: null,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            isOpen: false,
            searchQuery: "",
            highlightedIndex: -1,
            isValid: true,
            validationMessage: "",
        };
    },
    computed: {
        selectedItems() {
            return this.options.filter((option) =>
                this.modelValue.includes(option.id)
            );
        },
        filteredOptions() {
            if (!this.searchQuery) {
                return this.options;
            }
            return this.options.filter((option) =>
                option.label
                    .toLowerCase()
                    .includes(this.searchQuery.toLowerCase())
            );
        },
        allSelected() {
            return (
                this.filteredOptions.length > 0 &&
                this.filteredOptions.every((option) =>
                    this.modelValue.includes(option.id)
                )
            );
        },
    },
    watch: {
        modelValue: {
            handler() {
                this.validate();
            },
            immediate: true,
        },
    },
    mounted() {
        document.addEventListener("click", this.handleClickOutside);
    },
    beforeUnmount() {
        document.removeEventListener("click", this.handleClickOutside);
    },
    methods: {
        toggleDropdown() {
            if (this.disabled) return;

            if (this.isOpen) {
                this.closeDropdown();
            } else {
                this.openDropdown();
            }
        },
        openDropdown() {
            if (this.disabled) return;

            this.isOpen = true;
            this.highlightedIndex = -1;
            this.$nextTick(() => {
                this.$refs.searchInput?.focus();
            });
        },
        closeDropdown() {
            this.isOpen = false;
            this.searchQuery = "";
            this.highlightedIndex = -1;
        },
        toggleOption(option) {
            if (this.disabled) return;

            const currentValues = [...this.modelValue];
            const index = currentValues.indexOf(option.id);

            if (index > -1) {
                // Remove item
                currentValues.splice(index, 1);
            } else {
                // Add item (check max limit)
                if (this.maxItems && currentValues.length >= this.maxItems) {
                    return;
                }
                currentValues.push(option.id);
            }

            this.$emit("update:modelValue", currentValues);
            this.$emit("change", currentValues);

            // Keep dropdown open for multiple selections
            this.$nextTick(() => {
                this.$refs.searchInput?.focus();
            });
        },
        removeItem(item) {
            if (this.disabled) return;

            const currentValues = this.modelValue.filter(
                (id) => id !== item.id
            );
            this.$emit("update:modelValue", currentValues);
            this.$emit("change", currentValues);
        },
        isSelected(option) {
            return this.modelValue.includes(option.id);
        },
        handleKeydown(event) {
            switch (event.key) {
                case "ArrowDown":
                    event.preventDefault();
                    this.highlightNext();
                    break;
                case "ArrowUp":
                    event.preventDefault();
                    this.highlightPrevious();
                    break;
                case "Enter":
                    event.preventDefault();
                    if (this.highlightedIndex >= 0) {
                        this.toggleOption(
                            this.filteredOptions[this.highlightedIndex]
                        );
                    }
                    break;
                case "Escape":
                    this.closeDropdown();
                    break;
                case "Backspace":
                    if (!this.searchQuery && this.selectedItems.length > 0) {
                        this.removeItem(
                            this.selectedItems[this.selectedItems.length - 1]
                        );
                    }
                    break;
            }
        },
        highlightNext() {
            if (this.highlightedIndex < this.filteredOptions.length - 1) {
                this.highlightedIndex++;
            }
        },
        highlightPrevious() {
            if (this.highlightedIndex > 0) {
                this.highlightedIndex--;
            }
        },
        handleClickOutside(event) {
            if (
                !this.$refs.inputContainer?.contains(event.target) &&
                !this.$refs.dropdown?.contains(event.target)
            ) {
                this.closeDropdown();
            }
        },
        selectAll() {
            if (this.disabled) return;

            const newValues = [...this.modelValue];
            this.filteredOptions.forEach((option) => {
                if (!newValues.includes(option.id)) {
                    // Check max limit
                    if (!this.maxItems || newValues.length < this.maxItems) {
                        newValues.push(option.id);
                    }
                }
            });

            this.$emit("update:modelValue", newValues);
            this.$emit("change", newValues);
        },
        clearAll() {
            if (this.disabled) return;

            this.$emit("update:modelValue", []);
            this.$emit("change", []);
        },
        truncateLabel(label, maxLength = 50) {
            if (label.length <= maxLength) return label;
            return label.substring(0, maxLength) + "...";
        },
        validate() {
            let valid = true;
            let message = "";

            // Check required
            if (this.required && this.modelValue.length === 0) {
                valid = false;
                message = "Please select at least one option";
            }

            // Check minimum items
            if (this.minItems > 0 && this.modelValue.length < this.minItems) {
                valid = false;
                message = `Please select at least ${this.minItems} option${
                    this.minItems > 1 ? "s" : ""
                }`;
            }

            // Check maximum items
            if (this.maxItems && this.modelValue.length > this.maxItems) {
                valid = false;
                message = `Please select no more than ${this.maxItems} option${
                    this.maxItems > 1 ? "s" : ""
                }`;
            }

            this.isValid = valid;
            this.validationMessage = message;

            this.$emit("validate", valid);

            return valid;
        },
    },
};
</script>

<style scoped>
.multiselect-container {
    position: relative;
    width: 100%;
}

.multiselect-input {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background-color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    min-height: 40px;
    padding: 4px 8px;
    transition: border-color 0.2s ease;
}

.multiselect-input:hover {
    border-color: #9ca3af;
}

.multiselect-container.is-open .multiselect-input {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

.multiselect-container.is-invalid .multiselect-input {
    border-color: #ef4444;
}

.selected-items {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    flex: 1;
    align-items: center;
}

.selected-tag {
    background-color: #e5e7eb;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 4px;
    max-width: 200px;
    overflow: hidden;
}

.selected-tag .remove-icon {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s ease;
    flex-shrink: 0;
}

.selected-tag .remove-icon:hover {
    opacity: 1;
}

.search-input {
    border: none;
    outline: none;
    flex: 1;
    min-width: 120px;
    padding: 4px;
    font-size: 14px;
}

.dropdown-arrow {
    margin-left: 8px;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.dropdown-arrow .rotated {
    transform: rotate(180deg);
}

.dropdown-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    margin-top: 2px;
}

.dropdown-actions {
    padding: 8px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    gap: 8px;
}

.action-button {
    flex: 1;
    padding: 6px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: white;
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    transition: all 0.2s ease;
}

.action-button:hover:not(:disabled) {
    background-color: #f3f4f6;
    border-color: #9ca3af;
}

.action-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.action-button.select-all {
    color: #059669;
    border-color: #d1fae5;
}

.action-button.select-all:hover:not(:disabled) {
    background-color: #ecfdf5;
    border-color: #059669;
}

.action-button.clear-all {
    color: #dc2626;
    border-color: #fecaca;
}

.action-button.clear-all:hover:not(:disabled) {
    background-color: #fef2f2;
    border-color: #dc2626;
}

.dropdown-option {
    padding: 8px 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.2s ease;
}

.dropdown-option:hover,
.dropdown-option.highlighted {
    background-color: #f3f4f6;
}

.dropdown-option.selected {
    background-color: #eff6ff;
    color: #1d4ed8;
}

.dropdown-option.selected:hover,
.dropdown-option.selected.highlighted {
    background-color: #dbeafe;
}

.check-icon {
    color: #10b981;
}

.option-label {
    flex: 1;
}

.no-options {
    padding: 12px;
    text-align: center;
    color: #6b7280;
    font-style: italic;
}

.validation-message {
    color: #ef4444;
    font-size: 12px;
    margin-top: 4px;
}

/* Dropdown transition */
.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* Disabled state */
.multiselect-container:has(.multiselect-input[disabled]) {
    opacity: 0.6;
    pointer-events: none;
}
</style>
