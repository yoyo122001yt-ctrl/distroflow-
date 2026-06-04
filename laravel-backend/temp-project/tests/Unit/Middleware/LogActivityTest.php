<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\LogActivity;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', LogActivity::class])->group(function () {
            Route::post('/test-log-create', fn () => response()->json(['ok' => true]));
            Route::put('/test-log-update', fn () => response()->json(['ok' => true]));
            Route::patch('/test-log-patch', fn () => response()->json(['ok' => true]));
            Route::delete('/test-log-delete/{id}', fn () => response()->json(['ok' => true]));
            Route::get('/test-log-read', fn () => response()->json(['ok' => true]));
        });
    }

    protected function createUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_post_creates_activity_log(): void
    {
        $user = $this->createUser();

        $this->postJson('/test-log-create')->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'create',
        ]);
    }

    public function test_put_creates_activity_log(): void
    {
        $user = $this->createUser();

        $this->putJson('/test-log-update')->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'update',
        ]);
    }

    public function test_delete_creates_activity_log(): void
    {
        $user = $this->createUser();

        $this->deleteJson('/test-log-delete/1')->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'delete',
        ]);
    }

    public function test_get_does_not_create_activity_log(): void
    {
        $this->createUser();

        $this->getJson('/test-log-read')->assertOk();

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_unauthenticated_requests_do_not_create_logs(): void
    {
        $this->postJson('/test-log-create')->assertStatus(401);

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_passwords_are_excluded_from_log_details(): void
    {
        $this->createUser();

        Route::middleware(['auth:sanctum', LogActivity::class])->post('/test-log-passwords', fn () => response()->json(['ok' => true]));

        $this->postJson('/test-log-passwords', [
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'name' => 'Test',
        ])->assertOk();

        $log = ActivityLog::first();
        $this->assertArrayNotHasKey('password', $log->details['payload'] ?? []);
        $this->assertArrayNotHasKey('password_confirmation', $log->details['payload'] ?? []);
    }

    public function test_resource_type_extracted_from_url_path(): void
    {
        $this->createUser();

        Route::middleware(['auth:sanctum', LogActivity::class])->post('/api/test-resource', fn () => response()->json(['ok' => true]));

        $this->postJson('/api/test-resource')->assertOk();

        $log = ActivityLog::first();
        $this->assertEquals('test-resource', $log->resource_type);
    }

    public function test_activity_log_contains_user_agent(): void
    {
        $this->createUser();

        $this->postJson('/test-log-create', [], ['User-Agent' => 'TestAgent/1.0'])->assertOk();

        $log = ActivityLog::first();
        $this->assertEquals('TestAgent/1.0', $log->user_agent);
    }

    public function test_activity_log_contains_ip_address(): void
    {
        $this->createUser();

        $this->postJson('/test-log-create')->assertOk();

        $log = ActivityLog::first();
        $this->assertNotEmpty($log->ip_address);
    }

    public function test_multiple_mutation_methods_all_log(): void
    {
        $this->createUser();

        $this->postJson('/test-log-create')->assertOk();
        $this->putJson('/test-log-update')->assertOk();
        $this->patchJson('/test-log-patch')->assertOk();
        $this->deleteJson('/test-log-delete/1')->assertOk();

        $this->assertDatabaseCount('activity_logs', 4);
    }
}
