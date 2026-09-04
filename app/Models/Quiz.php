<?php

namespace App\Models;

use App\Enums\QuizStatus;
use App\Support\QrCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Quiz extends Model
{
    use HasFactory;

    public const ANSWER_DURATION_SECONDS = 20;

    protected $fillable = [
        'title',
        'description',
        'published_at',
        'uuid',
        'status',
        'current_question_index',
        'question_started_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'status' => QuizStatus::class,
        'current_question_index' => 'integer',
        'question_started_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function displays(): MorphMany
    {
        return $this->morphMany(Display::class, 'displayable');
    }

    public function currentQuestion(): ?Question
    {
        if ($this->status !== QuizStatus::Live) {
            return null;
        }

        return $this->questions()
            ->with(['answers' => fn ($q) => $q->orderBy('order')])
            ->skip($this->current_question_index)
            ->first();
    }

    public function answerClosesAt(): ?Carbon
    {
        if ($this->status !== QuizStatus::Live || $this->question_started_at === null) {
            return null;
        }

        return $this->question_started_at->copy()->addSeconds(self::ANSWER_DURATION_SECONDS);
    }

    public function isAnswerWindowOpen(): bool
    {
        $closesAt = $this->answerClosesAt();

        return $closesAt !== null && ! now()->greaterThan($closesAt);
    }

    /**
     * @return list<int>
     */
    public function revealedCorrectAnswerIds(?Question $question = null): array
    {
        if ($this->isAnswerWindowOpen()) {
            return [];
        }

        $question ??= $this->currentQuestion();

        if (! $question) {
            return [];
        }

        $question->loadMissing('answers');

        return $question->answers
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, url: string}>
     */
    public function attachedDisplaysPayload(): array
    {
        return $this->displays()
            ->orderBy('name')
            ->get()
            ->map(fn (Display $display) => $display->publicSummary())
            ->values()
            ->all();
    }

    public function joinUrl(): string
    {
        return route('quiz.join', ['uuid' => $this->uuid]);
    }

    public function participateUrl(): string
    {
        return route('quiz.participate', ['uuid' => $this->uuid]);
    }

    public function joinQrSvg(): string
    {
        return QrCodeGenerator::svg($this->joinUrl());
    }

    /**
     * @return array{id: int, text: string, order: int, answers: array<int, array{id: int, text: string, order: int}>}|null
     */
    public function publicQuestionPayload(?Question $question): ?array
    {
        if (! $question) {
            return null;
        }

        $question->loadMissing(['answers' => fn ($q) => $q->orderBy('order')]);

        return [
            'id' => $question->id,
            'text' => $question->text,
            'order' => $question->order,
            'answers' => $question->answers->map(fn (Answer $answer) => [
                'id' => $answer->id,
                'text' => $answer->text,
                'order' => $answer->order,
            ])->values()->all(),
        ];
    }

    /**
     * Running score for the participant header. Only settled questions count, so
     * answering the current question does not reveal whether it was correct.
     *
     * @return array{current: int, total: int}
     */
    public function participantScore(Participant $participant): array
    {
        $questions = $this->recapQuestions();
        $total = $questions->count();

        if ($this->status === QuizStatus::Waiting || $total === 0) {
            return ['current' => 0, 'total' => $total];
        }

        $settledCount = match ($this->status) {
            QuizStatus::Waiting => 0,
            QuizStatus::Finished => $total,
            QuizStatus::Live => $this->current_question_index + ($this->isAnswerWindowOpen() ? 0 : 1),
        };

        $answersByQuestionId = $participant->answers()
            ->get(['question_id', 'answer_id'])
            ->keyBy('question_id');

        $current = $questions
            ->take(max(0, min($total, $settledCount)))
            ->filter(function (Question $question) use ($answersByQuestionId) {
                $correctIds = $question->answers
                    ->where('is_correct', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $selectedId = $answersByQuestionId->get($question->id)?->answer_id;

                return $selectedId !== null && in_array((int) $selectedId, $correctIds, true);
            })
            ->count();

        return [
            'current' => $current,
            'total' => $total,
        ];
    }

    /**
     * @return array{score: int, total: int, questions: list<array{id: int, text: string, selectedAnswerId: int|null, selectedText: string|null, correctAnswerIds: list<int>, correctTexts: list<string>, isCorrect: bool}>}|null
     */
    public function participantRecap(Participant $participant): ?array
    {
        if ($this->status !== QuizStatus::Finished) {
            return null;
        }

        $questions = $this->recapQuestions();
        $answersByQuestionId = $participant->answers()
            ->get(['question_id', 'answer_id'])
            ->keyBy('question_id');

        $items = $questions->map(function (Question $question) use ($answersByQuestionId) {
            $correctAnswers = $question->answers->where('is_correct', true)->values();
            $correctIds = $correctAnswers->pluck('id')->map(fn ($id) => (int) $id)->all();
            $selectedId = $answersByQuestionId->get($question->id)?->answer_id;
            $selectedId = $selectedId !== null ? (int) $selectedId : null;
            $selected = $selectedId !== null
                ? $question->answers->firstWhere('id', $selectedId)
                : null;

            return [
                'id' => $question->id,
                'text' => $question->text,
                'selectedAnswerId' => $selectedId,
                'selectedText' => $selected?->text,
                'correctAnswerIds' => $correctIds,
                'correctTexts' => $correctAnswers->pluck('text')->values()->all(),
                'isCorrect' => $selectedId !== null && in_array($selectedId, $correctIds, true),
            ];
        })->values()->all();

        return [
            'score' => collect($items)->where('isCorrect', true)->count(),
            'total' => count($items),
            'questions' => $items,
        ];
    }

    /**
     * @return array{total: int, questions: list<array{id: int, text: string, correctAnswerIds: list<int>, correctTexts: list<string>}>}|null
     */
    public function publicRecap(): ?array
    {
        if ($this->status !== QuizStatus::Finished) {
            return null;
        }

        $items = $this->recapQuestions()->map(function (Question $question) {
            $correctAnswers = $question->answers->where('is_correct', true)->values();

            return [
                'id' => $question->id,
                'text' => $question->text,
                'correctAnswerIds' => $correctAnswers->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'correctTexts' => $correctAnswers->pluck('text')->values()->all(),
            ];
        })->values()->all();

        return [
            'total' => count($items),
            'questions' => $items,
        ];
    }

    /**
     * Host console roster with running scores.
     *
     * @return list<array{id: int, nickname: string, created_at: string|null, score: int, score_total: int}>
     */
    public function consoleParticipantsPayload(): array
    {
        $questions = $this->recapQuestions();
        $total = $questions->count();

        $settledCount = match ($this->status) {
            QuizStatus::Waiting => 0,
            QuizStatus::Finished => $total,
            QuizStatus::Live => $this->current_question_index + ($this->isAnswerWindowOpen() ? 0 : 1),
        };
        $settledCount = max(0, min($total, $settledCount));
        $settledQuestions = $questions->take($settledCount);

        $correctByQuestionId = $settledQuestions->mapWithKeys(
            fn (Question $question) => [
                $question->id => $question->answers
                    ->where('is_correct', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            ]
        );

        return $this->participants()
            ->with(['answers:id,participant_id,question_id,answer_id'])
            ->orderBy('created_at')
            ->get()
            ->map(function (Participant $participant) use ($total, $settledQuestions, $correctByQuestionId) {
                $answersByQuestionId = $participant->answers->keyBy('question_id');
                $score = 0;

                foreach ($settledQuestions as $question) {
                    $selectedId = $answersByQuestionId->get($question->id)?->answer_id;
                    $correctIds = $correctByQuestionId[$question->id] ?? [];

                    if ($selectedId !== null && in_array((int) $selectedId, $correctIds, true)) {
                        $score++;
                    }
                }

                return [
                    'id' => $participant->id,
                    'nickname' => $participant->nickname,
                    'created_at' => $participant->created_at?->toIso8601String(),
                    'score' => $score,
                    'score_total' => $total,
                ];
            })
            ->sortBy([
                ['score', 'desc'],
                ['nickname', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Question>
     */
    protected function recapQuestions()
    {
        return $this->questions()
            ->with(['answers' => fn ($q) => $q->orderBy('order')])
            ->orderBy('order')
            ->get();
    }
}
