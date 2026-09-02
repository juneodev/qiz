<script setup lang="ts">
import { useAnswerSync } from '@/composables/useAnswerSync';
import { useQuestionTimer } from '@/composables/useQuestionTimer';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface Answer {
    id: number;
    text: string;
    order?: number;
}

interface Question {
    id: number;
    text: string;
    order?: number;
    answers: Answer[];
}

interface RecapQuestion {
    id: number;
    text: string;
    selectedAnswerId: number | null;
    selectedText: string | null;
    correctAnswerIds: number[];
    correctTexts: string[];
    isCorrect: boolean;
}

interface Recap {
    score: number;
    total: number;
    questions: RecapQuestion[];
}

type PageProps = {
    quiz: {
        uuid: string;
        title: string;
        status: 'waiting' | 'live' | 'finished';
        currentIndex: number;
        total: number;
    };
    participant: { id: number; nickname: string };
    question: Question | null;
    hasAnswered: boolean;
    selectedAnswerId: number | null;
    correctAnswerIds: number[];
    answerClosesAt: string | null;
    answerDurationSeconds: number;
    recap: Recap | null;
    score: { current: number; total: number };
    submitUrl: string;
};

const page = usePage<{ props: PageProps }>();
const props = computed(() => page.props as unknown as PageProps);

const avatarUrl = computed(() => `https://api.dicebear.com/10.x/glyphs/svg?seed=${encodeURIComponent(String(props.value.participant.id))}`);

const letters = ['A', 'B', 'C', 'D', 'E', 'F'];
const pusherStatus = ref('');
const revealReloadRequested = ref(false);
let revealReloadTimer: ReturnType<typeof setTimeout> | null = null;

const isWaiting = computed(() => props.value.quiz.status === 'waiting');
const isLive = computed(() => props.value.quiz.status === 'live');
const isFinished = computed(() => props.value.quiz.status === 'finished');

const answerClosesAt = computed(() => props.value.answerClosesAt);
const answerDurationSeconds = computed(() => props.value.answerDurationSeconds ?? 20);
const correctAnswerIds = computed(() => props.value.correctAnswerIds ?? []);
const revealed = computed(() => correctAnswerIds.value.length > 0);
const questionId = computed(() => props.value.question?.id ?? null);

const {
    selectedAnswerId,
    hasAnswered,
    status: syncStatus,
    errorMessage: syncError,
    select,
    retry: retrySync,
} = useAnswerSync({
    submitUrl: () => props.value.submitUrl,
    quizUuid: () => props.value.quiz.uuid,
    questionId,
    serverHasAnswered: () => props.value.hasAnswered,
    serverSelectedAnswerId: () => props.value.selectedAnswerId,
    onUnauthenticated: () => {
        router.reload({ preserveScroll: true });
    },
});

const selectedIsCorrect = computed(() => selectedAnswerId.value != null && correctAnswerIds.value.includes(selectedAnswerId.value));
const { remainingSeconds, progress, expired } = useQuestionTimer(answerClosesAt, answerDurationSeconds);
const timerUrgent = computed(() => remainingSeconds.value > 0 && remainingSeconds.value <= 5);

function answerButtonClass(answerId: number): string {
    if (revealed.value) {
        if (correctAnswerIds.value.includes(answerId)) {
            return 'bg-emerald-500/80 ring-2 ring-white';
        }
        if (selectedAnswerId.value === answerId) {
            return 'bg-rose-500/80 ring-1 ring-white/15';
        }
        return 'bg-[#2b7cff] ring-1 ring-white/15 opacity-70';
    }

    if (selectedAnswerId.value === answerId) {
        return 'bg-[#2b7cff] ring-2 ring-white';
    }

    return 'bg-[#2b7cff] ring-1 ring-white/15 hover:brightness-110';
}

function submitAnswer(answerId: number) {
    if (hasAnswered.value || expired.value) return;
    select(answerId);
}

watch(answerClosesAt, () => {
    revealReloadRequested.value = false;
});

watch(
    expired,
    (isExpired) => {
        if (!isExpired) {
            revealReloadRequested.value = false;
            return;
        }
        if (!isLive.value || !props.value.question) return;
        if (revealed.value || revealReloadRequested.value) return;

        revealReloadRequested.value = true;
        revealReloadTimer = setTimeout(() => {
            router.reload({ preserveScroll: true });
        }, 400);
    },
    { immediate: true },
);

onMounted(async () => {
    try {
        const mod = await import('pusher-js');
        const Pusher = (mod as any).default || mod;
        const key = (import.meta as any).env?.VITE_PUSHER_APP_KEY;
        const cluster = (import.meta as any).env?.VITE_PUSHER_APP_CLUSTER || 'eu';
        if (!Pusher || !key) {
            pusherStatus.value = "Temps réel non configuré — rechargez la page si l'hôte avance.";
            return;
        }
        const pusher = new Pusher(key, { cluster, forceTLS: true });
        const channel = pusher.subscribe(`quiz.${props.value.quiz.uuid}`);
        const reload = () => {
            router.reload({ preserveScroll: true });
        };
        channel.bind('advance', reload);
        channel.bind('reset', reload);
    } catch {
        pusherStatus.value = 'Impossible de se connecter au temps réel.';
    }
});

onUnmounted(() => {
    if (revealReloadTimer) {
        clearTimeout(revealReloadTimer);
    }
});
</script>

