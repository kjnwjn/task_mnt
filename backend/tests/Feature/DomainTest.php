<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_belongs_to_assignee_and_user_has_many_tasks(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'assignee')->create();

        $this->assertTrue($task->assignee->is($user));
        $this->assertTrue($user->tasks->first()->is($task));
    }

    public function test_casts_and_defaults(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Write spec',
            'assigned_to' => $user->id,
            'due_date' => '2026-10-01',
        ])->fresh();

        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame('2026-10-01', $task->due_date->format('Y-m-d'));
        $this->assertSame($user->id, $task->assigned_to);
        $this->assertSame(Role::User, $user->fresh()->role);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue(User::factory()->admin()->create()->isAdmin());
    }

    public function test_deleting_user_cascades_to_their_tasks(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'assignee')->create();

        $user->delete();

        $this->assertModelMissing($task);
    }

    public function test_seeder_creates_documented_accounts_and_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(3, User::count());
        $this->assertSame(30, Task::count());

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('password', $admin->password));

        $user = User::where('email', 'user@example.com')->firstOrFail();
        $this->assertFalse($user->isAdmin());
        $this->assertTrue(Hash::check('password', $user->password));

        $this->assertTrue(User::where('email', 'jane@example.com')->exists());
    }
}
