import { computed, onUnmounted, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';

export type AnswerSyncStatus = 'idle' | 'syncing' | 'synced' | 'failed' | 'rejected';

type PendingAnswer = {
    questionId: number;
    answerId: number;
};

const RETRY_DELAYS_MS = [300, 800, 2000, 5000];
const REQUEST_TIMEOUT_MS = 8000;

export function useAnswerSync(options: {
    submitUrl: MaybeRefOrGetter<string>;
    quizUuid: MaybeRefOrGetter<string>;
    questionId: MaybeRefOrGetter<number | null>;
    serverHasAnswered: MaybeRefOrGetter<boolean>;
    serverSelectedAnswerId: MaybeRefOrGetter<number | null>;
    onUnauthenticated?: () => void;
}) {
    const localSelectedAnswerId = ref<number | null>(null);
    const localHasAnswered = ref(false);
    const status = ref<AnswerSyncStatus>('idle');
    const errorMessage = ref<string | null>(null);

    let generation = 0;
    let retryTimer: ReturnType<typeof setTimeout> | null = null;
    let settleRetryWait: ((aborted: boolean) => void) | null = null;
    let abortController: AbortController | null = null;

    const selectedAnswerId = computed(() => localSelectedAnswerId.value ?? toValue(options.serverSelectedAnswerId));
    const hasAnswered = computed(() => localHasAnswered.value || toValue(options.serverHasAnswered));

    function storageKey(uuid: string): string {
        return `qiz:pending-answer:${uuid}`;
    }

    function readPending(uuid: string): PendingAnswer | null {
        if (typeof sessionStorage === 'undefined') {
            return null;
        }

        try {
            const raw = sessionStorage.getItem(storageKey(uuid));
            if (!raw) {
                return null;
            }

            const parsed = JSON.parse(raw) as Partial<PendingAnswer>;
            if (typeof parsed.questionId !== 'number' || typeof parsed.answerId !== 'number') {
                return null;
            }

            return { questionId: parsed.questionId, answerId: parsed.answerId };
        } catch {
            return null;
        }
    }

    function writePending(uuid: string, pending: PendingAnswer): void {
        if (typeof sessionStorage === 'undefined') {
            return;
        }

        sessionStorage.setItem(storageKey(uuid), JSON.stringify(pending));
    }

    function clearPending(uuid: string): void {
        if (typeof sessionStorage === 'undefined') {
            return;
        }

        sessionStorage.removeItem(storageKey(uuid));
    }

    function cancelInFlight(): void {
        generation += 1;
        if (retryTimer) {
            clearTimeout(retryTimer);
            retryTimer = null;
        }
        if (settleRetryWait) {
            settleRetryWait(true);
            settleRetryWait = null;
        }
        abortController?.abort();
        abortController = null;
    }

    function resetLocal(): void {
        localSelectedAnswerId.value = null;
        localHasAnswered.value = false;
        status.value = 'idle';
        errorMessage.value = null;
    }

    function applyServerTruth(): void {
        clearPending(toValue(options.quizUuid));
        localSelectedAnswerId.value = null;
        localHasAnswered.value = false;
        status.value = 'synced';
        errorMessage.value = null;
    }

    function restoreOrReset(): void {
        const uuid = toValue(options.quizUuid);
        const questionId = toValue(options.questionId);

        if (toValue(options.serverHasAnswered)) {
            applyServerTruth();
            return;
        }

        const pending = readPending(uuid);
        if (pending && pending.questionId === questionId) {
            localSelectedAnswerId.value = pending.answerId;
            localHasAnswered.value = true;
            errorMessage.value = null;
            void startSync(pending.answerId, pending.questionId);
            return;
        }

        if (pending) {
            clearPending(uuid);
        }

        resetLocal();
    }

    function xsrfToken(): string {
        if (typeof document === 'undefined') {
            return '';
        }

        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    function parseErrorMessage(payload: unknown, fallback: string): string {
        if (!payload || typeof payload !== 'object') {
            return fallback;
        }

        const errors = (payload as { errors?: { answer_id?: string[] } }).errors?.answer_id;
        if (Array.isArray(errors) && typeof errors[0] === 'string' && errors[0]) {
            return errors[0];
        }

        const message = (payload as { message?: unknown }).message;
        if (typeof message === 'string' && message) {
            return message;
        }

        return fallback;
    }

    async function parseJson(response: Response): Promise<unknown> {
        try {
            return await response.json();
        } catch {
            return null;
        }
    }

    function isRedirectStatus(statusCode: number): boolean {
        return statusCode === 301 || statusCode === 302 || statusCode === 303 || statusCode === 307 || statusCode === 308;
    }

    async function postAnswer(
        answerId: number,
        questionId: number,
        signal: AbortSignal,
    ): Promise<{
        kind: 'ok' | 'retry' | 'rejected' | 'unauthenticated';
        message?: string;
    }> {
        let response: Response;

        try {
            response = await fetch(toValue(options.submitUrl), {
                method: 'POST',
                credentials: 'same-origin',
                redirect: 'manual',
                signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    answer_id: answerId,
                    question_id: questionId,
                }),
            });
        } catch (error) {
            if (signal.aborted) {
                return { kind: 'retry' };
            }

            return { kind: 'retry', message: error instanceof Error ? error.message : undefined };
        }

        if (response.type === 'opaqueredirect' || response.status === 0 || isRedirectStatus(response.status)) {
            return { kind: 'unauthenticated' };
        }

        if (response.status === 401 || response.status === 419) {
            return { kind: 'unauthenticated' };
        }

        if (response.ok) {
            return { kind: 'ok' };
        }

        if (response.status === 422) {
            const payload = await parseJson(response);
            return {
                kind: 'rejected',
                message: parseErrorMessage(payload, "Impossible d'enregistrer la réponse."),
            };
        }

        if (response.status >= 500 || response.status === 408 || response.status === 429) {
            return { kind: 'retry' };
        }

        const payload = await parseJson(response);
        return {
            kind: 'rejected',
            message: parseErrorMessage(payload, "Impossible d'enregistrer la réponse."),
        };
    }

    async function startSync(answerId: number, questionId: number): Promise<void> {
        cancelInFlight();
        const currentGeneration = generation;
        status.value = 'syncing';
        errorMessage.value = null;

        const maxAttempts = RETRY_DELAYS_MS.length + 1;

        for (let attempt = 0; attempt < maxAttempts; attempt++) {
            if (currentGeneration !== generation) {
                return;
            }

            abortController?.abort();
            abortController = new AbortController();
            const timeout = setTimeout(() => abortController?.abort(), REQUEST_TIMEOUT_MS);

            const result = await postAnswer(answerId, questionId, abortController.signal);
            clearTimeout(timeout);

            if (currentGeneration !== generation) {
                return;
            }

            if (toValue(options.questionId) !== questionId) {
                return;
            }

            if (result.kind === 'ok') {
                clearPending(toValue(options.quizUuid));
                status.value = 'synced';
                errorMessage.value = null;
                return;
            }

            if (result.kind === 'unauthenticated') {
                options.onUnauthenticated?.();
                return;
            }

            if (result.kind === 'rejected') {
                clearPending(toValue(options.quizUuid));
                localSelectedAnswerId.value = null;
                localHasAnswered.value = false;
                status.value = 'rejected';
                errorMessage.value = result.message ?? "Impossible d'enregistrer la réponse.";
                return;
            }

            const delay = RETRY_DELAYS_MS[attempt];
            if (delay === undefined) {
                break;
            }

            const aborted = await new Promise<boolean>((resolve) => {
                settleRetryWait = resolve;
                retryTimer = setTimeout(() => {
                    retryTimer = null;
                    settleRetryWait = null;
                    resolve(false);
                }, delay);
            });

            if (aborted || currentGeneration !== generation) {
                return;
            }
        }

        if (currentGeneration !== generation) {
            return;
        }

        status.value = 'failed';
        errorMessage.value = 'Envoi impossible';
    }

    function select(answerId: number): void {
        const questionId = toValue(options.questionId);
        if (hasAnswered.value || questionId == null) {
            return;
        }

        localSelectedAnswerId.value = answerId;
        localHasAnswered.value = true;
        errorMessage.value = null;
        writePending(toValue(options.quizUuid), { questionId, answerId });
        void startSync(answerId, questionId);
    }

    function retry(): void {
        const questionId = toValue(options.questionId);
        const answerId = selectedAnswerId.value;
        if (questionId == null || answerId == null) {
            return;
        }

        localSelectedAnswerId.value = answerId;
        localHasAnswered.value = true;
        writePending(toValue(options.quizUuid), { questionId, answerId });
        void startSync(answerId, questionId);
    }

    watch(
        () => toValue(options.questionId),
        () => {
            cancelInFlight();
            restoreOrReset();
        },
        { immediate: true },
    );

    watch(
        () => toValue(options.serverHasAnswered),
        (answered) => {
            if (answered) {
                cancelInFlight();
                applyServerTruth();
            }
        },
    );

    onUnmounted(() => {
        cancelInFlight();
    });

    return {
        selectedAnswerId,
        hasAnswered,
        status,
        errorMessage,
        select,
        retry,
    };
}
