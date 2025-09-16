<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({
  uuid: '' as string,
})

const submit = () => {
  form.post('/quiz')
}
</script>

<template>
  <div class="min-h-dvh bg-[#102846] text-white">
    <div class="mx-auto max-w-xl px-6 py-16">
      <h1 class="text-center text-3xl sm:text-4xl font-semibold tracking-wide">Jouer à un quiz</h1>
      <p class="mt-3 text-center text-white/80">Entrez l'identifiant (UUID) du quiz pour commencer.</p>

      <form @submit.prevent="submit" class="mt-8 space-y-4">
        <div>
          <label class="block text-sm text-white/80 mb-2">Identifiant du quiz (UUID)</label>
          <input
            v-model="form.uuid"
            type="text"
            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
            class="w-full rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/20 placeholder:text-white/40 focus:outline-none focus:ring-white/40"
          />
          <div v-if="form.errors.uuid" class="mt-2 text-sm text-rose-300">{{ form.errors.uuid }}</div>
        </div>

        <div class="flex items-center justify-between">
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15 disabled:opacity-60"
          >
            Continuer
          </button>

          <Link href="/" class="text-sm text-white/70 hover:text-white">Retour à l'accueil</Link>
        </div>
      </form>
    </div>
  </div>
</template>
