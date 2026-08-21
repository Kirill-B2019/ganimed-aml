<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $admin = \App\Models\User::query()->where('is_admin', true)->first();
        if ($admin) {
            app(\App\Services\Ops\DefaultScreeningCases::class)->ensureFor($admin);
        }
    }
}
