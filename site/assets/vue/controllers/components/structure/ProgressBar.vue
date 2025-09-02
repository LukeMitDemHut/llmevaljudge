<template>
    <div class="w-100 border my-2 border-primary rounded">
        <div
            class="bg-primary bg-opacity-50 rounded h-1 progress"
            :style="{ width: `${value}%` }"
        ></div>
    </div>
</template>

<script>
export default {
    name: "ProgressBar",
    props: {
        value: {
            type: Number,
            required: true,
            validator: (v) => v >= 0 && v <= 100,
        },
    },
};
</script>
<style scoped>
.progress {
    height: 4px;
}
</style>
