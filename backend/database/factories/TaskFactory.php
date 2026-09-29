<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->sentence(4), '.'),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(TaskStatus::cases()),
            'assigned_to' => User::factory(),
            'due_date' => fake()->optional()->dateTimeBetween('-1 week', '+1 month')?->format('Y-m-d'),
        ];
    }
}
