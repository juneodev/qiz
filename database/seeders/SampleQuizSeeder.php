<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleQuizSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure there is at least one user to own the demo quiz
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => 'password', // Will be hashed by User casts
            ]);
        }

        // Create or update a simple quiz with one question and four answers, owned by the user
        $quiz = $user->quizzes()->firstOrCreate([
            'title' => 'Culture Générale (Démo)'
        ], [
            'description' => 'Un mini quiz de démonstration pour la page publique.'
        ]);

        // Ensure a single demo question exists
        $question = Question::firstOrCreate([
            'quiz_id' => $quiz->id,
            'text' => 'Quelle est la capitale de la France ?'
        ], [
            'order' => 1,
        ]);

        // Clear previous answers for idempotency
        Answer::where('question_id', $question->id)->delete();

        // Create four answers, one correct
        $answers = [
            ['text' => 'Paris', 'is_correct' => true, 'order' => 1],
            ['text' => 'Lyon', 'is_correct' => false, 'order' => 2],
            ['text' => 'Marseille', 'is_correct' => false, 'order' => 3],
            ['text' => 'Bordeaux', 'is_correct' => false, 'order' => 4],
        ];

        foreach ($answers as $a) {
            Answer::create([
                'question_id' => $question->id,
                'text' => $a['text'],
                'is_correct' => $a['is_correct'],
                'order' => $a['order'],
            ]);
        }
    }
}
