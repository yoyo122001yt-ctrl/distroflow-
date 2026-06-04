<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:admin'])->get('/test-admin', fn () => response()->json(['ok' => true]));
        Route::middleware(['auth:sanctum', 'role:store'])->get('/test-store', fn () => response()->json(['ok' => true]));
        Route::middleware(['auth:sanctum', 'role:driver'])->get('/test-driver', fn () => response()->json(['ok' => true]));
        Route::middleware(['auth:sanctum', 'role:admin,manager,warehouse_manager'])->get('/test-multi-role', fn () => response()->json(['ok' => true]));
    }

    protected function createUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'viewer',
            'is_active' => true,
        ], $overrides));

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_role_matching_passes_200(): void
    {
        $this->createUser(['role' => 'admin']);

        $this->getJson('/test-admin')->assertOk();
    }

    public function test_non_matching_role_returns_403(): void
    {
        $this->createUser(['role' => 'viewer']);

        $this->getJson('/test-admin')->assertStatus(403);
    }

    public function test_no_user_returns_401(): void
    {
        $this->getJson('/test-admin')->assertStatus(401);
    }

    public function test_multiple_role_parameters_one_matches(): void
    {
        $this->createUser(['role' => 'manager']);

        $this->getJson('/test-multi-role')->assertOk();
    }

    public function test_multiple_role_parameters_none_matches(): void
    {
        $this->createUser(['role' => 'driver']);

        $this->getJson('/test-multi-role')->assertStatus(403);
    }

    public function test_admin_can_access_store_protected_route(): void
    {
        $this->createUser(['role' => 'admin']);

        $this->getJson('/test-store')->assertStatus(403);
    }

    public function test_role_matching_is_exact(): void
    {
        $this->createUser(['role' => 'admin']);

        Route::middleware(['auth:sanctum', 'role:adm'])->get('/test-partial', fn () => response()->json(['ok' => true]));

        $this->getJson('/test-partial')->assertStatus(403);
    }

    public function test_unauthenticated_returns_401_with_message(): void
    {
        $this->getJson('/test-admin')
            ->assertStatus(401)
            ->assertJsonFragment(['message' => 'Unauthenticated.']);
    }

    public function test_unauthorized_returns_403_with_role_message(): void
    {
        $this->createUser(['role' => 'viewer']);

        $this->getJson('/test-admin')
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'Unauthorized. Required role: admin']);
    }
}
