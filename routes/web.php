<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\PlayQuizController;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\Request;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Public play: enter UUID page and play by UUID
Route::get('/quiz', function () {
    return Inertia::render('Quiz/Enter');
})->name('quiz.enter');

Route::post('/quiz', function (Request $request) {
    $data = $request->validate([
        'uuid' => ['required', 'uuid'],
    ]);
    return redirect()->route('quiz.play.show', ['uuid' => $data['uuid']]);
})->name('quiz.enter.submit');

Route::get('/quiz/{uuid}', [PlayQuizController::class, 'show'])->name('quiz.play.show');
Route::post('/quiz/{uuid}', [PlayQuizController::class, 'submit'])->name('quiz.play.submit');
Route::post('/quiz/{uuid}/advance', [PlayQuizController::class, 'advance'])->name('quiz.play.advance');
Route::post('/quiz/{uuid}/reset', [PlayQuizController::class, 'reset'])->name('quiz.play.reset');

// Authenticated app routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();

        $quizzes = Quiz::query()
            ->where('user_id', $user->id)
            ->select('id', 'uuid', 'title', 'description', 'published_at', 'created_at')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($quiz) {
                $quiz->play_url = route('quiz.play.show', ['uuid' => $quiz->uuid]);
                return $quiz;
            });

        $total = Quiz::where('user_id', $user->id)->count();
        $published = Quiz::where('user_id', $user->id)->whereNotNull('published_at')->count();
        $drafts = $total - $published;

        return Inertia::render('Quizzes/Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
            ],
            'stats' => [
                'total' => $total,
                'published' => $published,
                'drafts' => $drafts,
            ],
            'recentQuizzes' => $quizzes,
        ]);
    })->name('dashboard');

    Route::get('quizzes', function () {
        $quizzes = Quiz::query()
            ->where('user_id', auth()->id())
            ->select('id', 'uuid', 'title', 'description', 'published_at', 'created_at')
            ->latest()
            ->get()
            ->map(function ($quiz) {
                $quiz->play_url = route('quiz.play.show', ['uuid' => $quiz->uuid]);
                return $quiz;
            });

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

        $quiz = $request->user()->quizzes()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'published_at' => !empty($validated['publish']) ? now() : null,
        ]);

        return redirect()->route('quizzes.edit', ['quiz' => $quiz->id]);
    })->name('quizzes.store');

    // Edit quiz page
    Route::get('quizzes/{quiz}/edit', function (Quiz $quiz) {
        abort_if($quiz->user_id !== auth()->id(), 404);

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
                        'is_correct' => (bool)$answer->is_correct,
                        'order' => $answer->order,
                    ];
                })->values(),
            ];
        })->values();

        $quizArray = $quiz->only(['id', 'uuid', 'title', 'description', 'published_at', 'created_at']);
        $quizArray['play_url'] = route('quiz.play.show', ['uuid' => $quiz->uuid]);
        $quizArray['console_url'] = route('quizzes.console', ['quiz' => $quiz->id]);

        return Inertia::render('Quizzes/Edit', [
            'quiz' => $quizArray,
            'questions' => $questions,
        ]);
    })->name('quizzes.edit');

    // Console to control a quiz (Next/Reset), authenticated only
    Route::get('quizzes/{quiz}/console', function (Quiz $quiz) {
        abort_if($quiz->user_id !== auth()->id(), 404);

        $quizArray = $quiz->only(['id', 'uuid', 'title']);
        $quizArray['play_url'] = route('quiz.play.show', ['uuid' => $quiz->uuid]);
        $quizArray['advance_url'] = route('quiz.play.advance', ['uuid' => $quiz->uuid]);
        $quizArray['reset_url'] = route('quiz.play.reset', ['uuid' => $quiz->uuid]);

        return Inertia::render('Quizzes/Console', [
            'quiz' => $quizArray,
        ]);
    })->name('quizzes.console');

    // Update existing quiz
    Route::put('quizzes/{quiz}', function (Request $request, Quiz $quiz) {
        abort_if($quiz->user_id !== auth()->id(), 404);

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

        return redirect()->route('dashboard');
    })->name('quizzes.update');

    // Update quiz structure: questions and answers
    Route::put('quizzes/{quiz}/structure', function (Request $request, Quiz $quiz) {
        abort_if($quiz->user_id !== auth()->id(), 404);

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

            if (!empty($qData['id'])) {
                $question = Question::where('quiz_id', $quiz->id)->where('id', $qData['id'])->first();
            }

            if (!$question) {
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
                    $a['is_correct'] = !empty($a['is_correct']);
                    return $a;
                })->values();

            $keptAnswerIds = [];

            foreach ($answersData as $aIndex => $aData) {
                $answer = null;
                if (!empty($aData['id'])) {
                    $answer = Answer::where('question_id', $question->id)->where('id', $aData['id'])->first();
                }

                if (!$answer) {
                    $answer = new Answer();
                    $answer->question_id = $question->id;
                }

                $answer->text = $aData['text'];
                $answer->is_correct = (bool)($aData['is_correct'] ?? false);
                $answer->order = $aData['order'] ?? ($aIndex + 1);
                $answer->save();

                $keptAnswerIds[] = $answer->id;
            }

            // Delete removed answers
            Answer::where('question_id', $question->id)
                ->when(!empty($keptAnswerIds), fn($q) => $q->whereNotIn('id', $keptAnswerIds))
                ->when(empty($keptAnswerIds), fn($q) => $q)
                ->delete();
        }

        // Delete removed questions (cascade will delete answers)
        Question::where('quiz_id', $quiz->id)
            ->when(!empty($keptQuestionIds), fn($q) => $q->whereNotIn('id', $keptQuestionIds))
            ->when(empty($keptQuestionIds), fn($q) => $q)
            ->delete();

        return redirect()->route('quizzes.edit', ['quiz' => $quiz->id]);
    })->name('quizzes.structure');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
