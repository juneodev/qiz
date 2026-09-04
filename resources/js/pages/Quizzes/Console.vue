<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Layout from './Layout.vue'

// Apply shared layout for the Quizzes section
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
defineOptions({ layout: Layout })

interface Participant {
  id: number
  nickname: string
  created_at: string | null
  score: number
  score_total: number
}

interface DisplayOption {
  id: number
  name: string
  url: string
}

interface AttachedDisplay {
  id: number
  name: string
  url: string
}

interface Answer {
  id: number
  text: string
  order: number
  is_correct: boolean
}

interface Question {
  id: number
  text: string
  order: number
  answers: Answer[]
}

interface Quiz {
  id: number
  uuid: string
  title: string
  join_url: string
  advance_url: string
  reset_url: string
  participants_url: string
  attach_display_url: string
  status: 'waiting' | 'live' | 'finished'
  current_question_index: number
  total_questions: number
}

const props = defineProps<{
  quiz: Quiz
  questions: Question[]
  participants: Participant[]
  displays: AttachedDisplay[]
  availableDisplays: DisplayOption[]
}>()

const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']

function avatarUrl(id: number) {
  return `https://api.dicebear.com/10.x/glyphs/svg?seed=${encodeURIComponent(String(id))}`
}

function refreshParticipants() {
  router.reload({
    only: ['participants'],
    preserveScroll: true,
    preserveState: true,
  })
}

const sending = ref<{ advance?: boolean; reset?: boolean; display?: boolean }>({})
const realtimeState = ref<'connecting' | 'live' | 'offline' | 'unavailable'>('connecting')
const realtimeDetail = ref('')
const liveParticipants = ref<Participant[]>([...props.participants])
const status = ref(props.quiz.status)
const currentIndex = ref(props.quiz.current_question_index)
const selectedDisplayId = ref<number | null>(props.displays[0]?.id ?? null)

watch(() => props.participants, (value) => {
  liveParticipants.value = [...value]
})
watch(() => [props.quiz.status, props.quiz.current_question_index], () => {
  status.value = props.quiz.status
  currentIndex.value = props.quiz.current_question_index
})
watch(() => props.displays, (value) => {
  selectedDisplayId.value = value[0]?.id ?? null
})

const rankedParticipants = computed(() =>
  [...liveParticipants.value].sort((a, b) => {
    if (b.score !== a.score) return b.score - a.score
    return a.nickname.localeCompare(b.nickname, 'fr')
  }),
)

const selectedDisplay = computed(() => {
  if (selectedDisplayId.value == null) return null
  return props.availableDisplays.find((display) => display.id === Number(selectedDisplayId.value)) ?? null
})

const realtimeLabel = computed(() => {
  if (realtimeState.value === 'live') return 'Temps réel actif'
  if (realtimeState.value === 'unavailable') return 'Temps réel indisponible'
  if (realtimeState.value === 'offline') return 'Temps réel hors ligne'
  return 'Connexion au temps réel…'
})

const advanceLabel = computed(() => {
  if (status.value === 'waiting') return 'Démarrer'
  if (status.value === 'live' && currentIndex.value >= props.quiz.total_questions - 1) return 'Terminer'
  if (status.value === 'finished') return 'Terminé'
  return 'Question suivante'
})

const phaseLabel = computed(() => {
  if (status.value === 'waiting') return 'En attente'
  if (status.value === 'live') return 'En cours'
  return 'Terminé'
})

const progressLabel = computed(() => {
  if (props.questions.length === 0) return 'Aucune question'
  if (status.value === 'waiting') {
    return `${liveParticipants.value.length} participant${liveParticipants.value.length === 1 ? '' : 's'} · prêt à démarrer`
  }
  if (status.value === 'finished') {
    return `${props.questions.length} question${props.questions.length === 1 ? '' : 's'} jouée${props.questions.length === 1 ? '' : 's'}`
  }
  return `Question ${currentIndex.value + 1} / ${props.questions.length}`
})

