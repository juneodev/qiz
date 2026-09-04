<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Layout from './Layout.vue'
import QrCodeSvg from '@/components/QrCodeSvg.vue'

// Apply shared layout for the Quizzes section
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
defineOptions({ layout: Layout })

interface Participant {
  id: number
  nickname: string
  created_at: string | null
}

interface AttachedDisplay {
  id: number
  name: string
  url: string
}

interface Quiz {
  id: number
  uuid: string
  title: string
  join_url: string
  qr_svg: string
  advance_url: string
  reset_url: string
  participants_url: string
  status: 'waiting' | 'live' | 'finished'
  current_question_index: number
  total_questions: number
}

const props = defineProps<{
  quiz: Quiz
  participants: Participant[]
  displays: AttachedDisplay[]
}>()

const sending = ref<{ advance?: boolean; reset?: boolean }>({})
const pusherStatus = ref<string>('')
const liveParticipants = ref<Participant[]>([...props.participants])
const status = ref(props.quiz.status)
const currentIndex = ref(props.quiz.current_question_index)

watch(() => props.participants, (value) => {
  liveParticipants.value = [...value]
})
watch(() => [props.quiz.status, props.quiz.current_question_index], () => {
  status.value = props.quiz.status
  currentIndex.value = props.quiz.current_question_index
})

const advanceLabel = computed(() => {
  if (status.value === 'waiting') return 'Démarrer'
  if (status.value === 'live' && currentIndex.value >= props.quiz.total_questions - 1) return 'Terminer'
  if (status.value === 'finished') return 'Terminé'
  return 'Question suivante'
})

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

onMounted(async () => {
  try {
    const mod = await import('pusher-js')
    const Pusher = (mod as any).default || mod
    const key = (import.meta as any).env?.VITE_PUSHER_APP_KEY
    const cluster = (import.meta as any).env?.VITE_PUSHER_APP_CLUSTER || 'eu'
    if (!Pusher || !key) {
      pusherStatus.value = 'Pusher non configuré (VITE_PUSHER_APP_KEY manquant) — les boutons fonctionneront quand même.'
      return
    }
    const pusher = new Pusher(key, { cluster, forceTLS: true })
    const channel = pusher.subscribe(`quiz.${props.quiz.uuid}`)
    channel.bind('advance', (data: { status?: Quiz['status']; currentIndex?: number }) => {
      if (data?.status) status.value = data.status
      if (typeof data?.currentIndex === 'number') currentIndex.value = data.currentIndex
      pusherStatus.value = 'Signal: question suivante envoyée'
    })
    channel.bind('reset', (data: { status?: Quiz['status']; currentIndex?: number }) => {
      status.value = data?.status || 'waiting'
      currentIndex.value = typeof data?.currentIndex === 'number' ? data.currentIndex : 0
      liveParticipants.value = []
      pusherStatus.value = 'Signal: réinitialisation envoyée'
    })
    channel.bind('participant-joined', (data: { nickname?: string; count?: number }) => {
      if (data?.nickname && !liveParticipants.value.some((p) => p.nickname === data.nickname)) {
        liveParticipants.value = [
          ...liveParticipants.value,
          { id: Date.now(), nickname: data.nickname, created_at: new Date().toISOString() },
        ]
      }
      pusherStatus.value = `${data?.nickname ?? 'Un joueur'} a rejoint (${data?.count ?? liveParticipants.value.length})`
    })
    pusherStatus.value = 'Connecté à Pusher'
  } catch {
    pusherStatus.value = 'Impossible de charger pusher-js'
  }
})
</script>

<template>
  <div class="mx-auto max-w-5xl px-6 pb-20 pt-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Console du quiz</h1>
      <div class="flex items-center gap-2">
        <a :href="props.quiz.participants_url" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm ring-1 ring-white/15 hover:bg-white/10">Participants</a>
        <a href="/quizzes" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm ring-1 ring-white/15 hover:bg-white/10">Retour</a>
      </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-white/5 p-5">
      <div class="mb-4">
        <div class="text-sm text-white/70">Quiz</div>
        <div class="mt-1 text-lg font-semibold">{{ props.quiz.title }}</div>
        <div class="text-xs text-white/60">UUID: {{ props.quiz.uuid }}</div>
      </div>

      <div class="mb-6 grid gap-6 lg:grid-cols-[auto_1fr] lg:items-start">
        <QrCodeSvg :svg="props.quiz.qr_svg" alt="QR code de participation" />
        <div class="space-y-3">
          <div>
            <div class="mb-1 text-xs text-white/60">Lien de participation</div>
            <div class="flex flex-wrap items-center gap-2">
              <input :value="props.quiz.join_url" readonly class="h-10 min-w-0 flex-1 rounded bg-white/10 px-3 text-sm text-white ring-1 ring-white/15" />
              <button type="button" class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15" @click="navigator.clipboard.writeText(props.quiz.join_url)">Copier</button>
            </div>
          </div>
          <div>
            <div class="mb-1 text-xs text-white/60">Écran de projection</div>
            <div v-if="props.displays.length" class="space-y-2">
              <div v-for="display in props.displays" :key="display.id" class="flex flex-wrap items-center gap-2">
                <input :value="display.url" readonly class="h-10 min-w-0 flex-1 rounded bg-white/10 px-3 text-sm text-white ring-1 ring-white/15" />
                <span class="text-xs text-white/60">{{ display.name }}</span>
                <button type="button" class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15" @click="navigator.clipboard.writeText(display.url)">Copier</button>
                <a :href="display.url" target="_blank" class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15">Ouvrir</a>
              </div>
            </div>
            <p v-else class="text-sm text-white/70">
              Aucun affichage rattaché.
              <a href="/displays" class="underline decoration-white/30 underline-offset-4 hover:text-white">Rattacher un affichage</a>
            </p>
          </div>
        </div>
      </div>

      <div v-if="pusherStatus" class="mb-4 text-xs" :class="pusherStatus.includes('Connecté') || pusherStatus.includes('rejoint') ? 'text-emerald-300' : 'text-white/60'">{{ pusherStatus }}</div>
      <div class="mb-4 text-sm text-white/80">
        <span v-if="status === 'waiting'">En attente — {{ liveParticipants.length }} participant{{ liveParticipants.length === 1 ? '' : 's' }}</span>
        <span v-else-if="status === 'live'">En cours : question {{ currentIndex + 1 }} / {{ props.quiz.total_questions }}</span>
        <span v-else>Terminé</span>
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
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Participants</h2>
        <a :href="props.quiz.participants_url" class="text-sm text-white/70 underline decoration-white/30 underline-offset-4 hover:text-white">Voir la liste</a>
      </div>
      <p v-if="liveParticipants.length === 0" class="text-sm text-white/60">En attente des joueurs…</p>
      <ul v-else class="flex flex-wrap gap-2">
        <li v-for="participant in liveParticipants" :key="participant.id" class="rounded-full bg-white/10 px-3 py-1 text-sm ring-1 ring-white/15">
          {{ participant.nickname }}
        </li>
      </ul>
    </div>

    <div class="mt-8 text-sm text-white/70">
      Conseils: ouvrez l'affichage public dans un autre onglet/écran, partagez le QR code, puis démarrez quand tout le monde a rejoint.
    </div>
  </div>
</template>
