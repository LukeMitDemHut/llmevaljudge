<template>
    <div class="card shadow-sm">
        <div
            v-if="showHeader"
            class="card-header d-flex align-items-center justify-content-between"
        >
            <h5 class="mb-0">
                <Icon :name="icon" class="me-2 text-primary" />
                {{ title }}
            </h5>
            <div class="card-header-actions">
                <slot name="header-actions"></slot>
            </div>
        </div>
        <div class="card-body">
            <slot></slot>
        </div>
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
        },
        icon: {
            type: String,
        },
    },
    computed: {
        showHeader() {
            return this.title || this.icon;
        },
    },
};
</script>
