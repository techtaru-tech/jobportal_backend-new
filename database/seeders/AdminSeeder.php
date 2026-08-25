<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Creates the first admin operator, so the panel can be signed into at all.
 *
 * Idempotent — matches on email and updates in place, like `DemoSeeder`, so
 * re-running it resets the password rather than failing on the unique index.
 *
 * Credentials come from the environment, with local-only defaults. The default
 * password is deliberately not a real-looking one: if this ever runs somewhere
 * it shouldn't, `change-me-locally` in a login form is a lot more obvious than
 * something that resembles a password somebody meant.
 *
 *   php artisan db:seed --class=AdminSeeder
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@inthes.local');
        $password = env('ADMIN_PASSWORD', 'change-me-locally');

        $admin = Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                // Hashed by the model's `password` => 'hashed' cast.
                'password' => $password,
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        $this->command?->info("Admin ready: {$admin->email}");

        if ($password === 'change-me-locally') {
            $this->command?->warn(
                'Using the default password. Set ADMIN_EMAIL / ADMIN_PASSWORD in .env before this is reachable by anyone else.',
            );
        }
    }
}
