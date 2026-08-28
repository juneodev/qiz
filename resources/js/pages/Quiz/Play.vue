<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import QrCodeSvg from '@/components/QrCodeSvg.vue'

interface Answer { id: number; text: string; order?: number }
interface Question { id: number; text: string; order?: number; answers: Answer[] }

type PageProps = {
  questions: Question[]
  total: number
  uuid: string
  title: string
  status: 'waiting' | 'live' | 'finished'
  currentIndex: number
  joinUrl: string
  qrSvg: string
  participantCount: number
}

const page = usePage<{ props: PageProps }>()
const props = computed(() => page.props as unknown as PageProps)

const letters = ['A', 'B', 'C', 'D', 'E', 'F']

const status = ref(props.value.status)
const currentIndex = ref(props.value.currentIndex)
const participantCount = ref(props.value.participantCount)

watch(() => [props.value.status, props.value.currentIndex, props.value.participantCount], () => {
  status.value = props.value.status
  currentIndex.value = props.value.currentIndex
  participantCount.value = props.value.participantCount
})

const total = computed(() => props.value.total ?? (props.value.questions?.length || 0))
const isWaiting = computed(() => status.value === 'waiting')
const finished = computed(() => status.value === 'finished')

const currentQuestion = computed<Question | null>(() => {
  const list = props.value.questions || []
  if (!list.length) return null
  return list[currentIndex.value] ?? null
})

onMounted(async () => {
  try {
    const mod = await import('pusher-js')
    const Pusher = (mod as any).default || mod
    const key = (import.meta as any).env?.VITE_PUSHER_APP_KEY
    const cluster = (import.meta as any).env?.VITE_PUSHER_APP_CLUSTER || 'eu'
    if (!Pusher || !key) {
      console.warn('[Quiz] Pusher not configured. Set VITE_PUSHER_APP_KEY and VITE_PUSHER_APP_CLUSTER.')
      return
    }
    const pusher = new Pusher(key, { cluster, forceTLS: true })
    const channel = pusher.subscribe(`quiz.${props.value.uuid}`)
    channel.bind('advance', (data: { currentIndex?: number; status?: string }) => {
      if (typeof data?.currentIndex === 'number') currentIndex.value = data.currentIndex
      if (data?.status) status.value = data.status as PageProps['status']
    })
    channel.bind('reset', (data: { currentIndex?: number; status?: string }) => {
      currentIndex.value = typeof data?.currentIndex === 'number' ? data.currentIndex : 0
      status.value = (data?.status as PageProps['status']) || 'waiting'
    })
    channel.bind('participant-joined', (data: { count?: number }) => {
      if (typeof data?.count === 'number') participantCount.value = data.count
    })
  } catch (e) {
    console.warn('[Quiz] Unable to load pusher-js:', e)
  }
})
</script>

<template>
  <div class="min-h-dvh bg-[#102846] text-white">
    <div class="mx-auto max-w-6xl px-6 py-10" v-if="props.questions && props.questions.length">
      <div v-if="isWaiting" class="mx-auto max-w-3xl text-center">
        <p class="text-sm uppercase tracking-[0.2em] text-white/60">Prochain quiz</p>
        <h1 class="mt-3 text-4xl font-semibold tracking-wide sm:text-5xl">{{ props.title }}</h1>
        <p class="mt-4 text-lg text-white/80">Scannez le QR code ou ouvrez le lien pour rejoindre.</p>

        <div class="mt-8 flex flex-col items-center gap-6">
          <QrCodeSvg :svg="props.qrSvg" alt="QR code de participation" />
          <div class="w-full max-w-xl">
            <input :value="props.joinUrl" readonly class="h-11 w-full rounded-xl bg-white/10 px-4 text-center text-sm text-white ring-1 ring-white/15" />
          </div>
          <div class="rounded-full bg-white/10 px-5 py-2 text-sm ring-1 ring-white/20">
            {{ participantCount }} participant{{ participantCount === 1 ? '' : 's' }} en attente
          </div>
        </div>
      </div>

      <template v-else>
        <div class="mb-6 flex items-center justify-between text-sm text-white/80">
          <div>Question {{ Math.min((currentIndex ?? 0) + 1, total) }} / {{ total }}</div>
          <div>{{ props.title }}</div>
        </div>

        <div v-if="finished" class="rounded-2xl bg-[#1f57c7]/95 p-8 text-center shadow-2xl ring-1 ring-white/10">
          <h2 class="text-3xl font-semibold tracking-wide sm:text-4xl">Fin du quiz</h2>
          <p class="mt-4 text-lg">Merci à toutes et à tous pour votre participation.</p>
        </div>

        <template v-else>
          <div class="relative rounded-2xl bg-[#1f57c7]/95 shadow-2xl ring-1 ring-white/10">
            <div class="px-8 py-8 sm:py-10">
              <h2 class="text-center text-4xl font-semibold leading-tight tracking-wide sm:text-5xl">{{ currentQuestion?.text }}</h2>
            </div>
          </div>
          <div v-if="currentQuestion?.answers && currentQuestion.answers.length" class="mt-8 grid gap-4 md:grid-cols-2">
            <div v-for="(answer, idx) in currentQuestion.answers" :key="answer.id" class="rounded-2xl bg-[#2b7cff] px-6 py-5 text-white shadow-xl ring-1 ring-white/15">
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

      <div class="mt-10 text-center text-sm text-white/70">
        <Link class="underline decoration-white/30 underline-offset-4 hover:text-white" href="/">Retour à l'accueil</Link>
      </div>
    </div>

    <div v-else class="mx-auto max-w-3xl px-6 py-16 text-center text-white/80">
      <h2 class="text-2xl font-semibold">Aucune question disponible</h2>
      <p class="mt-2 text-sm">Ce quiz ne contient pas de questions pour le moment.</p>
    </div>
  </div>
</template>
