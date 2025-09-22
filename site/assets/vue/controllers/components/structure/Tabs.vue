<template>
    <ul
        class="nav mb-4 border-bottom border-2 flex-nowrap overflow-auto hide-scrollbar"
        id="settingsTabs"
        role="tablist"
    >
        <li
            v-for="tab in tabs"
            class="nav-item text-nowrap"
            role="presentation"
        >
            <button
                class="nav-link border-0 fw-medium px-3 py-3 position-relative tab-button"
                :class="{
                    'text-primary': currentActiveTab === tab.id,
                    'text-secondary': currentActiveTab !== tab.id,
                    active: currentActiveTab === tab.id,
                }"
                :id="`${tab.id}-tab`"
                data-bs-toggle="tab"
                type="button"
                role="tab"
                :aria-selected="currentActiveTab === tab.id"
                @click="updateTab(tab.id)"
            >
                <Icon :name="tab.icon"></Icon>
                {{ tab.label }}
                <span
                    v-if="tab.mark"
                    :class="`bg-${tab.mark}`"
                    id="mark"
                ></span>
            </button>
        </li>
    </ul>
    <div class="tab-content">
        <div
            v-for="tab in tabs"
            :key="tab.id"
            :class="{
                'tab-pane': true,
                fade: true,
                show: currentActiveTab === tab.id,
                active: currentActiveTab === tab.id,
            }"
            :id="`${tab.id}-pane`"
            role="tabpanel"
            :aria-labelledby="`${tab.id}-tab`"
        >
            <slot :name="`${tab.id}-pane`"></slot>
        </div>
    </div>
</template>

<script>
import Icon from "@components/structure/Icon.vue";
export default {
    components: {
        Icon,
    },
    data() {
        return {
            currentActiveTab: this.activeTab,
        };
    },
    props: {
        tabs: {
            type: Array,
            required: true,
            validator: (value) => {
                return value.every((tab) => {
                    return (
                        tab.id &&
                        tab.label &&
                        tab.icon &&
                        typeof tab.id === "string" &&
                        typeof tab.label === "string" &&
                        typeof tab.icon === "string"
                    );
                });
            },
        },
        activeTab: {
            type: String,
        },
        mark: {
            type: String,
        },
    },
    methods: {
        updateTab(tabId) {
            this.currentActiveTab = tabId;
            this.$emit("update:activeTab", tabId);
        },
    },
    beforeMount() {
        if (!this.activeTab) {
            this.currentActiveTab = this.tabs[0].id;
        } else {
            this.currentActiveTab = this.activeTab;
        }
    },
    watch: {
        activeTab(newVal) {
            this.currentActiveTab = newVal;
        },
    },
};
</script>

<style scoped>
.tab-button {
    transition: color 0.2s ease-in-out;
    position: relative;
}

.tab-button::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background-color: var(--bs-primary);
    opacity: 0;
    transition: opacity 0.2s ease-in-out;
}

.tab-button:hover:not(.active)::after,
.tab-button.active::after {
    opacity: 1;
}

.tab-button:hover:not(.active) {
    color: var(--bs-primary) !important;
}

.tab-button.active {
    color: var(--bs-primary) !important;
}

#mark {
    position: relative;
    display: inline-block;
    margin-left: 4px;
    transform: translateY(-2px);
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.hide-scrollbar {
    -ms-overflow-style: none; /* IE and Edge */
    scrollbar-width: none; /* Firefox */
}
.hide-scrollbar::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera*/
}
</style>
