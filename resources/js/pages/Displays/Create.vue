<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import Layout from '@/pages/Quizzes/Layout.vue'

// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
defineOptions({ layout: Layout })

const form = useForm({
  name: '',
})

function submit() {
  form.post('/displays', {
    preserveScroll: true,
  })
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 pb-16 pt-4">
    <h1 class="text-2xl font-semibold">Créer un affichage</h1>
    <p class="mt-2 text-sm text-white/70">
      Donnez un nom à l’écran de la salle. L’URL permanente sera générée automatiquement.
    </p>

    <form @submit.prevent="submit" class="mt-6 space-y-5">
      <div>
        <label class="mb-1 block text-sm text-white/80" for="name">Nom</label>
        <input
          v-model="form.name"
          id="name"
          type="text"
          class="w-full rounded-lg bg-white/10 px-3 py-2 text-white ring-1 ring-white/20 placeholder:text-white/40 focus:outline-none focus:ring-white/40"
          placeholder="Salle principale"
          required
        />
        <div v-if="form.errors.name" class="mt-1 text-sm text-rose-300">{{ form.errors.name }}</div>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="inline-flex items-center rounded-lg bg-[#2b7cff] px-5 py-2.5 font-semibold ring-1 ring-white/20 transition hover:brightness-110 disabled:opacity-50"
        >
          {{ form.processing ? 'Création…' : 'Créer l’affichage' }}
        </button>
        <a href="/displays" class="text-sm text-white/70 underline decoration-white/30 underline-offset-4 hover:text-white">Annuler</a>
      </div>
    </form>
  </div>
</template>
