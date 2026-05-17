<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_returns_token_with_valid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'john@example.com',
            'password' => bcrypt('Password1!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'john@example.com',
            'password' => 'Password1!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_rejects_wrong_credentials(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/login', [
            'email'    => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_logout_revokes_token(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/logout');

        $response->assertStatus(200);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson(['id' => $user->id, 'email' => $user->email]);
    }

    public function test_unauthenticated_access_to_protected_routes_returns_401(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
        $this->postJson('/api/logout')->assertStatus(401);
        $this->postJson('/api/email/verify', ['otp' => '123456'])->assertStatus(401);
    }

    public function test_unverified_user_blocked_from_verified_routes(): void
    {
        $user  = User::factory()->unverified()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/account');

        $response->assertStatus(403);
    }

    public function test_verified_user_can_access_verified_routes(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/account');

        $response->assertStatus(200)
            ->assertJson(['id' => $user->id, 'email' => $user->email]);
    }
}
