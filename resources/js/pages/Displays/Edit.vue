<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
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

interface QuizOption {
  id: number
  title: string
}

const props = defineProps<{
  display: Display
  quizzes: QuizOption[]
}>()

const form = useForm({
  name: props.display.name,
  quiz_id: props.display.quiz_id as number | null,
})

function submit() {
  form.put(`/displays/${props.display.id}`, {
    preserveScroll: true,
  })
}

function destroy() {
  if (!confirm('Supprimer cet affichage ? L’URL de l’écran ne fonctionnera plus.')) {
    return
  }

  router.delete(`/displays/${props.display.id}`)
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 pb-16 pt-4">
    <h1 class="text-2xl font-semibold">Modifier l’affichage</h1>

    <div class="mt-6 rounded-xl border border-white/10 bg-white/5 p-5">
      <div class="mb-1 text-xs text-white/60">URL permanente de l’écran</div>
      <div class="flex flex-wrap items-center gap-2">
        <input :value="props.display.url" readonly class="h-10 min-w-0 flex-1 rounded bg-white/10 px-3 text-sm text-white ring-1 ring-white/15" />
        <button type="button" class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15" @click="navigator.clipboard.writeText(props.display.url)">Copier</button>
        <a :href="props.display.url" target="_blank" class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15">Ouvrir</a>
      </div>
      <p class="mt-2 text-xs text-white/60">Bookmarkez cette adresse sur la TV. Elle ne change jamais.</p>
    </div>

    <form @submit.prevent="submit" class="mt-6 space-y-5">
      <div>
        <label class="mb-1 block text-sm text-white/80" for="name">Nom</label>
        <input
          v-model="form.name"
          id="name"
          type="text"
          class="w-full rounded-lg bg-white/10 px-3 py-2 text-white ring-1 ring-white/20 placeholder:text-white/40 focus:outline-none focus:ring-white/40"
          required
        />
        <div v-if="form.errors.name" class="mt-1 text-sm text-rose-300">{{ form.errors.name }}</div>
      </div>

      <div>
        <label class="mb-1 block text-sm text-white/80" for="quiz_id">Quiz rattaché</label>
        <select
          id="quiz_id"
          v-model="form.quiz_id"
          class="w-full rounded-lg bg-white/10 px-3 py-2 text-white ring-1 ring-white/20 focus:outline-none focus:ring-white/40"
        >
          <option :value="null">Aucun jeu</option>
          <option v-for="quiz in props.quizzes" :key="quiz.id" :value="quiz.id">{{ quiz.title }}</option>
        </select>
        <div v-if="form.errors.quiz_id" class="mt-1 text-sm text-rose-300">{{ form.errors.quiz_id }}</div>
        <p class="mt-1 text-xs text-white/60">L’écran projettera ce quiz. Vous pourrez en changer sans modifier l’URL.</p>
      </div>

      <div class="flex flex-wrap items-center gap-3 pt-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="inline-flex items-center rounded-lg bg-[#2b7cff] px-5 py-2.5 font-semibold ring-1 ring-white/20 transition hover:brightness-110 disabled:opacity-50"
        >
          {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
        <a href="/displays" class="text-sm text-white/70 underline decoration-white/30 underline-offset-4 hover:text-white">Retour</a>
        <button
          type="button"
          class="ml-auto text-sm text-rose-300 underline decoration-rose-300/30 underline-offset-4 hover:text-rose-200"
          @click="destroy"
        >
          Supprimer
        </button>
      </div>
    </form>
  </div>
</template>
