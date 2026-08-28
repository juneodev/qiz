<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Display extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'uuid',
        'user_id',
        'displayable_type',
        'displayable_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Display $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return route('displays.show', ['uuid' => $this->uuid]);
    }

    /**
     * @return array{id: int, name: string, url: string}
     */
    public function publicSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url(),
        ];
    }
}
