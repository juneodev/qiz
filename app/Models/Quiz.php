<?php

namespace App\Models;

use App\Enums\QuizStatus;
use App\Support\QrCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'published_at',
        'uuid',
        'status',
        'current_question_index',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'status' => QuizStatus::class,
        'current_question_index' => 'integer',
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

    public function playUrl(): string
    {
        return route('quiz.play.show', ['uuid' => $this->uuid]);
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
}
