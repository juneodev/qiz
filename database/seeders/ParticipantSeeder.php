<?php

namespace Database\Seeders;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class ParticipantSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', DemoUserSeeder::EMAIL)->firstOrFail();
        $quiz = Quiz::query()->where('user_id', $user->id)->where('title', 'Géographie')->firstOrFail();

        foreach (['Alex', 'Sam', 'Léa', 'Hugo'] as $nickname) {
            $quiz->participants()->create([
                'nickname' => $nickname,
            ]);
        }
    }
}
