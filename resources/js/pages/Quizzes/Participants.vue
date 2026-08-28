<script setup lang="ts">
import Layout from './Layout.vue'

// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
defineOptions({ layout: Layout })

interface Participant {
  id: number
  nickname: string
  created_at: string | null
}

interface Quiz {
  id: number
  uuid: string
  title: string
  console_url: string
  edit_url: string
}

const props = defineProps<{
  quiz: Quiz
  participants: Participant[]
}>()

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleString()
}
</script>

<template>
  <div class="mx-auto max-w-5xl px-6 pb-20 pt-6">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold">Participants</h1>
        <p class="mt-1 text-sm text-white/70">{{ props.quiz.title }}</p>
      </div>
      <div class="flex items-center gap-2">
        <a :href="props.quiz.console_url" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm ring-1 ring-white/15 hover:bg-white/10">Console</a>
        <a :href="props.quiz.edit_url" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm ring-1 ring-white/15 hover:bg-white/10">Modifier</a>
      </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-white/5 p-5">
      <div class="mb-4 text-sm text-white/70">{{ props.participants.length }} participant{{ props.participants.length === 1 ? '' : 's' }}</div>

      <div v-if="props.participants.length === 0" class="rounded-lg bg-white/5 p-6 text-center text-sm text-white/70">
        Personne n'a encore rejoint ce quiz.
      </div>

      <ul v-else class="divide-y divide-white/10">
        <li v-for="participant in props.participants" :key="participant.id" class="flex items-center justify-between py-3">
          <span class="font-medium">{{ participant.nickname }}</span>
          <span class="text-xs text-white/60">Inscrit le {{ formatDate(participant.created_at) }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
