<?php

namespace Database\Seeders;

use App\Models\Display;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Compte hôte : demo@qiz.test / password
     */
    public function run(): void
    {
        $this->call([
            DemoUserSeeder::class,
            QuizSeeder::class,
            DisplaySeeder::class,
            ParticipantSeeder::class,
        ]);

        $this->printDemoHints();
    }

    protected function printDemoHints(): void
    {
        if (! $this->command) {
            return;
        }

        $display = Display::query()->where('name', 'Salle principale')->first();

        $this->command->newLine();
        $this->command->info('Compte hôte : '.DemoUserSeeder::EMAIL.' / '.DemoUserSeeder::PASSWORD);

        if ($display) {
            $this->command->info('Affichage salle : '.$display->url());
        }
    }
}
