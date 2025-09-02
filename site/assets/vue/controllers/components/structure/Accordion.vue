<template>
    <div class="accordion" :id="accordionId">
        <div v-for="(item, idx) in items" :key="item.id" class="accordion-item">
            <h2 class="accordion-header" :id="`${item.id}Heading`">
                <button
                    class="accordion-button"
                    :class="{ collapsed: currentActive !== item.id }"
                    type="button"
                    data-bs-toggle="collapse"
                    :data-bs-target="`#${item.id}Collapse`"
                    :aria-expanded="
                        currentActive === item.id ? 'true' : 'false'
                    "
                    :aria-controls="`${item.id}Collapse`"
                    @click="updateActive(item.id)"
                >
                    <Icon
                        v-if="item.icon"
                        :name="item.icon"
                        :class="item.iconClass || ''"
                        class="me-2"
                    />
                    <strong>{{ item.label }}</strong>
                </button>
            </h2>
            <div
                :id="`${item.id}Collapse`"
                class="accordion-collapse collapse"
                :class="{ show: currentActive === item.id }"
                :aria-labelledby="`${item.id}Heading`"
                :data-bs-parent="`#${accordionId}`"
            >
                <div class="accordion-body">
                    <slot :name="`${item.id}-pane`"></slot>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";
export default {
    name: "Accordion",
    components: { Icon },
    props: {
        items: {
            type: Array,
            required: true,
            validator: (value) => {
                return value.every(
                    (item) =>
                        item.id &&
                        item.label &&
                        typeof item.id === "string" &&
                        typeof item.label === "string"
                );
            },
        },
        active: {
            type: String,
            default: null,
        },
        accordionId: {
            type: String,
            default: "accordion",
        },
    },
    data() {
        return {
            currentActive:
                this.active || (this.items.length ? this.items[0].id : null),
        };
    },
    watch: {
        active(newVal) {
            this.currentActive = newVal;
        },
    },
    methods: {
        updateActive(id) {
            this.currentActive = id;
            this.$emit("update:active", id);
        },
    },
};
</script>

<style scoped>
.accordion-button {
    cursor: pointer;
}
</style>
