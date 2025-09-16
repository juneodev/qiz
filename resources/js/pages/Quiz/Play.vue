<script setup lang="ts">
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'

interface Answer { id: number; text: string; is_correct?: boolean; order?: number }
interface Question { id: number; text: string; answers: Answer[] }

type PageProps = {
  question: Question
  selectedAnswerId?: number
  isCorrect?: boolean
  currentIndex: number
  total: number
  finished?: boolean
  score?: number
  showUrl: string
  submitUrl: string
}

const page = usePage<{ props: PageProps }>()
const props = computed(() => page.props as unknown as PageProps)

const form = useForm({
  answer_id: props.value.selectedAnswerId ?? null as number | null,
})

const letters = ['A','B','C','D','E','F']

const submit = () => {
  form.post(props.value.submitUrl)
}
</script>

<template>
  <div class="min-h-dvh bg-[#102846] text-white">
    <div class="mx-auto max-w-6xl px-6 py-10">
      <!-- Header / Progress -->
      <div class="mb-6 flex items-center justify-between text-sm text-white/80">
        <div v-if="props.total !== undefined">Question {{ (props.currentIndex ?? 0) + 1 }} / {{ props.total }}</div>
        <div>
          <Link :href="props.showUrl + '?reset=1'" class="underline decoration-white/30 underline-offset-4 hover:text-white">Réinitialiser</Link>
        </div>
      </div>

      <!-- If finished, show result -->
      <div v-if="props.finished" class="rounded-2xl bg-[#1f57c7]/95 p-8 text-center shadow-2xl ring-1 ring-white/10">
        <h2 class="text-3xl sm:text-4xl font-semibold tracking-wide">Quiz terminé 🎉</h2>
        <p class="mt-4 text-lg">Votre score: <span class="font-bold">{{ props.score }}</span> / {{ props.total }}</p>
        <div class="mt-6">
          <Link :href="props.showUrl + '?reset=1'" class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">Recommencer</Link>
        </div>
      </div>

      <template v-else>
        <!-- Question card -->
        <div class="relative rounded-2xl bg-[#1f57c7]/95 shadow-2xl ring-1 ring-white/10">
          <div class="absolute left-6 top-1/2 -translate-y-1/2 hidden sm:flex h-14 w-14 items-center justify-center rounded-full bg-white/10 ring-2 ring-white/30">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7 text-white/90"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7 7h10v10H7z"/></svg>
          </div>
          <div class="absolute right-6 top-1/2 -translate-y-1/2 hidden sm:flex h-14 w-14 items-center justify-center rounded-full bg-white/10 ring-2 ring-white/30">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7 text-white/90"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7 7h10v10H7z"/></svg>
          </div>
          <div class="px-8 py-8 sm:py-10">
            <h2 class="text-center text-3xl sm:text-4xl font-semibold tracking-wide">{{ props.question.text }}</h2>
          </div>
        </div>

        <!-- Answers -->
        <form @submit.prevent="submit" class="mt-8">
          <fieldset class="grid gap-5 md:grid-cols-2">
            <label v-for="(answer, idx) in props.question.answers" :key="answer.id"
                   class="group relative cursor-pointer rounded-2xl px-6 py-5 text-white shadow-xl ring-1 transition-all hover:-translate-y-0.5 hover:shadow-2xl"
                   :class="{
                      'ring-emerald-400 bg-emerald-500': form.answer_id === answer.id && props.isCorrect === true,
                      'ring-rose-400 bg-rose-500': form.answer_id === answer.id && props.isCorrect === false,
                      'ring-white/15 bg-[#2b7cff]': !(form.answer_id === answer.id && props.isCorrect !== undefined),
                   }"
            >
              <input type="radio" name="answer_id" :value="answer.id" v-model="form.answer_id" class="peer absolute inset-0 h-full w-full cursor-pointer opacity-0" :disabled="props.isCorrect !== undefined" />
              <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15 ring-2 ring-white/40">
                  <span class="text-xl font-bold">{{ letters[idx] ?? String.fromCharCode(65 + idx) }}</span>
                </div>
                <div class="text-lg sm:text-xl font-medium tracking-wide leading-snug">
                  {{ answer.text }}
                </div>
              </div>
            </label>
          </fieldset>

          <div v-if="page.props.errors?.answer_id" class="mt-3 text-sm text-rose-300">{{ page.props.errors.answer_id }}</div>

          <div class="mt-6 flex items-center gap-4">
            <button v-if="props.isCorrect === undefined" type="submit" :disabled="form.processing || form.answer_id === null"
                    class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15 disabled:opacity-60">
              Valider ma réponse
            </button>
            <template v-else>
              <Link :href="props.showUrl + '?next=1'" class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">
                {{ (props.currentIndex + 1 >= props.total) ? 'Terminer' : 'Question suivante' }}
              </Link>
            </template>

            <Link :href="props.showUrl + '?reset=1'" class="text-sm text-white/70 hover:text-white">Réinitialiser</Link>
          </div>
        </form>

        <!-- Explanation & progress -->
        <div class="mt-8">
          <p v-if="props.isCorrect !== undefined" class="text-center text-lg italic text-white/90">
            <template v-if="props.isCorrect">Bravo ! C'est la bonne réponse 🎉</template>
            <template v-else>Dommage, ce n'est pas la bonne réponse. La bonne réponse est « {{ props.question.answers.find(a => a.is_correct)?.text }} ».</template>
          </p>
          <p v-else class="text-center text-lg italic text-white/80">Sélectionnez une réponse puis validez.</p>

          <div v-if="props.total !== undefined" class="mt-6 flex items-center">
            <div class="h-5 w-5 rounded-full bg-[#ff4b3e]"></div>
            <div class="ml-4 h-5 flex-1 rounded-full bg-white/30">
              <div class="h-5 rounded-full bg-white/70" :style="{ width: Math.max(0, Math.min(100, Math.round(((props.currentIndex ?? 0) / Math.max(1, props.total)) * 100))) + '%' }"></div>
            </div>
          </div>
        </div>
      </template>

      <div class="mt-10 text-center text-sm text-white/70">
        <Link class="underline decoration-white/30 underline-offset-4 hover:text-white" href="/">Retour à l'accueil</Link>
      </div>
    </div>
  </div>
</template>