const currentQuestion = computed(() => {
  if (status.value !== 'live' || props.questions.length === 0) return null
  return props.questions[currentIndex.value] ?? null
})

const previewQuestion = computed(() => {
  if (status.value !== 'waiting' || props.questions.length === 0) return null
  return props.questions[0] ?? null
})

const displayedQuestion = computed(() => currentQuestion.value ?? previewQuestion.value)

function answerClass(answer: Answer) {
  if (answer.is_correct) {
    return 'rounded-xl bg-emerald-500/20 px-4 py-3 ring-2 ring-emerald-400/70'
  }
  return 'rounded-xl bg-white/5 px-4 py-3 ring-1 ring-white/10'
}

function advance() {
  if (status.value === 'finished') return
  sending.value.advance = true
  router.post(props.quiz.advance_url, {}, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => { sending.value.advance = false },
  })
}

function resetQuiz() {
  sending.value.reset = true
  router.post(props.quiz.reset_url, {}, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => { sending.value.reset = false },
  })
}

function attachDisplay() {
  const raw = selectedDisplayId.value as number | string | null
  const displayId = raw === null || raw === '' || Number.isNaN(Number(raw))
    ? null
    : Number(raw)
  selectedDisplayId.value = displayId
  sending.value.display = true
  router.put(props.quiz.attach_display_url, {
    display_id: displayId,
  }, {
    preserveScroll: true,
    onFinish: () => { sending.value.display = false },
  })
}

onMounted(async () => {
  try {
    const mod = await import('pusher-js')
    const Pusher = (mod as any).default || mod
    const key = (import.meta as any).env?.VITE_PUSHER_APP_KEY
    const cluster = (import.meta as any).env?.VITE_PUSHER_APP_CLUSTER || 'eu'
    if (!Pusher || !key) {
      realtimeState.value = 'unavailable'
      realtimeDetail.value = 'Les commandes fonctionnent quand même.'
      return
    }
    const pusher = new Pusher(key, { cluster, forceTLS: true })
    const channel = pusher.subscribe(`quiz.${props.quiz.uuid}`)
    channel.bind('advance', (data: { status?: Quiz['status']; currentIndex?: number }) => {
      if (data?.status) status.value = data.status
      if (typeof data?.currentIndex === 'number') currentIndex.value = data.currentIndex
      realtimeDetail.value = 'Question suivante envoyée'
      refreshParticipants()
    })
    channel.bind('reset', (data: { status?: Quiz['status']; currentIndex?: number }) => {
      status.value = data?.status || 'waiting'
      currentIndex.value = typeof data?.currentIndex === 'number' ? data.currentIndex : 0
      liveParticipants.value = []
      realtimeDetail.value = 'Quiz réinitialisé'
    })
    channel.bind('participant-joined', (data: { participantId?: number; nickname?: string; count?: number }) => {
      if (data?.nickname && !liveParticipants.value.some((p) => p.nickname === data.nickname)) {
        liveParticipants.value = [
          ...liveParticipants.value,
          {
            id: data.participantId ?? Date.now(),
            nickname: data.nickname,
            created_at: new Date().toISOString(),
            score: 0,
            score_total: props.questions.length,
          },
        ]
      }
      realtimeDetail.value = `${data?.nickname ?? 'Un joueur'} a rejoint`
    })
    realtimeState.value = 'live'
  } catch {
    realtimeState.value = 'offline'
    realtimeDetail.value = 'Impossible d’établir la connexion.'
  }
})
</script>

