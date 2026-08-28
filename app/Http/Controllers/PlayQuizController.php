<?php

namespace App\Http\Controllers;

use App\Enums\QuizStatus;
use App\Events\QuizProgressUpdated;
use App\Models\ParticipantAnswer;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayQuizController extends Controller
{
    public function show(string $uuid): Response
    {
        $quiz = Quiz::where('uuid', $uuid)->firstOrFail();

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
            'title' => $quiz->title,
            'status' => $quiz->status->value,
            'currentIndex' => $quiz->current_question_index,
            'joinUrl' => $quiz->joinUrl(),
            'qrSvg' => $quiz->joinQrSvg(),
            'participantCount' => $quiz->participants()->count(),
        ]);
    }

    public function advance(Request $request, string $uuid): RedirectResponse
    {
        $quiz = $this->ownedQuiz($request, $uuid);
        $total = $quiz->questions()->count();

        abort_if($total === 0, 404, 'Aucune question disponible pour ce quiz.');

        if ($quiz->status === QuizStatus::Waiting) {
            $quiz->status = QuizStatus::Live;
            $quiz->current_question_index = 0;
        } elseif ($quiz->status === QuizStatus::Live) {
            if ($quiz->current_question_index >= $total - 1) {
                $quiz->status = QuizStatus::Finished;
            } else {
                $quiz->current_question_index++;
            }
        }

        $quiz->save();

        $this->broadcastProgress($quiz, $total, 'advance');

        return back();
    }

    public function reset(Request $request, string $uuid): RedirectResponse
    {
        $quiz = $this->ownedQuiz($request, $uuid);

        $quiz->status = QuizStatus::Waiting;
        $quiz->current_question_index = 0;
        $quiz->save();

        ParticipantAnswer::query()
            ->whereIn('participant_id', $quiz->participants()->select('id'))
            ->delete();

        $this->broadcastProgress($quiz, $quiz->questions()->count(), 'reset');

        return back();
    }

    protected function ownedQuiz(Request $request, string $uuid): Quiz
    {
        $quiz = Quiz::where('uuid', $uuid)->firstOrFail();

        abort_unless($request->user()?->id === $quiz->user_id, 403);

        return $quiz;
    }

    protected function broadcastProgress(Quiz $quiz, int $total, string $action): void
    {
        event(new QuizProgressUpdated(
            $quiz->uuid,
            $quiz->current_question_index,
            $quiz->status === QuizStatus::Finished,
            $total,
            $action,
            $quiz->status->value,
        ));
    }
}
