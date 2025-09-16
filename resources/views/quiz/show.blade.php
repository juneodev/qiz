<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Question</title>
    @vite('resources/js/app.ts')
</head>
<body class="min-h-screen bg-[#102846] text-white">
    <div class="mx-auto max-w-6xl px-6 py-10">
        @php
            $showUrl = $showUrl ?? route('quiz.show');
            $submitUrl = $submitUrl ?? route('quiz.submit');
        @endphp
        <!-- Header / Progress -->
        <div class="mb-6 flex items-center justify-between text-sm text-white/80">
            @isset($total)
                <div>Question {{ ($currentIndex ?? 0) + 1 }} / {{ $total }}</div>
            @endisset
            <div>
                <a href="{{ $showUrl }}?reset=1" class="underline decoration-white/30 underline-offset-4 hover:text-white">Réinitialiser</a>
            </div>
        </div>

        <!-- If finished, show result -->
        @if (!empty($finished))
            <div class="rounded-2xl bg-[#1f57c7]/95 p-8 text-center shadow-2xl ring-1 ring-white/10">
                <h2 class="text-3xl sm:text-4xl font-semibold tracking-wide">Quiz terminé 🎉</h2>
                <p class="mt-4 text-lg">Votre score: <span class="font-bold">{{ $score }}</span> / {{ $total }}</p>
                <div class="mt-6">
                    <a href="{{ $showUrl }}?reset=1" class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">Recommencer</a>
                </div>
            </div>
        @else
        <!-- Question card -->
        <div class="relative rounded-2xl bg-[#1f57c7]/95 shadow-2xl ring-1 ring-white/10">
            <div class="absolute left-6 top-1/2 -translate-y-1/2 hidden sm:flex h-14 w-14 items-center justify-center rounded-full bg-white/10 ring-2 ring-white/30">
                <!-- left icon -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7 text-white/90"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7 7h10v10H7z"/></svg>
            </div>
            <div class="absolute right-6 top-1/2 -translate-y-1/2 hidden sm:flex h-14 w-14 items-center justify-center rounded-full bg-white/10 ring-2 ring-white/30">
                <!-- right icon -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7 text-white/90"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7 7h10v10H7z"/></svg>
            </div>
            <div class="px-8 py-8 sm:py-10">
                <h2 class="text-center text-3xl sm:text-4xl font-semibold tracking-wide">{{ $question->text }}</h2>
            </div>
        </div>

        <!-- Answers -->
        <form action="{{ $submitUrl }}" method="POST" class="mt-8">
            @csrf
            <fieldset class="grid gap-5 md:grid-cols-2">
                @php $letters = ['A','B','C','D','E','F']; @endphp
                @foreach ($question->answers as $answer)
                    @php
                        $isSelected = isset($selectedAnswerId) && $selectedAnswerId == $answer->id;
                        $stateColor = $isSelected ? ($isCorrect ? 'ring-emerald-400 bg-emerald-500' : 'ring-rose-400 bg-rose-500') : 'ring-white/15 bg-[#2b7cff]';
                    @endphp
                    <label class="group relative cursor-pointer rounded-2xl px-6 py-5 text-white shadow-xl ring-1 transition-all hover:-translate-y-0.5 hover:shadow-2xl {{ $stateColor }}">
                        <input type="radio" name="answer_id" value="{{ $answer->id }}" class="peer absolute inset-0 h-full w-full cursor-pointer opacity-0" {{ $isSelected ? 'checked' : '' }} {{ isset($isCorrect) ? 'disabled' : '' }} />
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15 ring-2 ring-white/40">
                                <span class="text-xl font-bold">{{ $letters[$loop->index] ?? chr(65+$loop->index) }}</span>
                            </div>
                            <div class="text-lg sm:text-xl font-medium tracking-wide leading-snug">
                                {{ $answer->text }}
                            </div>
                        </div>
                    </label>
                @endforeach
            </fieldset>

            @error('answer_id')
                <p class="mt-3 text-sm text-rose-300">{{ $message }}</p>
            @enderror

            <div class="mt-6 flex items-center gap-4">
                @if (!isset($isCorrect))
                    <button type="submit" class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">Valider ma réponse</button>
                @else
                    @php $isLast = isset($total, $currentIndex) && ($currentIndex + 1 >= $total); @endphp
                    @if ($isLast)
                        <a href="{{ $showUrl }}?next=1" class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">Terminer</a>
                    @else
                        <a href="{{ $showUrl }}?next=1" class="inline-flex items-center rounded-full bg-white/10 px-6 py-3 text-base font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">Question suivante</a>
                    @endif
                @endif

                <a href="{{ $showUrl }}?reset=1" class="text-sm text-white/70 hover:text-white">Réinitialiser</a>
            </div>
        </form>

        <!-- Explanation & progress -->
        <div class="mt-8">
            @if (isset($isCorrect))
                <p class="text-center text-lg italic text-white/90">
                    @if ($isCorrect)
                        Bravo ! C'est la bonne réponse 🎉
                    @else
                        Dommage, ce n'est pas la bonne réponse. La bonne réponse est « {{ optional($question->answers->firstWhere('is_correct', true))->text }} ».
                    @endif
                </p>
            @else
                <p class="text-center text-lg italic text-white/80">Sélectionnez une réponse puis validez.</p>
            @endif

            @isset($total)
            <div class="mt-6 flex items-center">
                <div class="h-5 w-5 rounded-full bg-[#ff4b3e]"></div>
                <div class="ml-4 h-5 flex-1 rounded-full bg-white/30">
                    @php $percent = max(0, min(100, (int) round(((($currentIndex ?? 0)) / max(1, $total)) * 100))); @endphp
                    <div class="h-5 rounded-full bg-white/70" style="width: {{ $percent }}%"></div>
                </div>
            </div>
            @endisset
        </div>
        @endif

        <div class="mt-10 text-center text-sm text-white/70">
            <a class="underline decoration-white/30 underline-offset-4 hover:text-white" href="/">Retour à l'accueil</a>
        </div>
    </div>
</body>
</html>
