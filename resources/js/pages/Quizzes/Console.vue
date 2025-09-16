<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Layout from './Layout.vue'

// Apply shared layout for the Quizzes section
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
defineOptions({ layout: Layout })

interface Quiz {
  id: number
  uuid: string
  title: string
  play_url: string
  advance_url: string
  reset_url: string
}

const props = defineProps<{ quiz: Quiz }>()

const sending = ref<{ advance?: boolean; reset?: boolean }>({})
const status = ref<string>('')
const progress = ref<{ index: number; total: number; finished: boolean } | null>(null)

function advance() {
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
      status.value = 'Pusher non configuré (VITE_PUSHER_APP_KEY manquant) — les boutons fonctionneront quand même.'
      return
    }
    const pusher = new Pusher(key, { cluster, forceTLS: true })
    const channelName = `quiz.${props.quiz.uuid}`
    const channel = pusher.subscribe(channelName)
    // Listen to action signals (no payload required)
    channel.bind('advance', () => {
      status.value = 'Signal: question suivante envoyée'
    })
    channel.bind('reset', () => {
      status.value = 'Signal: réinitialisation envoyée'
    })
    status.value = 'Connecté à Pusher'
  } catch {
    status.value = 'Impossible de charger pusher-js'
  }
})
</script>

<template>
  <div class="mx-auto max-w-5xl px-6 pb-20 pt-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Console du quiz</h1>
      <a href="/quizzes" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm ring-1 ring-white/15 hover:bg-white/10">Retour</a>
    </div>

    <div class="rounded-xl border border-white/10 bg-white/5 p-5">
      <div class="mb-4">
        <div class="text-sm text-white/70">Quiz</div>
        <div class="mt-1 text-lg font-semibold">{{ props.quiz.title }}</div>
        <div class="text-xs text-white/60">UUID: {{ props.quiz.uuid }}</div>
      </div>

      <div class="mb-4 grid gap-2 sm:grid-cols-[1fr_auto_auto_auto] items-center">
        <input :value="props.quiz.play_url" readonly class="h-10 w-full rounded bg-white/10 px-3 text-sm text-white ring-1 ring-white/15" />
        <a :href="props.quiz.play_url" target="_blank" class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15">Ouvrir l'affichage</a>
        <button type="button" class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15" @click="navigator.clipboard.writeText(props.quiz.play_url)">Copier</button>
        <span class="text-xs text-white/60 hidden sm:block">Lien public à envoyer</span>
      </div>

      <div v-if="status" class="mb-4 text-xs" :class="status.includes('Connecté') ? 'text-emerald-300' : 'text-white/60'">{{ status }}</div>
      <div v-if="progress" class="mb-4 text-sm text-white/80">Progression: question {{ progress.index + 1 }} / {{ progress.total }} <span v-if="progress.finished" class="ml-2 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs text-emerald-200 ring-1 ring-emerald-500/30">Terminé</span></div>

      <div class="flex flex-wrap items-center gap-3">
        <button type="button" @click="advance" :disabled="sending.advance" class="inline-flex items-center justify-center rounded-lg bg-[#2b7cff] px-5 py-2.5 font-semibold ring-1 ring-white/20 transition hover:brightness-110 disabled:opacity-60">
          {{ sending.advance ? '…' : 'Question suivante' }}
        </button>
        <button type="button" @click="resetQuiz" :disabled="sending.reset" class="inline-flex items-center justify-center rounded-lg bg-white/10 px-5 py-2.5 ring-1 ring-white/20 transition hover:bg-white/15 disabled:opacity-60">
          {{ sending.reset ? '…' : 'Réinitialiser' }}
        </button>
        <span class="text-xs text-white/50">Ces boutons déclenchent un événement temps réel vers l'affichage.</span>
      </div>
    </div>

    <!-- Zone libre pour boutons "fictifs" supplémentaires -->
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <button type="button" class="rounded-xl bg-white/5 p-4 text-left ring-1 ring-white/10 hover:bg-white/10">
        <div class="text-sm text-white/70">Fictif</div>
        <div class="mt-1 font-semibold">Mode pause</div>
        <div class="mt-1 text-xs text-white/50">(à définir plus tard)</div>
      </button>
      <button type="button" class="rounded-xl bg-white/5 p-4 text-left ring-1 ring-white/10 hover:bg-white/10">
        <div class="text-sm text-white/70">Fictif</div>
        <div class="mt-1 font-semibold">Afficher la bonne réponse</div>
        <div class="mt-1 text-xs text-white/50">(à définir plus tard)</div>
      </button>
      <button type="button" class="rounded-xl bg-white/5 p-4 text-left ring-1 ring-white/10 hover:bg-white/10">
        <div class="text-sm text-white/70">Fictif</div>
        <div class="mt-1 font-semibold">Compter à rebours</div>
        <div class="mt-1 text-xs text-white/50">(à définir plus tard)</div>
      </button>
    </div>

    <div class="mt-8 text-sm text-white/70">
      Conseils: ouvrez l'affichage public du quiz dans un autre onglet/écran, puis utilisez cette console pour avancer de question en question en temps réel.
    </div>
  </div>
</template>
