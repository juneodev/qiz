<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Layout from './Layout.vue';

// Apply shared layout
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
defineOptions({ layout: Layout });

const form = useForm({
  title: '',
  description: '',
  publish: false,
});

function submit() {
  form.post('/quizzes', {
    preserveScroll: true,
  });
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 pb-16 pt-4">
    <h1 class="text-2xl font-semibold">Créer un quiz</h1>

    <form @submit.prevent="submit" class="mt-6 space-y-5">
      <div>
        <label class="mb-1 block text-sm text-white/80" for="title">Titre</label>
        <input
          v-model="form.title"
          id="title"
          type="text"
          class="w-full rounded-lg bg-white/10 px-3 py-2 text-white ring-1 ring-white/20 placeholder:text-white/40 focus:outline-none focus:ring-white/40"
          placeholder="Mon super quiz"
          required
        />
        <div v-if="form.errors.title" class="mt-1 text-sm text-rose-300">{{ form.errors.title }}</div>
      </div>

      <div>
        <label class="mb-1 block text-sm text-white/80" for="description">Description (optionnel)</label>
        <textarea
          v-model="form.description"
          id="description"
          rows="4"
          class="w-full rounded-lg bg-white/10 px-3 py-2 text-white ring-1 ring-white/20 placeholder:text-white/40 focus:outline-none focus:ring-white/40"
          placeholder="Décrivez brièvement votre quiz"
        />
        <div v-if="form.errors.description" class="mt-1 text-sm text-rose-300">{{ form.errors.description }}</div>
      </div>

      <label class="inline-flex items-center gap-2 text-sm text-white/80">
        <input type="checkbox" v-model="form.publish" class="h-4 w-4 rounded border-white/20 bg-white/10 text-[#2b7cff] focus:ring-white/30" />
        Publier ce quiz immédiatement
      </label>

      <div class="flex items-center gap-3 pt-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="inline-flex items-center rounded-lg bg-[#2b7cff] px-5 py-2.5 font-semibold ring-1 ring-white/20 transition hover:brightness-110 disabled:opacity-50"
        >
          {{ form.processing ? 'Création…' : 'Créer le quiz' }}
        </button>
        <a href="/quizzes" class="text-sm text-white/70 underline decoration-white/30 underline-offset-4 hover:text-white">Annuler</a>
      </div>
    </form>
  </div>
</template>
