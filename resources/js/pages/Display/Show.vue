<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import QrCodeSvg from '@/components/QrCodeSvg.vue'
import { useQuestionTimer } from '@/composables/useQuestionTimer'

interface Answer { id: number; text: string; order?: number }
interface Question { id: number; text: string; order?: number; answers: Answer[] }

interface QuizPayload {
  uuid: string
  title: string
  status: 'waiting' | 'live' | 'finished'
  currentIndex: number
  questions: Question[]
  total: number
  joinUrl: string
  qrSvg: string
  participantCount: number
  answerClosesAt: string | null
  answerDurationSeconds: number
  correctAnswerIds: number[]
  recap: {
    total: number
    questions: { id: number; text: string; correctAnswerIds: number[]; correctTexts: string[] }[]
  } | null
}

interface DisplayPayload {
  uuid: string
  name: string
}

const props = defineProps<{
  display: DisplayPayload
  quiz: QuizPayload | null
}>()

const letters = ['A', 'B', 'C', 'D', 'E', 'F']

const status = ref(props.quiz?.status ?? 'waiting')
const currentIndex = ref(props.quiz?.currentIndex ?? 0)
const participantCount = ref(props.quiz?.participantCount ?? 0)
const answerClosesAt = ref(props.quiz?.answerClosesAt ?? null)
const correctAnswerIds = ref<number[]>(props.quiz?.correctAnswerIds ?? [])
const revealReloadRequested = ref(false)
let revealReloadTimer: ReturnType<typeof setTimeout> | null = null

watch(() => props.quiz, (value) => {
  status.value = value?.status ?? 'waiting'
  currentIndex.value = value?.currentIndex ?? 0
  participantCount.value = value?.participantCount ?? 0
  answerClosesAt.value = value?.answerClosesAt ?? null
  correctAnswerIds.value = value?.correctAnswerIds ?? []
})

const total = computed(() => props.quiz?.total ?? (props.quiz?.questions?.length || 0))
const isWaiting = computed(() => status.value === 'waiting')
const finished = computed(() => status.value === 'finished')
const hasQuestions = computed(() => (props.quiz?.questions?.length ?? 0) > 0)
const answerDurationSeconds = computed(() => props.quiz?.answerDurationSeconds ?? 20)
const { remainingSeconds, expired } = useQuestionTimer(answerClosesAt, answerDurationSeconds)
const timerUrgent = computed(() => remainingSeconds.value > 0 && remainingSeconds.value <= 5)

const currentQuestion = computed<Question | null>(() => {
  const list = props.quiz?.questions || []
  if (!list.length) return null
  return list[currentIndex.value] ?? null
})

function displayAnswerClass(answerId: number): string {
  if (correctAnswerIds.value.includes(answerId)) {
    return 'rounded-2xl bg-emerald-500/80 px-6 py-5 text-white shadow-xl ring-2 ring-white'
  }
  if (correctAnswerIds.value.length) {
    return 'rounded-2xl bg-[#2b7cff] px-6 py-5 text-white shadow-xl ring-1 ring-white/15 opacity-70'
  }
  return 'rounded-2xl bg-[#2b7cff] px-6 py-5 text-white shadow-xl ring-1 ring-white/15'
}

function requestRevealReload() {
  if (finished.value || isWaiting.value || !currentQuestion.value) return
  if (correctAnswerIds.value.length > 0 || revealReloadRequested.value) return

  revealReloadRequested.value = true
  revealReloadTimer = setTimeout(() => {
    router.reload({ preserveScroll: true })
  }, 400)
}

watch(answerClosesAt, () => {
  revealReloadRequested.value = false
})

watch(expired, (isExpired) => {
  if (!isExpired) {
    revealReloadRequested.value = false
    return
  }
  requestRevealReload()
}, { immediate: true })

let pusher: { disconnect: () => void } | null = null

