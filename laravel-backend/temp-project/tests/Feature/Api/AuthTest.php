<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\RetailStore;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::resolveRelationUsing('retailStore', function ($user) {
            return $user->belongsTo(RetailStore::class, 'warehouse_id', 'warehouse_id');
        });
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $password = 'correct-password';
        $user = new User;
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = Hash::make($password);
        $user->is_active = true;
        $user->save();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => $password,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'name', 'email'], 'token'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Login successful']);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = new User;
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = Hash::make('correct-password');
        $user->is_active = true;
        $user->save();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['message' => 'Invalid credentials']);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $password = 'correct-password';
        $user = new User;
        $user->name = 'Deactivated';
        $user->email = 'deactivated@example.com';
        $user->password = Hash::make($password);
        $user->is_active = false;
        $user->save();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'deactivated@example.com',
            'password' => $password,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Account is deactivated']);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '1234567890',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'name', 'email', 'role'], 'token'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Registration successful']);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
        ]);
    }

    public function test_registration_validates_unique_email(): void
    {
        $user = new User;
        $user->name = 'Existing';
        $user->email = 'existing@example.com';
        $user->password = Hash::make('password');
        $user->save();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_password_min_eight(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = new User;
        $user->name = 'Logout User';
        $user->email = 'logout@example.com';
        $user->password = Hash::make('password');
        $user->is_active = true;
        $user->save();

        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Logged out successfully']);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_their_profile(): void
    {
        $warehouse = new Warehouse;
        $warehouse->name = 'Test Warehouse';
        $warehouse->code = 'WH-TEST';
        $warehouse->is_active = true;
        $warehouse->save();

        $user = new User;
        $user->name = 'Profile User';
        $user->email = 'profile@example.com';
        $user->password = Hash::make('password');
        $user->warehouse_id = $warehouse->id;
        $user->is_active = true;
        $user->save();

        $response = $this->actingAs($user)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Profile retrieved'])
            ->assertJsonFragment(['id' => $user->id]);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $warehouse = new Warehouse;
        $warehouse->name = 'Test Warehouse';
        $warehouse->code = 'WH-UPDATE';
        $warehouse->is_active = true;
        $warehouse->save();

        $user = new User;
        $user->name = 'Original Name';
        $user->email = 'original@example.com';
        $user->password = Hash::make('password');
        $user->warehouse_id = $warehouse->id;
        $user->save();

        $response = $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'phone' => '9876543210',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Profile updated successfully'])
            ->assertJsonFragment(['name' => 'Updated Name'])
            ->assertJsonFragment(['email' => 'updated@example.com']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_update_profile_validates_unique_email_except_own(): void
    {
        $warehouse = new Warehouse;
        $warehouse->name = 'Test Warehouse';
        $warehouse->code = 'WH-OWN';
        $warehouse->is_active = true;
        $warehouse->save();

        $user = new User;
        $user->name = 'Current User';
        $user->email = 'current@example.com';
        $user->password = Hash::make('password');
        $user->warehouse_id = $warehouse->id;
        $user->save();

        $response = $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => 'Current User',
                'email' => 'current@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Profile updated successfully']);
    }

    public function test_update_profile_fails_with_duplicate_email(): void
    {
        $warehouse = new Warehouse;
        $warehouse->name = 'Test Warehouse';
        $warehouse->code = 'WH-DUP';
        $warehouse->is_active = true;
        $warehouse->save();

        $otherUser = new User;
        $otherUser->name = 'Other';
        $otherUser->email = 'other@example.com';
        $otherUser->password = Hash::make('password');
        $otherUser->warehouse_id = $warehouse->id;
        $otherUser->save();

        $user = new User;
        $user->name = 'Current User';
        $user->email = 'current@example.com';
        $user->password = Hash::make('password');
        $user->warehouse_id = $warehouse->id;
        $user->save();

        $response = $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => 'Current User',
                'email' => 'other@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
        $this->postJson('/api/auth/logout')->assertStatus(401);
        $this->putJson('/api/auth/profile', ['name' => 'Test'])->assertStatus(401);
    }

    public function test_user_can_register_with_default_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Default Role User',
            'email' => 'defaultrole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'defaultrole@example.com',
            'role' => 'warehouse',
        ]);
    }
}
