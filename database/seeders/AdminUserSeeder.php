<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@localhost');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => Hash::make((string) env('ADMIN_PASSWORD', 'ChangeMe123!')),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
