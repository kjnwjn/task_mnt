<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The container runs `migrate --seed` on every start; seed only once.
        if (User::query()->exists()) {
            return;
        }

        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $user = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $jane = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        foreach ([$admin, $user, $jane] as $owner) {
            Task::factory()->count(10)->for($owner, 'assignee')->create();
        }
    }
}
