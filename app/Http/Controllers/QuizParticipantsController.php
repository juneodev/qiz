<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuizParticipantsController extends Controller
{
    public function index(Request $request, Quiz $quiz): Response
    {
        abort_if($quiz->user_id !== $request->user()->id, 404);

        $participants = $quiz->participants()
            ->orderBy('created_at')
            ->get(['id', 'nickname', 'created_at'])
            ->map(fn ($participant) => [
                'id' => $participant->id,
                'nickname' => $participant->nickname,
                'created_at' => $participant->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Quizzes/Participants', [
            'quiz' => [
                'id' => $quiz->id,
                'uuid' => $quiz->uuid,
                'title' => $quiz->title,
                'console_url' => route('quizzes.console', ['quiz' => $quiz->id]),
                'edit_url' => route('quizzes.edit', ['quiz' => $quiz->id]),
            ],
            'participants' => $participants,
        ]);
    }
}