<template>
  <div class="mx-auto max-w-5xl px-6 pb-20 pt-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Console du quiz</h1>
      <div class="flex items-center gap-2">
        <a
          :href="props.quiz.join_url"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center justify-center rounded-lg bg-[#e11d48] px-4 py-2 text-sm font-semibold ring-1 ring-[#9f1239] transition hover:brightness-110"
        >
          Participer
        </a>
        <a :href="props.quiz.participants_url" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm ring-1 ring-white/15 hover:bg-white/10">Participants</a>
        <a href="/quizzes" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm ring-1 ring-white/15 hover:bg-white/10">Retour</a>
      </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-white/5 p-5">
      <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="text-sm text-white/70">Quiz</div>
          <div class="mt-1 text-lg font-semibold">{{ props.quiz.title }}</div>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
          <span class="relative inline-flex h-2.5 w-2.5 shrink-0">
            <span
              v-if="realtimeState === 'live'"
              class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"
            />
            <span
              class="relative inline-flex h-2.5 w-2.5 rounded-full"
              :class="{
                'bg-emerald-400': realtimeState === 'live',
                'bg-amber-400': realtimeState === 'connecting',
                'bg-white/40': realtimeState === 'unavailable' || realtimeState === 'offline',
              }"
            />
          </span>
          <span
            :class="{
              'text-emerald-300': realtimeState === 'live',
              'text-amber-200': realtimeState === 'connecting',
              'text-white/60': realtimeState === 'unavailable' || realtimeState === 'offline',
            }"
          >
            {{ realtimeLabel }}
          </span>
          <span v-if="realtimeDetail" class="text-white/50">· {{ realtimeDetail }}</span>
        </div>
      </div>

      <div class="mb-6">
        <div class="mb-1 text-xs text-white/60">Écran de projection</div>
        <div v-if="props.availableDisplays.length" class="flex flex-wrap items-center gap-2">
          <select
            v-model="selectedDisplayId"
            class="h-10 min-w-0 flex-1 rounded bg-white/10 px-3 text-sm text-white ring-1 ring-white/15 focus:outline-none focus:ring-white/40"
            :disabled="sending.display"
            @change="attachDisplay"
          >
            <option :value="null">Aucun affichage</option>
            <option v-for="display in props.availableDisplays" :key="display.id" :value="display.id">
              {{ display.name }}
            </option>
          </select>
          <a
            v-if="selectedDisplay"
            :href="selectedDisplay.url"
            target="_blank"
            rel="noopener noreferrer"
            class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15"
          >
            Ouvrir
          </a>
        </div>
        <p v-else class="text-sm text-white/70">
          Aucun affichage disponible.
          <a href="/displays/create" class="underline decoration-white/30 underline-offset-4 hover:text-white">Créer un affichage</a>
        </p>
      </div>

      <div class="mb-5 flex flex-wrap items-center gap-3">
        <span
          class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ring-1"
          :class="{
            'bg-amber-400/15 text-amber-200 ring-amber-400/30': status === 'waiting',
            'bg-emerald-400/15 text-emerald-200 ring-emerald-400/30': status === 'live',
            'bg-white/10 text-white/70 ring-white/20': status === 'finished',
          }"
        >
          {{ phaseLabel }}
        </span>
        <span class="text-sm text-white/80">{{ progressLabel }}</span>
      </div>

      <div class="mb-5 rounded-2xl border border-white/10 bg-black/20 p-5 sm:p-6">
        <template v-if="status === 'finished'">
          <div class="text-xs font-medium uppercase tracking-wide text-white/50">Quiz terminé</div>
          <p class="mt-3 text-xl font-semibold text-white/90 sm:text-2xl">Bravo — la partie est finie.</p>
          <p class="mt-2 text-sm text-white/60">Réinitialisez pour relancer une nouvelle session.</p>
        </template>

        <template v-else-if="!displayedQuestion">
          <div class="text-xs font-medium uppercase tracking-wide text-white/50">Question</div>
          <p class="mt-3 text-lg text-white/70">Aucune question dans ce quiz.</p>
        </template>

        <template v-else>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-xs font-medium uppercase tracking-wide text-white/50">
              <span v-if="status === 'waiting'">Aperçu — première question</span>
              <span v-else>Question {{ currentIndex + 1 }}</span>
            </div>
            <div v-if="status === 'live'" class="text-xs text-white/50">
              Indicatif maître de jeu · bonne réponse en surbrillance
            </div>
          </div>

          <h2 class="mt-3 text-2xl font-semibold leading-snug tracking-wide sm:text-3xl">
            {{ displayedQuestion.text }}
          </h2>

          <div
            v-if="displayedQuestion.answers.length"
            class="mt-5 grid gap-3 sm:grid-cols-2"
            :class="{ 'opacity-70': status === 'waiting' }"
          >
            <div
              v-for="(answer, idx) in displayedQuestion.answers"
              :key="answer.id"
              :class="answerClass(answer)"
            >
              <div class="flex items-center gap-3">
                <div
                  class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold leading-none"
                  :class="answer.is_correct ? 'bg-emerald-400/25 text-emerald-100 ring-1 ring-emerald-300/50' : 'bg-white/10 text-white/80 ring-1 ring-white/15'"
                >
                  {{ letters[idx] ?? String.fromCharCode(65 + idx) }}
                </div>
                <div class="min-w-0">
                  <div class="text-sm font-medium leading-snug sm:text-base">{{ answer.text }}</div>
                  <div v-if="answer.is_correct" class="mt-1 text-xs font-semibold uppercase tracking-wide text-emerald-300">
                    Bonne réponse
                  </div>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="mt-4 text-sm text-white/60">Cette question n’a pas encore de réponses.</p>
        </template>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <button type="button" @click="advance" :disabled="sending.advance || status === 'finished'" class="inline-flex items-center justify-center rounded-lg bg-[#2b7cff] px-5 py-2.5 font-semibold ring-1 ring-white/20 transition hover:brightness-110 disabled:opacity-60">
          {{ sending.advance ? '…' : advanceLabel }}
        </button>
        <button type="button" @click="resetQuiz" :disabled="sending.reset" class="inline-flex items-center justify-center rounded-lg bg-white/10 px-5 py-2.5 ring-1 ring-white/20 transition hover:bg-white/15 disabled:opacity-60">
          {{ sending.reset ? '…' : 'Réinitialiser' }}
        </button>
        <span class="text-xs text-white/50">Pilote l'affichage et les téléphones des participants.</span>
      </div>
    </div>

    <div class="mt-6 rounded-xl border border-white/10 bg-white/5 p-5">
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold">Participants</h2>
          <p class="mt-0.5 text-sm text-white/60">
            {{ liveParticipants.length }} joueur{{ liveParticipants.length === 1 ? '' : 's' }}
          </p>
        </div>
        <a :href="props.quiz.participants_url" class="text-sm text-white/70 underline decoration-white/30 underline-offset-4 hover:text-white">Liste détaillée</a>
      </div>

      <div v-if="rankedParticipants.length === 0" class="rounded-xl bg-white/5 px-4 py-8 text-center text-sm text-white/60 ring-1 ring-white/10">
        En attente des joueurs…
      </div>

      <ul v-else class="divide-y divide-white/10 overflow-hidden rounded-xl ring-1 ring-white/10">
        <li
          v-for="(participant, index) in rankedParticipants"
          :key="participant.id"
          class="flex items-center gap-3 bg-black/10 px-4 py-3"
        >
          <div class="w-6 shrink-0 text-center text-xs font-semibold tabular-nums text-white/40">
            {{ index + 1 }}
          </div>
          <img
            :src="avatarUrl(participant.id)"
            :alt="participant.nickname"
            class="size-8 shrink-0 rounded-full bg-white/10 ring-2 ring-white/15"
          />
          <div class="min-w-0 flex-1">
            <div class="truncate font-medium text-white">{{ participant.nickname }}</div>
            <div class="text-xs text-white/50">Score actuel</div>
          </div>
          <div class="shrink-0 text-right">
            <div class="text-lg font-semibold tabular-nums leading-none text-white">
              {{ participant.score }}
              <span class="text-sm font-medium text-white/45">/ {{ participant.score_total }}</span>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <div class="mt-8 text-sm text-white/70">
      Conseils: choisissez un écran de projection, invitez les joueurs via Participer, puis démarrez quand tout le monde a rejoint.
    </div>
  </div>
</template>
