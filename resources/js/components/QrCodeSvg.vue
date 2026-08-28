<script setup lang="ts">
defineProps<{
  svg: string
  alt?: string
}>()
</script>

<template>
  <div
    class="inline-flex overflow-hidden rounded-xl bg-white p-2 text-[#102846]"
    role="img"
    :aria-label="alt ?? 'QR code'"
    v-html="svg"
  />
</template>
