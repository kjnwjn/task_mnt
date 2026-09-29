<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_endpoint_returns_no_content(): void
    {
        $this->getJson('/api/csrf')->assertNoContent();
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'user@example.com')
            ->assertJsonPath('data.role', 'user');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'user@example.com', 'password' => 'password']);

        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_validates_input(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        // Even without an Accept: application/json header, API routes answer with JSON 401 (no redirect).
        $this->get('/api/me')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_me_returns_current_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.name', $admin->name)
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_logout_ends_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_users_endpoint_lists_id_and_name_only(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        $this->actingAs($alice)->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertExactJson([
                ['id' => $alice->id, 'name' => 'Alice'],
                ['id' => User::where('name', 'Bob')->value('id'), 'name' => 'Bob'],
            ]);
    }

    public function test_users_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }
}