onMounted(async () => {
  try {
    const mod = await import('pusher-js')
    const Pusher = (mod as any).default || mod
    const key = (import.meta as any).env?.VITE_PUSHER_APP_KEY
    const cluster = (import.meta as any).env?.VITE_PUSHER_APP_CLUSTER || 'eu'
    if (!Pusher || !key) {
      console.warn('[Display] Pusher not configured. Set VITE_PUSHER_APP_KEY and VITE_PUSHER_APP_CLUSTER.')
      return
    }
    pusher = new Pusher(key, { cluster, forceTLS: true })
    const displayChannel = (pusher as any).subscribe(`display.${props.display.uuid}`)
    const reload = () => router.reload({ preserveScroll: true })
    displayChannel.bind('attached', reload)
    displayChannel.bind('detached', reload)

    if (props.quiz?.uuid) {
      const quizChannel = (pusher as any).subscribe(`quiz.${props.quiz.uuid}`)
      quizChannel.bind('advance', (data: { currentIndex?: number; status?: string; answerClosesAt?: string | null }) => {
        if (data?.status === 'finished') {
          router.reload({ preserveScroll: true })
          return
        }
        if (typeof data?.currentIndex === 'number') currentIndex.value = data.currentIndex
        if (data?.status) status.value = data.status as QuizPayload['status']
        answerClosesAt.value = data?.answerClosesAt ?? null
        correctAnswerIds.value = []
        revealReloadRequested.value = false
      })
      quizChannel.bind('reset', (data: { currentIndex?: number; status?: string; answerClosesAt?: string | null }) => {
        currentIndex.value = typeof data?.currentIndex === 'number' ? data.currentIndex : 0
        status.value = (data?.status as QuizPayload['status']) || 'waiting'
        answerClosesAt.value = data?.answerClosesAt ?? null
        correctAnswerIds.value = []
        revealReloadRequested.value = false
      })
      quizChannel.bind('participant-joined', (data: { count?: number }) => {
        if (typeof data?.count === 'number') participantCount.value = data.count
      })
    }
  } catch (e) {
    console.warn('[Display] Unable to load pusher-js:', e)
  }
})

onUnmounted(() => {
  pusher?.disconnect()
  if (revealReloadTimer) {
    clearTimeout(revealReloadTimer)
  }
})
</script>

