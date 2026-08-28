<?php

namespace Database\Seeders;

use App\Models\Display;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class DisplaySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', DemoUserSeeder::EMAIL)->firstOrFail();
        $geographie = Quiz::query()->where('user_id', $user->id)->where('title', 'Géographie')->firstOrFail();

        $salle = Display::query()->create([
            'user_id' => $user->id,
            'name' => 'Salle principale',
        ]);
        $salle->displayable()->associate($geographie);
        $salle->save();

        Display::query()->create([
            'user_id' => $user->id,
            'name' => 'Bar',
        ]);
    }
}
