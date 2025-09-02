<template>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li
                v-for="(item, index) in items"
                :key="index"
                :class="[
                    'breadcrumb-item',
                    { active: index === items.length - 1 },
                ]"
                :aria-current="index === items.length - 1 ? 'page' : null"
            >
                <template v-if="index !== items.length - 1">
                    <a
                        :href="item.href"
                        class="text-decoration-none"
                        @click.prevent="onClick(item)"
                    >
                        <Icon v-if="item.icon" :name="item.icon" class="me-1" />
                        {{ item.label }}
                    </a>
                </template>
                <template v-else>
                    <Icon v-if="item.icon" :name="item.icon" class="me-1" />
                    {{ item.label }}
                </template>
            </li>
        </ol>
    </nav>
</template>

<script setup>
import Icon from "@components/structure/Icon.vue";

// Example: [{ label: 'Home', href: '/', icon: 'house' }, { label: 'Settings' }]
const props = defineProps({
    items: {
        type: Array,
        required: true,
        default: () => [],
    },
});

const emit = defineEmits(["navigate"]);

function onClick(item) {
    emit("navigate", { item });
}
</script>
