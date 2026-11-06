<script setup>
defineProps({
    errors: { type: Object, required: true },
});
</script>

<template>
    <ul v-if="Object.keys(errors).length" class="mb-4 text-sm text-red-700" role="alert">
        <li v-for="(message, field) in errors" :key="field">{{ message }}</li>
    </ul>
</template>
