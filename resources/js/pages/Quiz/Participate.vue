<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'

interface Answer {
  id: number
  text: string
  order?: number
}

interface Question {
  id: number
  text: string
  order?: number
  answers: Answer[]
}

type PageProps = {
  quiz: {
    uuid: string
    title: string
    status: 'waiting' | 'live' | 'finished'
    currentIndex: number
    total: number
  }
  participant: { id: number; nickname: string }
  question: Question | null
  hasAnswered: boolean
  selectedAnswerId: number | null
  submitUrl: string
}

const page = usePage<{ props: PageProps }>()
const props = computed(() => page.props as unknown as PageProps)

const letters = ['A', 'B', 'C', 'D', 'E', 'F']
const pusherStatus = ref('')

const form = useForm({
  answer_id: null as number | null,
})

const isWaiting = computed(() => props.value.quiz.status === 'waiting')
const isLive = computed(() => props.value.quiz.status === 'live')
const isFinished = computed(() => props.value.quiz.status === 'finished')

function submitAnswer(answerId: number) {
  if (props.value.hasAnswered || form.processing) return
  form.answer_id = answerId
  form.post(props.value.submitUrl, {
    preserveScroll: true,
  })
}

onMounted(async () => {
  try {
    const mod = await import('pusher-js')
    const Pusher = (mod as any).default || mod
    const key = (import.meta as any).env?.VITE_PUSHER_APP_KEY
    const cluster = (import.meta as any).env?.VITE_PUSHER_APP_CLUSTER || 'eu'
    if (!Pusher || !key) {
      pusherStatus.value = 'Temps réel non configuré — rechargez la page si l\'hôte avance.'
      return
    }
    const pusher = new Pusher(key, { cluster, forceTLS: true })
    const channel = pusher.subscribe(`quiz.${props.value.quiz.uuid}`)
    const reload = () => {
      router.reload({ preserveScroll: true })
    }
    channel.bind('advance', reload)
    channel.bind('reset', reload)
  } catch {
    pusherStatus.value = 'Impossible de se connecter au temps réel.'
  }
})
</script>

<template>
  <div class="min-h-dvh bg-[#102846] text-white">
    <div class="mx-auto max-w-3xl px-6 py-10">
      <div class="mb-6 flex items-center justify-between text-sm text-white/80">
        <div>{{ props.quiz.title }}</div>
        <div>Connecté en tant que <span class="font-semibold text-white">{{ props.participant.nickname }}</span></div>
      </div>

      <div v-if="pusherStatus" class="mb-4 text-center text-xs text-amber-200/80">{{ pusherStatus }}</div>

      <div v-if="isWaiting" class="rounded-2xl bg-[#1f57c7]/95 p-8 text-center shadow-2xl ring-1 ring-white/10">
        <h2 class="text-3xl font-semibold tracking-wide">En attente du lancement…</h2>
        <p class="mt-4 text-lg text-white/80">L'hôte va bientôt démarrer le quiz. Gardez cet écran ouvert.</p>
      </div>

      <div v-else-if="isFinished" class="rounded-2xl bg-[#1f57c7]/95 p-8 text-center shadow-2xl ring-1 ring-white/10">
        <h2 class="text-3xl font-semibold tracking-wide">Quiz terminé</h2>
        <p class="mt-4 text-lg text-white/80">Merci {{ props.participant.nickname }} pour votre participation.</p>
        <Link href="/" class="mt-6 inline-block text-sm text-white/70 underline decoration-white/30 underline-offset-4 hover:text-white">Retour à l'accueil</Link>
      </div>

      <template v-else-if="isLive && props.question">
        <div class="mb-4 text-sm text-white/70">
          Question {{ Math.min(props.quiz.currentIndex + 1, props.quiz.total) }} / {{ props.quiz.total }}
        </div>
        <div class="rounded-2xl bg-[#1f57c7]/95 p-8 shadow-2xl ring-1 ring-white/10">
          <h2 class="text-center text-3xl font-semibold leading-tight tracking-wide sm:text-4xl">{{ props.question.text }}</h2>
        </div>

        <p v-if="props.hasAnswered" class="mt-6 text-center text-emerald-200">
          Réponse enregistrée, attendez la question suivante.
        </p>
        <div v-if="form.errors.answer_id" class="mt-4 text-center text-sm text-rose-300">{{ form.errors.answer_id }}</div>

        <div class="mt-8 grid gap-4 md:grid-cols-2">
          <button
            v-for="(answer, idx) in props.question.answers"
            :key="answer.id"
            type="button"
            :disabled="props.hasAnswered || form.processing"
            class="rounded-2xl px-6 py-5 text-left text-white shadow-xl ring-1 ring-white/15 transition disabled:cursor-not-allowed disabled:opacity-70"
            :class="props.selectedAnswerId === answer.id ? 'bg-emerald-500/80' : 'bg-[#2b7cff] hover:brightness-110'"
            @click="submitAnswer(answer.id)"
          >
            <div class="flex items-center gap-4">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15 ring-2 ring-white/40">
                <span class="text-xl font-bold">{{ letters[idx] ?? String.fromCharCode(65 + idx) }}</span>
              </div>
              <div class="text-lg font-medium leading-snug tracking-wide sm:text-xl">
                {{ answer.text }}
              </div>
            </div>
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
