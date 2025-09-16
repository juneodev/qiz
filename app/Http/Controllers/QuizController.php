<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function show()
    {
        // Fetch the first available question with answers (our seeded one)
        $question = Question::with(['answers' => function ($q) {
            $q->orderBy('order');
        }])->orderBy('order')->first();

        if (! $question) {
            abort(404, 'Aucune question disponible.');
        }

        return view('quiz.show', [
            'question' => $question,
        ]);
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'answer_id' => ['required', 'integer', 'exists:answers,id'],
        ]);

        $answer = Answer::with('question')->findOrFail($data['answer_id']);
        $question = $answer->question()->with(['answers' => function ($q) {
            $q->orderBy('order');
        }])->first();

        $isCorrect = (bool) $answer->is_correct;

        return view('quiz.show', [
            'question' => $question,
            'selectedAnswerId' => $answer->id,
            'isCorrect' => $isCorrect,
        ]);
    }
}
