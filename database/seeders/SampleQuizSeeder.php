<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleQuizSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => 'password', // Will be hashed by User casts
            ]);
        }

        foreach ($this->quizzes() as $quizData) {
            $quiz = $user->quizzes()->firstOrCreate(
                ['title' => $quizData['title']],
                [
                    'description' => $quizData['description'],
                    'published_at' => now(),
                ]
            );

            if ($quiz->published_at === null) {
                $quiz->update(['published_at' => now()]);
            }

            foreach ($quizData['questions'] as $idx => $qData) {
                $question = Question::firstOrCreate(
                    [
                        'quiz_id' => $quiz->id,
                        'text' => $qData['text'],
                    ],
                    [
                        'order' => $idx + 1,
                    ]
                );

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

    /**
     * @return list<array{title: string, description: string, questions: list<array{text: string, answers: list<array{text: string, is_correct: bool}>}>}>
     */
    private function quizzes(): array
    {
        return [
            [
                'title' => 'Géographie',
                'description' => 'Un quiz sur les capitales, les pays et les continents.',
                'questions' => [
                    [
                        'text' => 'Quelle est la capitale de l’Australie ?',
                        'answers' => [
                            ['text' => 'Sydney', 'is_correct' => false],
                            ['text' => 'Melbourne', 'is_correct' => false],
                            ['text' => 'Canberra', 'is_correct' => true],
                            ['text' => 'Perth', 'is_correct' => false],
                        ],
                    ],
                    [
                        'text' => 'Quel est le plus grand océan du monde ?',
                        'answers' => [
                            ['text' => 'Océan Atlantique', 'is_correct' => false],
                            ['text' => 'Océan Pacifique', 'is_correct' => true],
                            ['text' => 'Océan Indien', 'is_correct' => false],
                            ['text' => 'Océan Arctique', 'is_correct' => false],
                        ],
                    ],
                    [
                        'text' => 'Dans quel pays se trouve le Machu Picchu ?',
                        'answers' => [
                            ['text' => 'Bolivie', 'is_correct' => false],
                            ['text' => 'Chili', 'is_correct' => false],
                            ['text' => 'Mexique', 'is_correct' => false],
                            ['text' => 'Pérou', 'is_correct' => true],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Cinéma',
                'description' => 'Un quiz sur les films, les réalisateurs et les récompenses.',
                'questions' => [
                    [
                        'text' => 'Qui a réalisé le film Inception ?',
                        'answers' => [
                            ['text' => 'Steven Spielberg', 'is_correct' => false],
                            ['text' => 'Christopher Nolan', 'is_correct' => true],
                            ['text' => 'James Cameron', 'is_correct' => false],
                            ['text' => 'Denis Villeneuve', 'is_correct' => false],
                        ],
                    ],
                    [
                        'text' => 'Quel film a remporté l’Oscar du meilleur film en 2020 ?',
                        'answers' => [
                            ['text' => '1917', 'is_correct' => false],
                            ['text' => 'Joker', 'is_correct' => false],
                            ['text' => 'Parasite', 'is_correct' => true],
                            ['text' => 'Once Upon a Time in Hollywood', 'is_correct' => false],
                        ],
                    ],
                    [
                        'text' => 'Dans quel film entend-on la réplique « Je suis ton père » ?',
                        'answers' => [
                            ['text' => 'Star Wars : L’Empire contre-attaque', 'is_correct' => true],
                            ['text' => 'Le Seigneur des anneaux', 'is_correct' => false],
                            ['text' => 'Harry Potter à l’école des sorciers', 'is_correct' => false],
                            ['text' => 'Matrix', 'is_correct' => false],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Sciences',
                'description' => 'Un quiz sur quelques notions scientifiques essentielles.',
                'questions' => [
                    [
                        'text' => 'Quel est le symbole chimique de l’or ?',
                        'answers' => [
                            ['text' => 'Ag', 'is_correct' => false],
                            ['text' => 'Fe', 'is_correct' => false],
                            ['text' => 'Au', 'is_correct' => true],
                            ['text' => 'Or', 'is_correct' => false],
                        ],
                    ],
                    [
                        'text' => 'Combien de planètes compte le système solaire ?',
                        'answers' => [
                            ['text' => '7', 'is_correct' => false],
                            ['text' => '8', 'is_correct' => true],
                            ['text' => '9', 'is_correct' => false],
                            ['text' => '10', 'is_correct' => false],
                        ],
                    ],
                    [
                        'text' => 'Quelle est l’unité de mesure de la force dans le Système international ?',
                        'answers' => [
                            ['text' => 'Joule', 'is_correct' => false],
                            ['text' => 'Watt', 'is_correct' => false],
                            ['text' => 'Pascal', 'is_correct' => false],
                            ['text' => 'Newton', 'is_correct' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}
