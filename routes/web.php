<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\QuizController;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\Request;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Public quiz routes
Route::get('/quiz', [QuizController::class, 'show'])->name('quiz.show');
Route::post('/quiz', [QuizController::class, 'submit'])->name('quiz.submit');

// Authenticated app routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('quizzes', function () {
        $quizzes = Quiz::query()
            ->select('id', 'title', 'description', 'published_at', 'created_at')
            ->latest()
            ->get();

        return Inertia::render('Quizzes/Index', [
            'quizzes' => $quizzes,
        ]);
    })->name('quizzes.index');

    // Create quiz page
    Route::get('quizzes/create', function () {
        return Inertia::render('Quizzes/Create');
    })->name('quizzes.create');

    // Store new quiz
    Route::post('quizzes', function (Request $request) {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $quiz = Quiz::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'published_at' => !empty($validated['publish']) ? now() : null,
        ]);

        return redirect()->route('quizzes.index');
    })->name('quizzes.store');

    // Edit quiz page
    Route::get('quizzes/{quiz}/edit', function (Quiz $quiz) {
        $quiz->load(['questions' => function ($q) {
            $q->orderBy('order');
        }, 'questions.answers' => function ($q) {
            $q->orderBy('order');
        }]);

        $questions = $quiz->questions->map(function ($question) {
            return [
                'id' => $question->id,
                'text' => $question->text,
                'order' => $question->order,
                'answers' => $question->answers->map(function ($answer) {
                    return [
                        'id' => $answer->id,
                        'text' => $answer->text,
                        'is_correct' => (bool) $answer->is_correct,
                        'order' => $answer->order,
                    ];
                })->values(),
            ];
        })->values();

        return Inertia::render('Quizzes/Edit', [
            'quiz' => $quiz->only(['id', 'title', 'description', 'published_at', 'created_at']),
            'questions' => $questions,
        ]);
    })->name('quizzes.edit');

    // Update existing quiz
    Route::put('quizzes/{quiz}', function (Request $request, Quiz $quiz) {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $quiz->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'published_at' => !empty($validated['publish']) ? now() : null,
        ]);

        return redirect()->route('quizzes.index');
    })->name('quizzes.update');

    // Update quiz structure: questions and answers
    Route::put('quizzes/{quiz}/structure', function (Request $request, Quiz $quiz) {
        $validated = $request->validate([
            'questions' => ['array'],
            'questions.*.id' => ['nullable', 'integer', 'exists:questions,id'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.order' => ['nullable', 'integer', 'min:0'],
            'questions.*.answers' => ['array'],
            'questions.*.answers.*.id' => ['nullable', 'integer', 'exists:answers,id'],
            'questions.*.answers.*.text' => ['required', 'string'],
            'questions.*.answers.*.is_correct' => ['nullable', 'boolean'],
            'questions.*.answers.*.order' => ['nullable', 'integer', 'min:0'],
        ]);

        $questionsData = collect($validated['questions'] ?? [])
            ->map(function ($q, $idx) {
                $q['order'] = $q['order'] ?? $idx + 1;
                $q['answers'] = collect($q['answers'] ?? [])->values()->all();
                return $q;
            })->values();

        $keptQuestionIds = [];

        foreach ($questionsData as $qIndex => $qData) {
            $question = null;

            if (! empty($qData['id'])) {
                $question = Question::where('quiz_id', $quiz->id)->where('id', $qData['id'])->first();
            }

            if (! $question) {
                $question = new Question();
                $question->quiz_id = $quiz->id;
            }

            $question->text = $qData['text'];
            $question->order = $qData['order'] ?? ($qIndex + 1);
            $question->save();

            $keptQuestionIds[] = $question->id;

            // Sync answers for this question
            $answersData = collect($qData['answers'] ?? [])
                ->map(function ($a, $aIdx) {
                    $a['order'] = $a['order'] ?? $aIdx + 1;
                    $a['is_correct'] = ! empty($a['is_correct']);
                    return $a;
                })->values();

            $keptAnswerIds = [];

            foreach ($answersData as $aIndex => $aData) {
                $answer = null;
                if (! empty($aData['id'])) {
                    $answer = Answer::where('question_id', $question->id)->where('id', $aData['id'])->first();
                }

                if (! $answer) {
                    $answer = new Answer();
                    $answer->question_id = $question->id;
                }

                $answer->text = $aData['text'];
                $answer->is_correct = (bool) ($aData['is_correct'] ?? false);
                $answer->order = $aData['order'] ?? ($aIndex + 1);
                $answer->save();

                $keptAnswerIds[] = $answer->id;
            }

            // Delete removed answers
            Answer::where('question_id', $question->id)
                ->when(! empty($keptAnswerIds), fn ($q) => $q->whereNotIn('id', $keptAnswerIds))
                ->when(empty($keptAnswerIds), fn ($q) => $q)
                ->delete();
        }

        // Delete removed questions (cascade will delete answers)
        Question::where('quiz_id', $quiz->id)
            ->when(! empty($keptQuestionIds), fn ($q) => $q->whereNotIn('id', $keptQuestionIds))
            ->when(empty($keptQuestionIds), fn ($q) => $q)
            ->delete();

        return redirect()->route('quizzes.edit', ['quiz' => $quiz->id]);
    })->name('quizzes.structure');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
