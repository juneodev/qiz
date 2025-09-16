<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import Layout from './Layout.vue'

// Apply shared layout for the Quizzes section
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
defineOptions({ layout: Layout })

interface AnswerItem {
  id?: number
  text: string
  is_correct?: boolean
  order?: number
}

interface QuestionItem {
  id?: number
  text: string
  order?: number
  answers: AnswerItem[]
}

interface Quiz {
  id: number
  uuid: string
  title: string
  description?: string | null
  published_at?: string | null
  created_at: string
  play_url: string
  console_url?: string
}

const props = defineProps<{ quiz: Quiz, questions: QuestionItem[] }>()

const form = useForm({
  title: props.quiz.title ?? '',
  description: props.quiz.description ?? '',
  publish: Boolean(props.quiz.published_at),
})

const isPublished = computed(() => form.publish)

function submit() {
  form.put(`/quizzes/${props.quiz.id}`, {
    preserveScroll: true,
  })
}

// Questions state for structure editing
const questions = ref<QuestionItem[]>(JSON.parse(JSON.stringify(props.questions ?? [])))

function addQuestion() {
  questions.value.push({ text: '', order: (questions.value.length + 1), answers: [] })
}
function removeQuestion(index: number) {
  questions.value.splice(index, 1)
  renumberQuestions()
}
function moveQuestion(index: number, dir: number) {
  const newIndex = index + dir
  if (newIndex < 0 || newIndex >= questions.value.length) return
  const [item] = questions.value.splice(index, 1)
  questions.value.splice(newIndex, 0, item)
  renumberQuestions()
}
function renumberQuestions() {
  questions.value.forEach((q, i) => { q.order = i + 1 })
}

function addAnswer(qIndex: number) {
  const q = questions.value[qIndex]
  q.answers = q.answers || []
  q.answers.push({ text: '', is_correct: false, order: (q.answers.length + 1) })
}
function removeAnswer(qIndex: number, aIndex: number) {
  const q = questions.value[qIndex]
  q.answers.splice(aIndex, 1)
  renumberAnswers(q)
}
function moveAnswer(qIndex: number, aIndex: number, dir: number) {
  const q = questions.value[qIndex]
  const newIndex = aIndex + dir
  if (newIndex < 0 || newIndex >= q.answers.length) return
  const [item] = q.answers.splice(aIndex, 1)
  q.answers.splice(newIndex, 0, item)
  renumberAnswers(q)
}
function renumberAnswers(q: QuestionItem) {
  q.answers.forEach((a, i) => { a.order = i + 1 })
}

const structureForm = useForm<{ questions: QuestionItem[] }>({
  questions: questions.value,
})

function saveStructure() {
  // ensure latest state copied (avoid ref sharing issues)
  structureForm.questions = JSON.parse(JSON.stringify(questions.value))
  structureForm.put(`/quizzes/${props.quiz.id}/structure`, {
    preserveScroll: true,
  })
}

// If props change (unlikely here), sync form
watch(
  () => props.quiz,
  (q) => {
    form.title = q.title
    form.description = q.description ?? ''
    form.publish = Boolean(q.published_at)
  }
)
</script>

