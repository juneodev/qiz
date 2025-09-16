<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

interface Answer { id: number; text: string; order?: number }
interface Question { id: number; text: string; order?: number; answers: Answer[] }

type PageProps = {
  questions: Question[]
  total: number
  uuid: string
}

const page = usePage<{ props: PageProps }>()
const props = computed(() => page.props as unknown as PageProps)

const letters = ['A','B','C','D','E','F']

const indexKey = computed(() => `quiz:${props.value.uuid}:index`)
const currentIndex = ref<number>(0)

const total = computed(() => props.value.total ?? (props.value.questions?.length || 0))
const finished = computed(() => currentIndex.value >= total.value && total.value > 0)

function clampIndex(v: number) {
  if (v < 0) return 0
  if (v >= total.value) return total.value - 1
  return v
}

function persistIndex() {
  try { localStorage.setItem(indexKey.value, String(currentIndex.value)) } catch {}
}

function restoreIndex() {
  try {
    const raw = localStorage.getItem(indexKey.value)
    if (raw !== null) {
      currentIndex.value = clampIndex(parseInt(raw, 10) || 0)
    }
  } catch {}
}

function advance() {
  if (currentIndex.value < total.value) {
    currentIndex.value = currentIndex.value + 1
    persistIndex()
  }
}

function resetQuiz() {
  currentIndex.value = 0
  persistIndex()
}

const currentQuestion = computed<Question | null>(() => {
  const list = props.value.questions || []
  if (!list.length) return null
  if (currentIndex.value >= total.value) return null
  return list[currentIndex.value] ?? null
})

watch(() => props.value.uuid, () => {
  // When quiz changes, reset index and restore if available
  currentIndex.value = 0
  restoreIndex()
})

onMounted(async () => {
  restoreIndex()
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
    const channelName = `quiz.${props.value.uuid}`
    const channel = pusher.subscribe(channelName)
    channel.bind('advance', () => {
      advance()
    })
    channel.bind('reset', () => {
      resetQuiz()
    })
  } catch (e) {
    console.warn('[Quiz] Unable to load pusher-js:', e)
  }
})
</script>

<template>
  <div class="min-h-dvh bg-[#102846] text-white">
    <div class="mx-auto max-w-6xl px-6 py-10" v-if="props.questions && props.questions.length">
      <!-- Header / Progress -->
      <div class="mb-6 flex items-center justify-between text-sm text-white/80">
        <div>Question {{ Math.min((currentIndex ?? 0) + 1, total) }} / {{ total }}</div>
        <div>
          <button type="button" @click="resetQuiz" class="underline decoration-white/30 underline-offset-4 hover:text-white">Réinitialiser</button>
        </div>
      </div>

      <!-- Finished state -->
      <div v-if="finished" class="rounded-2xl bg-[#1f57c7]/95 p-8 text-center shadow-2xl ring-1 ring-white/10">
        <h2 class="text-3xl sm:text-4xl font-semibold tracking-wide">Fin du quiz 🎉</h2>
        <p class="mt-4 text-lg">Merci. Vous pouvez recommencer si besoin.</p>
        <div class="mt-6 flex items-center justify-center gap-3">
          <button type="button" @click="resetQuiz" class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">Recommencer</button>
          <Link href="/" class="text-sm text-white/70 hover:text-white">Accueil</Link>
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
            <h2 class="text-center text-4xl sm:text-5xl font-semibold tracking-wide leading-tight">{{ currentQuestion?.text }}</h2>
          </div>
        </div>
        <!-- Optional answers list (display only) -->
        <div v-if="currentQuestion?.answers && currentQuestion.answers.length" class="mt-8 grid gap-4 md:grid-cols-2">
          <div v-for="(answer, idx) in currentQuestion.answers" :key="answer.id" class="rounded-2xl bg-[#2b7cff] px-6 py-5 text-white shadow-xl ring-1 ring-white/15">
            <div class="flex items-center gap-4">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15 ring-2 ring-white/40">
                <span class="text-xl font-bold">{{ letters[idx] ?? String.fromCharCode(65 + idx) }}</span>
              </div>
              <div class="text-lg sm:text-xl font-medium tracking-wide leading-snug">
                {{ answer.text }}
              </div>
            </div>
          </div>
        </div>

        <!-- Controls -->
        <div class="mt-8 flex items-center gap-4">
          <button type="button" @click="advance" class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">
            {{ (currentIndex + 1 >= total) ? 'Terminer' : 'Question suivante' }}
          </button>
          <button type="button" @click="resetQuiz" class="text-sm text-white/70 hover:text-white">Réinitialiser</button>
        </div>

        <!-- Progress bar -->
        <div class="mt-6 flex items-center">
          <div class="h-5 w-5 rounded-full bg-[#ff4b3e]"></div>
          <div class="ml-4 h-5 flex-1 rounded-full bg-white/30">
            <div class="h-5 rounded-full bg-white/70" :style="{ width: Math.max(0, Math.min(100, Math.round(((currentIndex ?? 0) / Math.max(1, total)) * 100))) + '%' }"></div>
          </div>
        </div>
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
