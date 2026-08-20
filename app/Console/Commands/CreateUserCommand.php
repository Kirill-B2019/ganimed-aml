<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create {name} {email} {password} {--admin}';

    protected $description = 'Create a login for the AML service';

    public function handle(): int
    {
        if (User::query()->where('email', $this->argument('email'))->exists()) {
            $this->error('A user with this email already exists.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($this->argument('password')),
            'is_admin' => (bool) $this->option('admin'),
            'email_verified_at' => now(),
        ]);

        $this->info('User created.');

        return self::SUCCESS;
    }
}
