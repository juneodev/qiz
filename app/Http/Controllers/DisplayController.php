<?php

namespace App\Http\Controllers;

use App\Models\Display;
use App\Models\Quiz;
use Inertia\Inertia;
use Inertia\Response;

class DisplayController extends Controller
{
    public function show(string $uuid): Response
    {
        $display = Display::query()
            ->where('uuid', $uuid)
            ->with('displayable')
            ->firstOrFail();

        return Inertia::render('Display/Show', [
            'display' => [
                'uuid' => $display->uuid,
                'name' => $display->name,
            ],
            'quiz' => $this->quizPayload($display->displayable),
        ]);
    }

    /**
     * @return array{
     *     uuid: string,
     *     title: string,
     *     status: string,
     *     currentIndex: int,
     *     questions: list<array{id: int, text: string, order: int, answers: list<array{id: int, text: string, order: int}>}>,
     *     total: int,
     *     joinUrl: string,
     *     qrSvg: string,
     *     participantCount: int,
     *     answerClosesAt: string|null,
     *     answerDurationSeconds: int,
     *     correctAnswerIds: list<int>,
     *     recap: array{total: int, questions: list<array{id: int, text: string, correctAnswerIds: list<int>, correctTexts: list<string>}>}|null
     * }|null
     */
    protected function quizPayload(mixed $displayable): ?array
    {
        if (! $displayable instanceof Quiz) {
            return null;
        }

        $questions = $displayable->questions()
            ->with(['answers' => fn ($q) => $q->orderBy('order')])
            ->get(['id', 'text', 'order'])
            ->map(fn ($question) => [
                'id' => $question->id,
                'text' => $question->text,
                'order' => $question->order,
                'answers' => $question->answers->map(fn ($answer) => [
                    'id' => $answer->id,
                    'text' => $answer->text,
                    'order' => $answer->order,
                ])->values()->all(),
            ])
            ->values();

        return [
            'uuid' => $displayable->uuid,
            'title' => $displayable->title,
            'status' => $displayable->status->value,
            'currentIndex' => $displayable->current_question_index,
            'questions' => $questions->all(),
            'total' => $questions->count(),
            'joinUrl' => $displayable->joinUrl(),
            'qrSvg' => $questions->isEmpty() ? '' : $displayable->joinQrSvg(),
            'participantCount' => $displayable->participants()->count(),
            'answerClosesAt' => $displayable->answerClosesAt()?->toIso8601String(),
            'answerDurationSeconds' => Quiz::ANSWER_DURATION_SECONDS,
            'correctAnswerIds' => $displayable->revealedCorrectAnswerIds(),
            'recap' => $displayable->publicRecap(),
        ];
    }
}
