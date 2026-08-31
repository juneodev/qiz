import { computed, onMounted, onUnmounted, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'

export function useQuestionTimer(
    answerClosesAt: MaybeRefOrGetter<string | null | undefined>,
    durationSeconds: MaybeRefOrGetter<number> = 20,
) {
    const now = ref(Date.now())
    let interval: ReturnType<typeof setInterval> | null = null

    function tick() {
        now.value = Date.now()
    }

    watch(() => toValue(answerClosesAt), () => tick(), { immediate: true })

    onMounted(() => {
        tick()
        interval = setInterval(tick, 250)
    })

    onUnmounted(() => {
        if (interval) {
            clearInterval(interval)
            interval = null
        }
    })

    const remainingMs = computed(() => {
        const closes = toValue(answerClosesAt)
        if (!closes) {
            return 0
        }

        return Math.max(0, new Date(closes).getTime() - now.value)
    })

    const remainingSeconds = computed(() => {
        const duration = toValue(durationSeconds)

        return Math.min(duration, Math.ceil(remainingMs.value / 1000))
    })

    const progress = computed(() => {
        const duration = toValue(durationSeconds) * 1000
        if (duration <= 0) {
            return 0
        }

        return Math.max(0, Math.min(100, (remainingMs.value / duration) * 100))
    })

    const expired = computed(() => {
        const closes = toValue(answerClosesAt)
        if (!closes) {
            return true
        }

        return remainingMs.value <= 0
    })

    return {
        remainingSeconds,
        progress,
        expired,
    }
}
