<script setup lang="ts">
import { computed } from 'vue'
import Layout from '@/pages/Quizzes/Layout.vue'

// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
defineOptions({ layout: Layout })

interface Display {
  id: number
  uuid: string
  name: string
  url: string
  quiz_id: number | null
  quiz_title: string | null
}

const props = defineProps<{ displays: Display[] }>()

const hasDisplays = computed(() => props.displays && props.displays.length > 0)
</script>

<template>
  <div class="mx-auto max-w-7xl px-6 pb-16 pt-4">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Mes affichages</h1>
      <a
        href="/displays/create"
        class="inline-flex items-center justify-center rounded-lg bg-[#2b7cff] px-4 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:brightness-110"
      >Créer un affichage</a>
    </div>

    <p class="mb-6 max-w-2xl text-sm text-white/70">
      Un affichage a une URL permanente à bookmarker sur la TV de la salle. Rattachez-y un quiz : l’écran projette ce contenu, sans changer d’adresse.
    </p>

    <div v-if="hasDisplays" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="display in props.displays"
        :key="display.id"
        class="rounded-xl bg-white/5 p-5 ring-1 ring-white/10 transition hover:bg-white/[0.08]"
      >
        <div class="flex items-start justify-between gap-4">
          <h2 class="line-clamp-2 text-base font-semibold">{{ display.name }}</h2>
          <span
            class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1"
            :class="display.quiz_id ? 'bg-emerald-500/15 text-emerald-200 ring-emerald-500/30' : 'bg-white/10 text-white/70 ring-white/20'"
          >
            {{ display.quiz_title ?? 'Aucun jeu' }}
          </span>
        </div>
        <div class="mt-4 space-y-3">
          <div class="flex items-center gap-2">
            <input :value="display.url" readonly class="w-full rounded bg-white/10 px-2 py-1 text-xs text-white ring-1 ring-white/15" />
            <button type="button" class="rounded bg-white/10 px-2 py-1 text-xs ring-1 ring-white/20 hover:bg-white/15" @click="navigator.clipboard.writeText(display.url)">Copier</button>
          </div>
          <div class="flex items-center justify-between text-xs text-white/60">
            <a :href="display.url" target="_blank" class="underline decoration-white/30 underline-offset-4 hover:text-white">Ouvrir</a>
            <a :href="`/displays/${display.id}/edit`" class="underline decoration-white/30 underline-offset-4 hover:text-white">Modifier</a>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="rounded-xl border border-white/10 bg-white/5 p-8 text-center text-white/80">
      Aucun affichage pour le moment.
      <a href="/displays/create" class="underline decoration-white/30 underline-offset-4 hover:text-white">Créer un affichage</a>
    </div>
  </div>
</template>
