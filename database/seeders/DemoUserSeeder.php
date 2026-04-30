<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'demo@campaigntracker.test'],
            [
                'name' => 'Campaign Manager',
                'password' => 'Password123!',
                'email_verified_at' => now(),
            ],
        );
    }
}
