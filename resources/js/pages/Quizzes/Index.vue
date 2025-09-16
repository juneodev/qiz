<script setup lang="ts">
import { computed } from 'vue';
import Layout from './Layout.vue';

// Apply shared layout for the Quizzes section
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
defineOptions({ layout: Layout });

interface Quiz {
  id: number;
  uuid: string;
  title: string;
  description?: string | null;
  published_at?: string | null;
  created_at: string;
  play_url: string;
}

const props = defineProps<{ quizzes: Quiz[] }>();

const hasQuizzes = computed(() => props.quizzes && props.quizzes.length > 0);
</script>

<template>
  <div class="mx-auto max-w-7xl px-6 pb-16 pt-4">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Mes quizzes</h1>
      <a
        href="/quizzes/create"
        class="inline-flex items-center justify-center rounded-lg bg-[#2b7cff] px-4 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:brightness-110"
      >Créer un nouveau quizz</a>
    </div>

    <div v-if="hasQuizzes" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="quiz in props.quizzes"
        :key="quiz.id"
        class="rounded-xl bg-white/5 p-5 ring-1 ring-white/10 transition hover:bg-white/[0.08]"
      >
        <div class="flex items-start justify-between gap-4">
          <h2 class="line-clamp-2 text-base font-semibold">{{ quiz.title }}</h2>
          <span
            class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1"
            :class="quiz.published_at ? 'bg-emerald-500/15 text-emerald-200 ring-emerald-500/30' : 'bg-amber-500/15 text-amber-200 ring-amber-500/30'"
          >
            {{ quiz.published_at ? 'Publié' : 'Brouillon' }}
          </span>
        </div>
        <p class="mt-2 line-clamp-3 text-sm text-white/80" v-if="quiz.description">{{ quiz.description }}</p>
        <div class="mt-4 space-y-3">
          <div class="flex items-center gap-2">
            <input :value="quiz.play_url" readonly class="w-full rounded bg-white/10 px-2 py-1 text-xs text-white ring-1 ring-white/15" />
            <button type="button" class="rounded bg-white/10 px-2 py-1 text-xs ring-1 ring-white/20 hover:bg-white/15" @click="navigator.clipboard.writeText(quiz.play_url)">Copier</button>
          </div>
          <div class="flex items-center justify-between text-xs text-white/60">
            <span>Créé le {{ new Date(quiz.created_at).toLocaleDateString() }}</span>
            <a :href="`/quizzes/${quiz.id}/edit`" class="underline decoration-white/30 underline-offset-4 hover:text-white">Modifier</a>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="rounded-xl border border-white/10 bg-white/5 p-8 text-center text-white/80">
      Aucun quiz pour le moment.
      <a href="/quizzes/create" class="underline decoration-white/30 underline-offset-4 hover:text-white">Créer un nouveau quizz</a>
    </div>
  </div>
</template>
