<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/tasks')->assertUnauthorized();
    }

    public function test_admin_sees_all_tasks_with_resource_and_pagination_shape(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->count(3)->create();
        Task::factory()->for($admin, 'assignee')->create();

        $this->actingAs($admin)->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'title', 'description', 'status', 'due_date',
                    'assigned_to', 'assignee' => ['id', 'name'], 'created_at', 'updated_at',
                ]],
                'links',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_user_sees_only_own_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Task::factory()->count(2)->for($user, 'assignee')->create();
        Task::factory()->count(3)->for($other, 'assignee')->create();

        $response = $this->actingAs($user)->getJson('/api/tasks')->assertOk();

        $response->assertJsonPath('meta.total', 2);
        $this->assertSame([$user->id], collect($response->json('data'))->pluck('assigned_to')->unique()->values()->all());
    }

    public function test_user_cannot_see_other_users_tasks_via_assigned_to_filter(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Task::factory()->count(3)->for($other, 'assignee')->create();

        $this->actingAs($user)->getJson("/api/tasks?assigned_to={$other->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_filter_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->count(2)->create(['status' => TaskStatus::Todo]);
        Task::factory()->create(['status' => TaskStatus::Done]);

        $this->actingAs($admin)->getJson('/api/tasks?status=done')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'done');
    }

    public function test_filter_by_assigned_to_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $jane = User::factory()->create();
        Task::factory()->count(2)->for($jane, 'assignee')->create();
        Task::factory()->count(3)->create();

        $this->actingAs($admin)->getJson("/api/tasks?assigned_to={$jane->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.assignee.id', $jane->id);
    }

    public function test_search_by_title(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->create(['title' => 'Fix login bug']);
        Task::factory()->create(['title' => 'Write docs']);

        $this->actingAs($admin)->getJson('/api/tasks?search=login')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Fix login bug');
    }

    public function test_server_side_pagination(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->count(15)->create();

        $this->actingAs($admin)->getJson('/api/tasks?per_page=10&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_invalid_query_params_return_422(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/tasks?status=bogus&per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'per_page']);
    }

    public function test_user_can_view_own_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'assignee')->create();

        $this->actingAs($user)->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.assignee.name', $user->name);
    }

    public function test_user_cannot_view_other_users_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($user)->getJson("/api/tasks/{$task->id}")->assertForbidden();
    }

    public function test_admin_can_view_any_task(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create();

        $this->actingAs($admin)->getJson("/api/tasks/{$task->id}")->assertOk();
    }

    public function test_missing_task_returns_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/tasks/999999')->assertNotFound();
    }
}
