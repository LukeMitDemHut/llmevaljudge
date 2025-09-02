<template>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center">
            <h1 class="display-6 mb-0 me-3">
                <Icon :name="icon" class="me-2 text-primary" />
                {{ title }}
            </h1>
        </div>
        <slot></slot>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";

export default {
    name: "PageHeading",
    components: {
        Icon,
    },
    props: {
        title: {
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

<style scoped></style>
