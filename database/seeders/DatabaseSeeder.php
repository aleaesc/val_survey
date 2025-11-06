<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Default test user (password: password)
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                // when using updateOrCreate, keep password hashed as factory default
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Admin user requested: username "aleaadmin", password "alea123"
        // We store the username in the 'name' field; email is arbitrary but unique.
        User::updateOrCreate(
            ['email' => 'aleaadmin@example.com'],
            [
                'name' => 'aleaadmin',
                'password' => Hash::make('alea123'),
                'email_verified_at' => now(),
            ]
        );
    }
}
