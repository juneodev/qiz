<?php

namespace App\Http\Controllers;

use App\Events\QuizProgressUpdated;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlayQuizController extends Controller
{
    protected function getSessionKey(Quiz $quiz): string
    {
        return 'quiz_' . $quiz->uuid;
    }

    protected function bootSessionFor(Quiz $quiz): array
    {
        $key = $this->getSessionKey($quiz);
        $state = session($key, null);

        if (!$state || request()->boolean('reset')) {
            $questionIds = $quiz->questions()->orderBy('order')->pluck('id')->all();
            if (empty($questionIds)) {
                abort(404, 'Aucune question disponible pour ce quiz.');
            }

            $state = [
                'question_ids' => $questionIds,
                'index' => 0,
                'score' => 0,
                'finished' => false,
            ];

            session([$key => $state]);
        }

        return $state;
    }

    public function show(Request $request, string $uuid)
    {
        $quiz = Quiz::where('uuid', $uuid)->firstOrFail();

        // Load all questions with answers (ordered) once; client handles progression locally
        $questions = Question::where('quiz_id', $quiz->id)
            ->orderBy('order')
            ->with(['answers' => function ($q) {
                $q->orderBy('order');
            }])
            ->get(['id', 'text', 'order'])
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'text' => $q->text,
                    'order' => $q->order,
                    'answers' => $q->answers->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'text' => $a->text,
                            'order' => $a->order,
                        ];
                    })->values(),
                ];
            })
            ->values();

        if ($questions->isEmpty()) {
            abort(404, 'Aucune question disponible pour ce quiz.');
        }

        return Inertia::render('Quiz/Play', [
            'questions' => $questions,
            'total' => $questions->count(),
            'uuid' => $quiz->uuid,
        ]);
    }

    public function submit(Request $request, string $uuid)
    {
        $quiz = Quiz::where('uuid', $uuid)->firstOrFail();
        $state = $this->bootSessionFor($quiz);
        $key = $this->getSessionKey($quiz);

        abort_if($state['finished'], 400, 'Quiz terminé.');

        $data = $request->validate([
            'answer_id' => ['required', 'integer', 'exists:answers,id'],
        ]);

        $currentQuestionId = $state['question_ids'][$state['index']];

        $answer = Answer::with('question')->findOrFail($data['answer_id']);

        // Ensure the answer belongs to the current question of this quiz
        if ($answer->question_id !== $currentQuestionId) {
            return back()->withErrors(['answer_id' => 'Réponse invalide pour cette question.'])->withInput();
        }

        $question = $answer->question()->with(['answers' => function ($q) {
            $q->orderBy('order');
        }])->first();

        $isCorrect = (bool) $answer->is_correct;
        if ($isCorrect) {
            $state['score']++;
        }

        if ($state['index'] >= count($state['question_ids']) - 1) {
            $state['finished'] = true;
        }

        session([$key => $state]);

        $showUrl = route('quiz.play.show', ['uuid' => $quiz->uuid]);
        $submitUrl = route('quiz.play.submit', ['uuid' => $quiz->uuid]);

        return Inertia::render('Quiz/Play', [
            'question' => $question,
            'selectedAnswerId' => $answer->id,
            'isCorrect' => $isCorrect,
            'currentIndex' => $state['index'],
            'total' => count($state['question_ids']),
            'finished' => $state['finished'],
            'score' => $state['score'],
            'showUrl' => $showUrl,
            'submitUrl' => $submitUrl,
            'advanceUrl' => route('quiz.play.advance', ['uuid' => $quiz->uuid]),
            'resetUrl' => route('quiz.play.reset', ['uuid' => $quiz->uuid]),
            'uuid' => $quiz->uuid,
        ]);
    }
    public function advance(Request $request, string $uuid)
    {
        $quiz = Quiz::where('uuid', $uuid)->firstOrFail();

        // Only dispatch an event; do not mutate state or redirect
        event(new QuizProgressUpdated($quiz->uuid, 0, false, 0, 'advance'));

        return back();
    }

    public function reset(Request $request, string $uuid)
    {
        $quiz = Quiz::where('uuid', $uuid)->firstOrFail();

        // Only dispatch an event; do not mutate state or redirect
        event(new QuizProgressUpdated($quiz->uuid, 0, false, 0, 'reset'));

        return back();
    }
}
