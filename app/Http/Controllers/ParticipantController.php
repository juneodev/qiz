<?php

namespace App\Http\Controllers;

use App\Enums\QuizStatus;
use App\Events\ParticipantJoined;
use App\Models\Answer;
use App\Models\Participant;
use App\Models\Quiz;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class ParticipantController extends Controller
{
    public function join(Request $request, string $uuid): Response|RedirectResponse
    {
        $quiz = $this->findQuiz($uuid);
        $participant = $this->participantFromRequest($request, $quiz);

        if ($participant) {
            return redirect()->route('quiz.participate', ['uuid' => $quiz->uuid]);
        }

        return Inertia::render('Quiz/Join', [
            'quiz' => [
                'uuid' => $quiz->uuid,
                'title' => $quiz->title,
                'description' => $quiz->description,
            ],
            'joinUrl' => $quiz->joinUrl(),
        ]);
    }

    public function store(Request $request, string $uuid): RedirectResponse
    {
        $quiz = $this->findQuiz($uuid);
        $existing = $this->participantFromRequest($request, $quiz);

        if ($existing) {
            return redirect()->route('quiz.participate', ['uuid' => $quiz->uuid]);
        }

        $nickname = trim((string) $request->input('nickname', ''));
        $request->merge(['nickname' => $nickname]);

        $request->validate([
            'nickname' => ['required', 'string', 'min:2', 'max:24'],
        ]);

        $taken = Participant::query()
            ->where('quiz_id', $quiz->id)
            ->whereRaw('LOWER(nickname) = ?', [mb_strtolower($nickname)])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'nickname' => 'Ce pseudo est déjà pris pour ce quiz.',
            ]);
        }

        $participant = $quiz->participants()->create([
            'nickname' => $nickname,
        ]);

        event(new ParticipantJoined(
            $quiz->uuid,
            $participant->id,
            $participant->nickname,
            $quiz->participants()->count(),
        ));

        return redirect()
            ->route('quiz.participate', ['uuid' => $quiz->uuid])
            ->cookie($this->participantCookie($participant->token));
    }

    public function play(Request $request, string $uuid): Response|RedirectResponse
    {
        $quiz = $this->findQuiz($uuid);
        $participant = $this->participantFromRequest($request, $quiz);

        if (! $participant) {
            return redirect()->route('quiz.join', ['uuid' => $quiz->uuid]);
        }

        $total = $quiz->questions()->count();
        $currentQuestion = $quiz->currentQuestion();
        $hasAnswered = false;
        $selectedAnswerId = null;

        if ($currentQuestion) {
            $existingAnswer = $participant->answers()
                ->where('question_id', $currentQuestion->id)
                ->first();

            $hasAnswered = (bool) $existingAnswer;
            $selectedAnswerId = $existingAnswer?->answer_id;
        }

        return Inertia::render('Quiz/Participate', [
            'quiz' => [
                'uuid' => $quiz->uuid,
                'title' => $quiz->title,
                'status' => $quiz->status->value,
                'currentIndex' => $quiz->current_question_index,
                'total' => $total,
            ],
            'participant' => [
                'id' => $participant->id,
                'nickname' => $participant->nickname,
            ],
            'question' => $quiz->publicQuestionPayload($currentQuestion),
            'hasAnswered' => $hasAnswered,
            'selectedAnswerId' => $selectedAnswerId,
            'correctAnswerIds' => $quiz->revealedCorrectAnswerIds($currentQuestion),
            'answerClosesAt' => $quiz->answerClosesAt()?->toIso8601String(),
            'answerDurationSeconds' => Quiz::ANSWER_DURATION_SECONDS,
            'recap' => $quiz->participantRecap($participant),
            'score' => $quiz->participantScore($participant),
            'submitUrl' => route('quiz.answers.store', ['uuid' => $quiz->uuid]),
        ]);
    }

    public function storeAnswer(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $quiz = $this->findQuiz($uuid);
        $participant = $this->participantFromRequest($request, $quiz);

        if (! $participant) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('quiz.join', ['uuid' => $quiz->uuid]);
        }

        if ($quiz->status !== QuizStatus::Live) {
            throw ValidationException::withMessages([
                'answer_id' => 'Le quiz n\'est pas en cours.',
            ]);
        }

        $currentQuestion = $quiz->currentQuestion();

        if (! $currentQuestion) {
            throw ValidationException::withMessages([
                'answer_id' => 'Aucune question en cours.',
            ]);
        }

        if (! $quiz->isAnswerWindowOpen()) {
            throw ValidationException::withMessages([
                'answer_id' => 'Le temps est écoulé.',
            ]);
        }

        $data = $request->validate([
            'answer_id' => ['required', 'integer', 'exists:answers,id'],
        ]);

        $answer = Answer::query()->findOrFail($data['answer_id']);

        if ($answer->question_id !== $currentQuestion->id) {
            throw ValidationException::withMessages([
                'answer_id' => 'Réponse invalide pour cette question.',
            ]);
        }

        $existing = $participant->answers()
            ->where('question_id', $currentQuestion->id)
            ->first();

        if ($existing) {
            if ($existing->answer_id === $answer->id) {
                return $this->answerStoredResponse($request, $answer->id);
            }

            throw ValidationException::withMessages([
                'answer_id' => 'Vous avez déjà répondu à cette question.',
            ]);
        }

        try {
            $participant->answers()->create([
                'question_id' => $currentQuestion->id,
                'answer_id' => $answer->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = $participant->answers()
                ->where('question_id', $currentQuestion->id)
                ->first();

            if ($existing && $existing->answer_id === $answer->id) {
                return $this->answerStoredResponse($request, $answer->id);
            }

            throw ValidationException::withMessages([
                'answer_id' => 'Vous avez déjà répondu à cette question.',
            ]);
        }

        return $this->answerStoredResponse($request, $answer->id);
    }

    protected function answerStoredResponse(Request $request, int $answerId): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'answer_id' => $answerId,
            ]);
        }

        return back();
    }

    protected function findQuiz(string $uuid): Quiz
    {
        return Quiz::where('uuid', $uuid)->firstOrFail();
    }

    protected function participantFromRequest(Request $request, Quiz $quiz): ?Participant
    {
        $token = $request->cookie(Participant::COOKIE);

        if (! $token) {
            return null;
        }

        return Participant::query()
            ->where('quiz_id', $quiz->id)
            ->where('token', $token)
            ->first();
    }

    protected function participantCookie(string $token): SymfonyCookie
    {
        return Cookie::make(
            name: Participant::COOKIE,
            value: $token,
            minutes: 60 * 24,
            path: '/',
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
