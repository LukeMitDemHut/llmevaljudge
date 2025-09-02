<template>
    <div class="d-flex flex-wrap">
        <div
            v-for="metric in metricsStore.metrics"
            :key="metric.id"
            class="col-12 col-lg-6 p-2"
        >
            <Card class="w-100">
                <div
                    class="d-flex justify-content-between align-items-start mb-3"
                >
                    <h5
                        class="card-title mb-0 text-truncate me-2"
                        style="min-width: 0"
                    >
                        {{ metric.name }}
                    </h5>
                    <Dropdown>
                        <DropdownItem
                            icon="files"
                            @click="$emit('duplicate', metric)"
                        >
                            Duplicate
                        </DropdownItem>
                        <DropdownItem
                            icon="article"
                            @click="$emit('select', metric)"
                        >
                            View Details
                        </DropdownItem>
                        <hr class="dropdown-divider" />

                        <DropdownItem
                            icon="trash"
                            :danger="true"
                            @click="$emit('delete', metric)"
                        >
                            Delete
                        </DropdownItem>
                    </Dropdown>
                </div>
                <div class="mb-3">
                    <span class="badge" :class="getTypeBadgeClass(metric.type)">
                        <Icon :name="getTypeIcon(metric.type)" class="me-1" />
                        <span>{{ metric.type }}</span>
                    </span>
                </div>

                <!-- Description -->
                <div class="mb-3 flex-grow-1">
                    <p class="text-muted small mb-0" v-if="metric.description">
                        {{ metric.description }}
                    </p>
                    <p class="text-muted small mb-0 fst-italic" v-else>
                        No description provided
                    </p>
                </div>

                <!-- Details -->
                <div class="mt-auto">
                    <!-- Threshold -->
                    <div
                        class="d-flex justify-content-between align-items-center mb-2"
                    >
                        <span class="small text-muted">Threshold:</span>
                        <span class="badge bg-light text-dark">
                            {{ (metric.threshold * 100).toFixed(0) }}%
                        </span>
                    </div>

                    <!-- Rating Model -->
                    <div
                        class="d-flex justify-content-between align-items-center"
                    >
                        <span class="small text-muted">Rating Model:</span>
                        <span class="small">
                            {{ metric.ratingModel?.name || "N/A" }}
                        </span>
                    </div>

                    <!-- G-Eval specific info -->
                    <div
                        v-if="metric.type === 'g-eval' && metric.definition"
                        class="mt-2"
                    >
                        <div
                            v-if="
                                metric.definition.steps &&
                                metric.definition.steps.length > 0
                            "
                        >
                            <div
                                class="d-flex flex-row justify-content-between align-items-center mb-2"
                            >
                                <span class="small text-muted mb-1"
                                    >Steps:</span
                                >
                                <span class="badge bg-info">
                                    {{ metric.definition.steps.length }} step{{
                                        metric.definition.steps.length !== 1
                                            ? "s"
                                            : ""
                                    }}
                                </span>
                            </div>
                        </div>
                        <div v-if="metric.definition.criteria">
                            <span class="small text-muted mb-1">Criteria:</span>
                            <span class="badge bg-info">Defined</span>
                        </div>
                    </div>

                    <div v-if="metric.type === 'dag'" class="mt-2">
                        <span class="badge bg-warning text-dark">
                            <Icon name="info-circle" class="me-1" />
                            DAG
                        </span>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>
<script>
import { useMetricsStore } from "@stores/MetricsStore.js";
import Card from "@components/structure/Card.vue";
import Dropdown, { DropdownItem } from "@components/interactables/Dropdown.vue";
import Icon from "@components/structure/Icon.vue";

export default {
    name: "MetricList",
    components: {
        Card,
        Dropdown,
        DropdownItem,
        Icon,
    },
    computed: {
        metricsStore() {
            return useMetricsStore();
        },
    },
    methods: {
        getTypeBadgeClass(type) {
            switch (type) {
                case "g-eval":
                    return "bg-success";
                case "dag":
                    return "bg-warning";
                default:
                    return "bg-secondary";
            }
        },

        getTypeIcon(type) {
            switch (type) {
                case "g-eval":
                    return "cpu";
                case "dag":
                    return "arrows-split";
            }
        },
    },
};
</script>
