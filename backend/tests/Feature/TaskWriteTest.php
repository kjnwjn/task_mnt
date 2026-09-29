<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create(): void
    {
        $this->postJson('/api/tasks', ['title' => 'x'])->assertUnauthorized();
    }

    public function test_user_creates_task_assigned_to_self_with_default_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/tasks', [
            'title' => 'Write tests',
            'description' => 'Cover the policy',
            'due_date' => '2026-10-15',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Write tests')
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.due_date', '2026-10-15')
            ->assertJsonPath('data.assigned_to', $user->id)
            ->assertJsonPath('data.assignee.name', $user->name);
    }

    public function test_user_cannot_create_task_for_someone_else(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->postJson('/api/tasks', [
            'title' => 'Sneaky',
            'assigned_to' => $other->id,
        ])->assertCreated()->assertJsonPath('data.assigned_to', $user->id);

        $this->assertDatabaseHas('tasks', ['title' => 'Sneaky', 'assigned_to' => $user->id]);
        $this->assertDatabaseMissing('tasks', ['assigned_to' => $other->id]);
    }

    public function test_admin_can_create_task_for_anyone(): void
    {
        $admin = User::factory()->admin()->create();
        $jane = User::factory()->create();

        $this->actingAs($admin)->postJson('/api/tasks', [
            'title' => 'For Jane',
            'status' => 'in_progress',
            'assigned_to' => $jane->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.assigned_to', $jane->id)
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_admin_without_assignee_defaults_to_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/tasks', ['title' => 'Mine'])
            ->assertCreated()
            ->assertJsonPath('data.assigned_to', $admin->id);
    }

    public function test_create_validation_errors(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/tasks', [
            'title' => '',
            'status' => 'bogus',
            'due_date' => 'not-a-date',
            'assigned_to' => 999999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'status', 'due_date', 'assigned_to']);
    }

    public function test_user_updates_own_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'assignee')->create(['status' => TaskStatus::Todo]);

        $this->actingAs($user)->putJson("/api/tasks/{$task->id}", [
            'title' => 'Renamed',
            'status' => 'done',
            'due_date' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.due_date', null);
    }

    public function test_user_cannot_reassign_own_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($user, 'assignee')->create();

        $this->actingAs($user)->putJson("/api/tasks/{$task->id}", ['assigned_to' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $user->id);
    }

    public function test_user_cannot_update_other_users_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        // 403 wins over validation: authorization runs before rules.
        $this->actingAs($user)->putJson("/api/tasks/{$task->id}", ['title' => ''])
            ->assertForbidden();
    }

    public function test_admin_can_update_and_reassign_any_task(): void
    {
        $admin = User::factory()->admin()->create();
        $jane = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($admin)->putJson("/api/tasks/{$task->id}", ['assigned_to' => $jane->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $jane->id);
    }

    public function test_update_validation_errors(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'assignee')->create();

        $this->actingAs($user)->putJson("/api/tasks/{$task->id}", ['title' => '', 'status' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'status']);
    }

    public function test_update_missing_task_returns_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->putJson('/api/tasks/999999', ['title' => 'x'])->assertNotFound();
    }

    public function test_user_deletes_own_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'assignee')->create();

        $this->actingAs($user)->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertModelMissing($task);
    }

    public function test_user_cannot_delete_other_users_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($user)->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

        $this->assertModelExists($task);
    }

    public function test_admin_can_delete_any_task(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertModelMissing($task);
    }

    public function test_delete_missing_task_returns_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->deleteJson('/api/tasks/999999')->assertNotFound();
    }
}
