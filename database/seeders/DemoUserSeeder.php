<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public const EMAIL = 'demo@qiz.test';

    public const PASSWORD = 'password';

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Hôte démo',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
            ],
        );
    }
}