<template>
  <div class="min-h-dvh bg-[#102846] text-white">
    <div v-if="!props.quiz" class="mx-auto flex min-h-dvh max-w-3xl flex-col items-center justify-center px-6 text-center">
      <p class="text-sm uppercase tracking-[0.2em] text-white/60">Affichage</p>
      <h1 class="mt-3 text-4xl font-semibold tracking-wide sm:text-5xl">{{ props.display.name }}</h1>
      <p class="mt-4 text-lg text-white/80">Aucun jeu rattaché.</p>
      <p class="mt-2 text-sm text-white/60">Rattachez un quiz depuis l’espace hôte pour projeter dans la salle.</p>
    </div>

    <div v-else-if="!hasQuestions" class="mx-auto flex min-h-dvh max-w-3xl flex-col items-center justify-center px-6 text-center">
      <p class="text-sm uppercase tracking-[0.2em] text-white/60">{{ props.display.name }}</p>
      <h1 class="mt-3 text-4xl font-semibold tracking-wide sm:text-5xl">{{ props.quiz.title }}</h1>
      <p class="mt-4 text-lg text-white/80">Aucune question disponible</p>
      <p class="mt-2 text-sm text-white/60">Ce quiz ne contient pas de questions pour le moment.</p>
    </div>

    <div v-else class="mx-auto max-w-6xl px-6 py-10">
      <div v-if="isWaiting" class="mx-auto max-w-3xl text-center">
        <p class="text-sm uppercase tracking-[0.2em] text-white/60">Prochain quiz</p>
        <h1 class="mt-3 text-4xl font-semibold tracking-wide sm:text-5xl">{{ props.quiz.title }}</h1>
        <p class="mt-4 text-lg text-white/80">Scannez le QR code ou ouvrez le lien pour rejoindre.</p>

        <div class="mt-8 flex flex-col items-center gap-6">
          <QrCodeSvg :svg="props.quiz.qrSvg" alt="QR code de participation" />
          <div class="w-full max-w-xl">
            <input :value="props.quiz.joinUrl" readonly class="h-11 w-full rounded-xl bg-white/10 px-4 text-center text-sm text-white ring-1 ring-white/15" />
          </div>
          <div class="rounded-full bg-white/10 px-5 py-2 text-sm ring-1 ring-white/20">
            {{ participantCount }} participant{{ participantCount === 1 ? '' : 's' }} en attente
          </div>
        </div>
      </div>

      <template v-else>
        <div v-if="!finished" class="mb-6 flex items-center justify-between text-white/80">
          <div class="flex items-baseline gap-6">
            <div class="text-sm">Question {{ Math.min((currentIndex ?? 0) + 1, total) }} / {{ total }}</div>
            <div
              v-if="answerClosesAt"
              class="text-4xl font-semibold tabular-nums leading-none"
              :class="timerUrgent ? 'text-amber-200' : 'text-white'"
            >
              {{ remainingSeconds }}
            </div>
          </div>
          <div class="text-sm">{{ props.quiz.title }}</div>
        </div>

        <div v-if="finished" class="space-y-6">
          <div class="rounded-2xl bg-[#1f57c7]/95 p-8 text-center shadow-2xl ring-1 ring-white/10">
            <h2 class="text-3xl font-semibold tracking-wide sm:text-4xl">Fin du quiz</h2>
            <p class="mt-4 text-lg">Merci à toutes et à tous pour votre participation.</p>
          </div>
          <div v-if="props.quiz.recap" class="space-y-4">
            <div
              v-for="(item, index) in props.quiz.recap.questions"
              :key="item.id"
              class="rounded-2xl bg-[#1f57c7]/80 p-6 shadow-xl ring-1 ring-white/10"
            >
              <div class="text-sm text-white/60">Question {{ index + 1 }}</div>
              <h3 class="mt-1 text-xl font-semibold leading-snug sm:text-2xl">{{ item.text }}</h3>
              <p v-if="item.correctTexts.length" class="mt-4 text-emerald-200">
                Bonne réponse : <span class="font-medium">{{ item.correctTexts.join(', ') }}</span>
              </p>
            </div>
          </div>
        </div>

        <template v-else>
          <div class="relative rounded-2xl bg-[#1f57c7]/95 shadow-2xl ring-1 ring-white/10">
            <div class="px-8 py-8 sm:py-10">
              <h2 class="text-center text-4xl font-semibold leading-tight tracking-wide sm:text-5xl">{{ currentQuestion?.text }}</h2>
            </div>
          </div>
          <div v-if="currentQuestion?.answers && currentQuestion.answers.length" class="mt-8 grid gap-4 md:grid-cols-2">
            <div v-for="(answer, idx) in currentQuestion.answers" :key="answer.id" :class="displayAnswerClass(answer.id)">
              <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15 ring-2 ring-white/40">
                  <span class="text-xl font-bold">{{ letters[idx] ?? String.fromCharCode(65 + idx) }}</span>
                </div>
                <div class="text-lg font-medium leading-snug tracking-wide sm:text-xl">
                  {{ answer.text }}
                </div>
              </div>
            </div>
          </div>

          <div class="mt-6 flex items-center">
            <div class="h-5 w-5 rounded-full bg-[#ff4b3e]"></div>
            <div class="ml-4 h-5 flex-1 rounded-full bg-white/30">
              <div class="h-5 rounded-full bg-white/70" :style="{ width: Math.max(0, Math.min(100, Math.round((((currentIndex ?? 0) + 1) / Math.max(1, total)) * 100))) + '%' }"></div>
            </div>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>
