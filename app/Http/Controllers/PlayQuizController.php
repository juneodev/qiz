<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;

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
        $state = $this->bootSessionFor($quiz);
        $key = $this->getSessionKey($quiz);

        $showUrl = route('quiz.play.show', ['uuid' => $quiz->uuid]);
        $submitUrl = route('quiz.play.submit', ['uuid' => $quiz->uuid]);

        if ($request->boolean('next') && !$state['finished']) {
            $state['index']++;
            if ($state['index'] >= count($state['question_ids'])) {
                $state['finished'] = true;
                $state['index'] = count($state['question_ids']) - 1;
            }
            session([$key => $state]);
            return redirect()->to($showUrl);
        }

        if ($state['finished']) {
            return view('quiz.show', [
                'question' => Question::with('answers')->find($state['question_ids'][count($state['question_ids']) - 1]),
                'finished' => true,
                'score' => $state['score'],
                'total' => count($state['question_ids']),
                'currentIndex' => $state['index'],
                'showUrl' => $showUrl,
                'submitUrl' => $submitUrl,
            ]);
        }

        $currentQuestionId = $state['question_ids'][$state['index']];
        $question = Question::with(['answers' => function ($q) {
            $q->orderBy('order');
        }])->findOrFail($currentQuestionId);

        return view('quiz.show', [
            'question' => $question,
            'currentIndex' => $state['index'],
            'total' => count($state['question_ids']),
            'showUrl' => $showUrl,
            'submitUrl' => $submitUrl,
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

        return view('quiz.show', [
            'question' => $question,
            'selectedAnswerId' => $answer->id,
            'isCorrect' => $isCorrect,
            'currentIndex' => $state['index'],
            'total' => count($state['question_ids']),
            'finished' => $state['finished'],
            'score' => $state['score'],
            'showUrl' => $showUrl,
            'submitUrl' => $submitUrl,
        ]);
    }
}
