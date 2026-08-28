<?php

namespace App\Http\Controllers;

use App\Enums\QuizStatus;
use App\Events\QuizProgressUpdated;
use App\Models\ParticipantAnswer;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlayQuizController extends Controller
{
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