<template>
    <div class="min-h-dvh bg-[#102846] text-white">
        <div class="mx-auto max-w-3xl px-6 py-10">
            <header class="mb-6 flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <img :src="avatarUrl" :alt="props.participant.nickname" class="size-10 shrink-0 rounded-full bg-white/10 ring-2 ring-white/20" />
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-white">{{ props.participant.nickname }}</div>
                        <div class="truncate text-sm text-white/70">{{ props.quiz.title }}</div>
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    <div class="text-xs tracking-wide text-white/60 uppercase">Score</div>
                    <div class="text-2xl leading-none font-semibold tabular-nums">{{ props.score.current }} / {{ props.score.total }}</div>
                </div>
            </header>

            <div v-if="pusherStatus" class="mb-4 text-center text-xs text-amber-200/80">{{ pusherStatus }}</div>

            <div v-if="isWaiting" class="rounded-2xl bg-[#1f57c7]/95 p-8 text-center shadow-2xl ring-1 ring-white/10">
                <h2 class="text-3xl font-semibold tracking-wide">En attente du lancement…</h2>
                <p class="mt-4 text-lg text-white/80">L'hôte va bientôt démarrer le quiz. Gardez cet écran ouvert.</p>
            </div>

            <div v-else-if="isFinished" class="space-y-6">
                <div class="rounded-2xl bg-[#1f57c7]/95 p-8 text-center shadow-2xl ring-1 ring-white/10">
                    <h2 class="text-3xl font-semibold tracking-wide">Quiz terminé</h2>
                    <p class="mt-4 text-lg text-white/80">Merci {{ props.participant.nickname }} pour votre participation.</p>
                    <p v-if="props.recap" class="mt-4 text-4xl font-semibold tabular-nums">{{ props.recap.score }} / {{ props.recap.total }}</p>
                </div>

                <div v-if="props.recap" class="space-y-4">
                    <div
                        v-for="(item, index) in props.recap.questions"
                        :key="item.id"
                        class="rounded-2xl bg-[#1f57c7]/80 p-6 shadow-xl ring-1 ring-white/10"
                    >
                        <div class="text-sm text-white/60">Question {{ index + 1 }}</div>
                        <h3 class="mt-1 text-lg leading-snug font-semibold">{{ item.text }}</h3>
                        <p class="mt-4 text-sm" :class="item.isCorrect ? 'text-emerald-200' : item.selectedText ? 'text-rose-200' : 'text-white/70'">
                            Votre réponse :
                            <span class="font-medium">{{ item.selectedText ?? 'Sans réponse' }}</span>
                        </p>
                        <p v-if="!item.isCorrect && item.correctTexts.length" class="mt-1 text-sm text-emerald-200">
                            Bonne réponse : <span class="font-medium">{{ item.correctTexts.join(', ') }}</span>
                        </p>
                    </div>
                </div>

                <Link href="/" class="inline-block text-sm text-white/70 underline decoration-white/30 underline-offset-4 hover:text-white"
                    >Retour à l'accueil</Link
                >
            </div>

            <template v-else-if="isLive && props.question">
                <div class="mb-4 flex items-center justify-between gap-4 text-sm text-white/70">
                    <div>Question {{ Math.min(props.quiz.currentIndex + 1, props.quiz.total) }} / {{ props.quiz.total }}</div>
                    <div class="text-2xl leading-none font-semibold tabular-nums" :class="timerUrgent ? 'text-amber-200' : 'text-white'">
                        {{ remainingSeconds }}
                    </div>
                </div>
                <div class="mb-4 h-2 overflow-hidden rounded-full bg-white/20">
                    <div
                        class="h-full rounded-full transition-[width] duration-200"
                        :class="timerUrgent ? 'bg-amber-200' : 'bg-white/70'"
                        :style="{ width: progress + '%' }"
                    ></div>
                </div>
                <div class="rounded-2xl bg-[#1f57c7]/95 p-8 shadow-2xl ring-1 ring-white/10">
                    <h2 class="text-center text-3xl leading-tight font-semibold tracking-wide sm:text-4xl">{{ props.question.text }}</h2>
                </div>

                <p v-if="revealed && hasAnswered && selectedIsCorrect" class="mt-6 text-center text-white/80">Bonne réponse</p>
                <p v-else-if="revealed && hasAnswered" class="mt-6 text-center text-white/80">Mauvaise réponse</p>
                <p v-else-if="revealed" class="mt-6 text-center text-white/80">Temps écoulé</p>
                <p v-else-if="hasAnswered && syncStatus !== 'failed'" class="mt-6 text-center text-white/80">
                    Réponse enregistrée, attendez la question suivante.
                </p>
                <p v-else-if="expired" class="mt-6 text-center text-white/80">Temps écoulé</p>
                <div v-if="syncStatus === 'failed'" class="mt-4 text-center">
                    <p class="text-sm text-rose-300">Envoi impossible</p>
                    <button
                        type="button"
                        class="mt-2 rounded-lg bg-white/10 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/20 hover:bg-white/15"
                        @click="retrySync"
                    >
                        Réessayer
                    </button>
                </div>
                <div v-else-if="syncError" class="mt-4 text-center text-sm text-rose-300">{{ syncError }}</div>

                <div class="mt-8 grid gap-4 md:grid-cols-2">
                    <button
                        v-for="(answer, idx) in props.question.answers"
                        :key="answer.id"
                        type="button"
                        :disabled="hasAnswered || expired"
                        class="rounded-2xl px-6 py-5 text-left text-white shadow-xl transition disabled:cursor-not-allowed"
                        :class="answerButtonClass(answer.id)"
                        @click="submitAnswer(answer.id)"
                    >
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15 ring-2 ring-white/40">
                                <span class="text-xl font-bold">{{ letters[idx] ?? String.fromCharCode(65 + idx) }}</span>
                            </div>
                            <div class="text-lg leading-snug font-medium tracking-wide sm:text-xl">
                                {{ answer.text }}
                            </div>
                        </div>
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
