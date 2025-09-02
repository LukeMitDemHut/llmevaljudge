<template>
    <div class="row">
        <div
            v-for="testCase in testCases"
            :key="testCase.id"
            class="col-12 col-md-6 col-lg-4 mb-3 w-100"
        >
            <Card
                class="h-100 cursor-pointer position-relative test-case-card"
                @click="$emit('select', testCase)"
            >
                <div class="position-absolute top-0 end-0 m-2">
                    <Dropdown @click.stop>
                        <DropdownItem
                            icon="article"
                            @click="$emit('select', testCase)"
                        >
                            View Details
                        </DropdownItem>
                        <hr class="dropdown-divider" />

                        <DropdownItem
                            icon="trash"
                            :danger="true"
                            @click="$emit('delete', testCase)"
                        >
                            Delete
                        </DropdownItem>
                    </Dropdown>
                </div>

                <div class="d-flex align-items-start">
                    <div class="flex-grow-1 pe-4">
                        <h6 class="mb-1">{{ testCase.name }}</h6>
                        <p
                            v-if="testCase.description"
                            class="text-muted small mb-2"
                        >
                            {{ testCase.description }}
                        </p>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-secondary me-2">
                                {{ getPromptCount(testCase) }} prompts
                            </span>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>

<script>
import Card from "@components/structure/Card.vue";
import Dropdown, { DropdownItem } from "@components/interactables/Dropdown.vue";
import Icon from "@components/structure/Icon.vue";

export default {
    name: "TestCaseList",
    components: {
        Card,
        Dropdown,
        DropdownItem,
        Icon,
    },
    props: {
        testCases: {
            type: Array,
            default: () => [],
        },
    },
    emits: ["select", "delete"],
    methods: {
        getPromptCount(testCase) {
            // If prompts are loaded, return actual count
            if (testCase.prompts && Array.isArray(testCase.prompts)) {
                return testCase.prompts.length;
            }
            // Use promptCount from API if available
            if (testCase.promptCount !== undefined) {
                return testCase.promptCount;
            }
            // Otherwise return 0
            return 0;
        },
    },
};
</script>

<style scoped>
.test-case-card {
    transition: all 0.2s ease;
}

.test-case-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.cursor-pointer {
    cursor: pointer;
}
</style>
