<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-status-danger dark:text-status-danger">
            {{ message }}
        </p>
    </div>
</template>
