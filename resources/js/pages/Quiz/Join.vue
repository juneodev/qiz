<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'

const props = defineProps<{
  quiz: { uuid: string; title: string; description?: string | null }
  joinUrl: string
}>()

const form = useForm({
  nickname: '',
})

function submit() {
  form.post(`/quiz/${props.quiz.uuid}/join`)
}
</script>

<template>
  <div class="min-h-dvh bg-[#102846] text-white">
    <div class="mx-auto max-w-xl px-6 py-16">
      <h1 class="text-center text-3xl font-semibold tracking-wide sm:text-4xl">Rejoindre le quiz</h1>
      <p class="mt-3 text-center text-lg text-white/90">{{ props.quiz.title }}</p>
      <p v-if="props.quiz.description" class="mt-2 text-center text-sm text-white/70">{{ props.quiz.description }}</p>
      <p class="mt-4 text-center text-white/80">Choisissez un pseudo pour participer.</p>

      <form @submit.prevent="submit" class="mt-8 space-y-4">
        <div>
          <label class="mb-2 block text-sm text-white/80">Votre pseudo</label>
          <input
            v-model="form.nickname"
            type="text"
            maxlength="24"
            autocomplete="nickname"
            placeholder="Ex: Alex"
            class="w-full rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/20 placeholder:text-white/40 focus:outline-none focus:ring-white/40"
          />
          <div v-if="form.errors.nickname" class="mt-2 text-sm text-rose-300">{{ form.errors.nickname }}</div>
        </div>

        <div class="flex items-center justify-between">
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center rounded-full bg-[#2b7cff] px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 transition hover:brightness-110 disabled:opacity-60"
          >
            {{ form.processing ? 'Inscription…' : 'Rejoindre' }}
          </button>
          <Link href="/" class="text-sm text-white/70 hover:text-white">Retour à l'accueil</Link>
        </div>
      </form>
    </div>
  </div>
</template>
