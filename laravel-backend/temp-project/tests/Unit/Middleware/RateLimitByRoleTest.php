<?php

namespace Tests\Unit\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitByRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'throttle.roles'])->get('/test-rate-limit', function () {
            return response()->json(['ok' => true]);
        });
    }

    protected function createUserWithRole(string $role): User
    {
        $user = User::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_admin_gets_1000_per_minute_limit(): void
    {
        $user = $this->createUserWithRole('admin');
        $response = $this->getJson('/test-rate-limit');
        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit', '1000');
    }

    public function test_store_gets_60_per_minute_limit(): void
    {
        $user = $this->createUserWithRole('store');
        $response = $this->getJson('/test-rate-limit');
        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit', '60');
    }

    public function test_driver_gets_120_per_minute_limit(): void
    {
        $user = $this->createUserWithRole('driver');
        $response = $this->getJson('/test-rate-limit');
        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit', '120');
    }

    public function test_custom_limit_override_works(): void
    {
        Route::middleware(['auth:sanctum', 'throttle.roles:5:1'])->get('/test-custom-limit', function () {
            return response()->json(['ok' => true]);
        });

        $user = $this->createUserWithRole('admin');

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/test-custom-limit')->assertOk();
        }

        $this->getJson('/test-custom-limit')->assertStatus(429);
    }

    public function test_x_rate_limit_remaining_header_present(): void
    {
        $user = $this->createUserWithRole('admin');
        $response = $this->getJson('/test-rate-limit');
        $response->assertOk();
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_rate_limit_exceeded_returns_429(): void
    {
        Route::middleware(['auth:sanctum', 'throttle.roles:2:1'])->get('/test-rate-exceed', function () {
            return response()->json(['ok' => true]);
        });

        $user = $this->createUserWithRole('store');

        $this->getJson('/test-rate-exceed')->assertOk();
        $this->getJson('/test-rate-exceed')->assertOk();
        $this->getJson('/test-rate-exceed')->assertStatus(429);
    }

    public function test_rate_limit_headers_present_on_429(): void
    {
        Route::middleware(['auth:sanctum', 'throttle.roles:1:1'])->get('/test-rate-headers', function () {
            return response()->json(['ok' => true]);
        });

        $user = $this->createUserWithRole('store');

        $this->getJson('/test-rate-headers')->assertOk();
        $response = $this->getJson('/test-rate-headers');
        $response->assertStatus(429);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_warehouse_manager_gets_500_per_minute_limit(): void
    {
        $user = $this->createUserWithRole('warehouse_manager');
        $response = $this->getJson('/test-rate-limit');
        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit', '500');
    }

    public function test_unknown_role_gets_30_per_minute_limit(): void
    {
        $user = $this->createUserWithRole('viewer');
        $response = $this->getJson('/test-rate-limit');
        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit', '30');
    }
}
