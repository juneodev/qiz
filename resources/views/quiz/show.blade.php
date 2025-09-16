<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Question</title>
    @vite('resources/js/app.ts')
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <div class="max-w-2xl mx-auto p-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold">Quiz de démo</h1>
            <p class="text-gray-600">Répondez à la question ci-dessous.</p>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">{{ $question->text }}</h2>

            @if (isset($isCorrect))
                <div class="mb-4">
                    @if ($isCorrect)
                        <div class="rounded-md bg-green-50 text-green-800 px-4 py-3 border border-green-200">Bravo ! C'est la bonne réponse 🎉</div>
                    @else
                        <div class="rounded-md bg-red-50 text-red-700 px-4 py-3 border border-red-200">Dommage, ce n'est pas la bonne réponse.</div>
                    @endif
                </div>
            @endif

            <form action="{{ route('quiz.submit') }}" method="POST" class="space-y-4">
                @csrf
                <fieldset class="space-y-3">
                    @foreach ($question->answers as $answer)
                        <label class="flex items-center gap-3 p-3 border rounded-md hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="answer_id" value="{{ $answer->id }}" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500"
                                {{ (isset($selectedAnswerId) && $selectedAnswerId == $answer->id) ? 'checked' : '' }}>
                            <span class="text-gray-800">{{ $answer->text }}</span>
                        </label>
                    @endforeach
                </fieldset>

                @error('answer_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="pt-2">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-md shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Valider ma réponse
                    </button>
                    <a href="{{ route('quiz.show') }}" class="ml-3 text-sm text-gray-600 hover:text-gray-900">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="mt-8 text-center text-sm text-gray-500">
            <a class="underline hover:text-gray-700" href="/">Retour à l'accueil</a>
        </div>
    </div>
</body>
</html>