<template>
  <div class="mx-auto max-w-5xl px-6 pb-20 pt-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Modifier le quiz</h1>
      <a
        href="/quizzes"
        class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm ring-1 ring-white/15 hover:bg-white/10"
      >Retour</a>
    </div>

    <!-- Share link -->
    <div class="mb-6 rounded-xl border border-white/10 bg-white/5 p-4">
      <div class="mb-2 text-sm text-white/80">Lien à partager</div>
      <div class="flex items-center gap-2">
        <input :value="props.quiz.play_url" readonly class="w-full rounded bg-white/10 px-3 py-2 text-sm text-white ring-1 ring-white/15" />
        <a :href="props.quiz.play_url" target="_blank" class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15">Ouvrir</a>
        <button type="button" class="rounded bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15" @click="navigator.clipboard.writeText(props.quiz.play_url)">Copier</button>
        <a v-if="props.quiz.console_url" :href="props.quiz.console_url" class="rounded bg-emerald-600/20 px-3 py-2 text-sm text-emerald-100 ring-1 ring-emerald-500/30 hover:bg-emerald-600/30">Ouvrir la console</a>
      </div>
      <p class="mt-2 text-xs text-white/60">Envoyez ce lien aux joueurs pour qu'ils puissent accéder au quiz. Utilisez la console pour piloter l'affichage en direct.</p>
    </div>

    <!-- Meta form -->
    <form @submit.prevent="submit" class="space-y-6">
      <div class="space-y-2">
        <label for="title" class="text-sm text-white/80">Titre</label>
        <input
          id="title"
          v-model="form.title"
          type="text"
          required
          class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 outline-none ring-1 ring-inset ring-white/10 focus:ring-white/30"
          placeholder="Ex: Culture Générale"
        />
        <div v-if="form.errors.title" class="text-sm text-rose-300">{{ form.errors.title }}</div>
      </div>

      <div class="space-y-2">
        <label for="description" class="text-sm text-white/80">Description</label>
        <textarea
          id="description"
          v-model="form.description"
          rows="4"
          class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 outline-none ring-1 ring-inset ring-white/10 focus:ring-white/30"
          placeholder="Décrivez brièvement votre quiz"
        />
        <div v-if="form.errors.description" class="text-sm text-rose-300">{{ form.errors.description }}</div>
      </div>

      <div class="flex items-center gap-3">
        <input id="publish" type="checkbox" v-model="form.publish" class="h-4 w-4 rounded border-white/20 bg-white/10" />
        <label for="publish" class="select-none text-sm text-white/80">Publier maintenant</label>
        <span v-if="isPublished" class="ml-2 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs text-emerald-200 ring-1 ring-emerald-500/30">Publié</span>
        <span v-else class="ml-2 rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs text-amber-200 ring-1 ring-amber-500/30">Brouillon</span>
      </div>

      <div class="flex items-center gap-3">
        <button
          type="submit"
          :disabled="form.processing"
          class="inline-flex items-center justify-center rounded-lg bg-[#2b7cff] px-5 py-2.5 font-semibold ring-1 ring-white/20 transition hover:brightness-110 disabled:opacity-60"
        >
          Enregistrer
        </button>
      </div>
    </form>

    <!-- Structure editor -->
    <div class="mt-10">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xl font-semibold">Questions & Réponses</h2>
        <button @click="addQuestion" class="rounded-lg bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 hover:bg-white/15">+ Ajouter une question</button>
      </div>

      <div v-if="questions.length === 0" class="rounded-lg border border-white/10 bg-white/5 p-6 text-sm text-white/80">
        Aucune question. Cliquez sur « Ajouter une question » pour commencer.
      </div>

      <div class="space-y-5">
        <div v-for="(q, qi) in questions" :key="q.id ?? `new-${qi}`" class="rounded-xl border border-white/10 bg-white/5 p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 space-y-2">
              <label class="text-sm text-white/80">Question {{ qi + 1 }}</label>
              <input v-model="q.text" type="text" placeholder="Intitulé de la question" class="w-full rounded-lg bg-white/10 px-3 py-2 ring-1 ring-white/20 placeholder:text-white/40 focus:outline-none focus:ring-white/40" />
            </div>
            <div class="flex shrink-0 gap-2 pt-7">
              <button @click="moveQuestion(qi, -1)" class="rounded-md bg-white/10 px-2 py-1 text-xs ring-1 ring-white/20 hover:bg-white/15" title="Monter">▲</button>
              <button @click="moveQuestion(qi, 1)" class="rounded-md bg-white/10 px-2 py-1 text-xs ring-1 ring-white/20 hover:bg-white/15" title="Descendre">▼</button>
              <button @click="removeQuestion(qi)" class="rounded-md bg-rose-600/20 px-2 py-1 text-xs text-rose-200 ring-1 ring-rose-400/30 hover:bg-rose-600/30" title="Supprimer">Suppr.</button>
            </div>
          </div>

          <!-- Answers -->
          <div class="mt-4 rounded-lg bg-white/5 p-3 ring-1 ring-white/10">
            <div class="mb-2 flex items-center justify-between">
              <div class="text-sm text-white/80">Réponses</div>
              <button @click="addAnswer(qi)" class="rounded-md bg-white/10 px-2 py-1 text-xs ring-1 ring-white/20 hover:bg-white/15">+ Ajouter une réponse</button>
            </div>
            <div class="space-y-2">
              <div v-for="(a, ai) in q.answers" :key="a.id ?? `new-a-${qi}-${ai}`" class="flex items-center gap-2">
                <input v-model="a.text" type="text" placeholder="Réponse" class="min-w-0 flex-1 rounded-md bg-white/10 px-3 py-2 text-sm ring-1 ring-white/20 placeholder:text-white/40 focus:outline-none focus:ring-white/40" />
                <label class="inline-flex items-center gap-2 text-xs text-white/80">
                  <input type="checkbox" v-model="a.is_correct" class="h-4 w-4 rounded border-white/20 bg-white/10" /> Correcte
                </label>
                <div class="flex items-center gap-1">
                  <button @click="moveAnswer(qi, ai, -1)" class="rounded-md bg-white/10 px-2 py-1 text-xs ring-1 ring-white/20 hover:bg-white/15" title="Monter">▲</button>
                  <button @click="moveAnswer(qi, ai, 1)" class="rounded-md bg-white/10 px-2 py-1 text-xs ring-1 ring-white/20 hover:bg-white/15" title="Descendre">▼</button>
                  <button @click="removeAnswer(qi, ai)" class="rounded-md bg-rose-600/20 px-2 py-1 text-xs text-rose-200 ring-1 ring-rose-400/30 hover:bg-rose-600/30" title="Supprimer">Suppr.</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6">
        <button @click="saveStructure" :disabled="structureForm.processing" class="inline-flex items-center rounded-lg bg-emerald-600 px-5 py-2.5 font-semibold ring-1 ring-white/20 transition hover:brightness-110 disabled:opacity-60">
          {{ structureForm.processing ? 'Enregistrement…' : 'Enregistrer les questions / réponses' }}
        </button>
      </div>
    </div>
  </div>
</template>
