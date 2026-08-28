<?php

namespace Database\Factories;

use App\Enums\QuizStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'published_at' => now(),
            'status' => QuizStatus::Waiting,
            'current_question_index' => 0,
        ];
    }
}
