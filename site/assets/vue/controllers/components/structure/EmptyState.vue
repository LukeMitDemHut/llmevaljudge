<template>
    <div class="text-center py-5">
        <Icon :name="icon" size="3x" class="text-muted mb-3" />
        <h5 class="text-muted">{{ title }}</h5>
        <p class="text-muted mb-4">
            {{ description }}
        </p>
        <slot></slot>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";
export default {
    components: {
        Icon,
    },
    props: {
        title: {
            type: String,
            required: true,
        },
        description: {
            type: String,
            required: true,
        },
        icon: {
            type: String,
            required: true,
        },
    },
};
</script>
