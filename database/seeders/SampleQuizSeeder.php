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

        // Create or update a simple quiz owned by the user
        $quiz = $user->quizzes()->firstOrCreate([
            'title' => 'Culture Générale (Démo)'
        ], [
            'description' => 'Un mini quiz de démonstration pour la page publique.'
        ]);

        // Define three simple questions with answers
        $questions = [
            [
                'text' => 'Quelle est la capitale de la France ?',
                'answers' => [
                    ['text' => 'Paris', 'is_correct' => true],
                    ['text' => 'Lyon', 'is_correct' => false],
                    ['text' => 'Marseille', 'is_correct' => false],
                    ['text' => 'Bordeaux', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Combien font 2 + 2 ?',
                'answers' => [
                    ['text' => '3', 'is_correct' => false],
                    ['text' => '4', 'is_correct' => true],
                    ['text' => '5', 'is_correct' => false],
                    ['text' => '22', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'De quelle couleur est le ciel par temps clair ?',
                'answers' => [
                    ['text' => 'Bleu', 'is_correct' => true],
                    ['text' => 'Vert', 'is_correct' => false],
                    ['text' => 'Rouge', 'is_correct' => false],
                    ['text' => 'Jaune', 'is_correct' => false],
                ],
            ],
        ];

        // Create or update questions and answers idempotently
        foreach ($questions as $idx => $qData) {
            $question = Question::firstOrCreate([
                'quiz_id' => $quiz->id,
                'text' => $qData['text'],
            ], [
                'order' => $idx + 1,
            ]);

            // Reset answers for idempotency
            Answer::where('question_id', $question->id)->delete();

            foreach ($qData['answers'] as $aIdx => $aData) {
                Answer::create([
                    'question_id' => $question->id,
                    'text' => $aData['text'],
                    'is_correct' => $aData['is_correct'],
                    'order' => $aIdx + 1,
                ]);
            }
        }
    }
}
