<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    protected function createLog(array $overrides = []): ActivityLog
    {
        $data = array_merge([
            'user_id' => $this->admin->id,
            'action' => 'create',
            'resource_type' => 'product',
            'resource_id' => 1,
            'description' => 'Created a product',
            'details' => json_encode(['payload' => ['name' => 'Test']]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        $id = DB::table('activity_logs')->insertGetId($data);

        return ActivityLog::find($id);
    }

    public function test_index_returns_paginated_logs(): void
    {
        Sanctum::actingAs($this->admin);

        for ($i = 0; $i < 5; $i++) {
            $this->createLog(['description' => "Log entry {$i}"]);
        }

        $response = $this->getJson('/api/activity-logs');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'action', 'resource_type', 'description'],
                    ],
                    'current_page',
                    'last_page',
                    'total',
                ],
            ]);
    }

    public function test_filter_by_user(): void
    {
        Sanctum::actingAs($this->admin);

        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@test.com',
            'password' => bcrypt('password'),
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $this->createLog(['user_id' => $this->admin->id, 'description' => 'Admin log']);
        $this->createLog(['user_id' => $otherUser->id, 'description' => 'Other log']);

        $response = $this->getJson('/api/activity-logs?user=' . $this->admin->id);

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Admin log', $data[0]['description']);
    }

    public function test_filter_by_action(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createLog(['action' => 'create', 'description' => 'Create log']);
        $this->createLog(['action' => 'delete', 'description' => 'Delete log']);

        $response = $this->getJson('/api/activity-logs?action=delete');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Delete log', $data[0]['description']);
    }

    public function test_filter_by_date_range(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createLog([
            'description' => 'Recent log',
            'created_at' => now()->subDay()->toDateTimeString(),
        ]);

        $this->createLog([
            'description' => 'Old log',
            'created_at' => now()->subDays(10)->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/activity-logs?date_from=' . now()->subDays(5)->format('Y-m-d'));

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Recent log', $data[0]['description']);
    }

    public function test_filter_by_date_to(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createLog([
            'description' => 'Old log',
            'created_at' => now()->subDays(5)->toDateTimeString(),
        ]);

        $this->createLog([
            'description' => 'Recent log',
            'created_at' => now()->subDay()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/activity-logs?date_to=' . now()->subDays(3)->format('Y-m-d'));

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Old log', $data[0]['description']);
    }

    public function test_filter_by_resource_type(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createLog(['resource_type' => 'product', 'description' => 'Product log']);
        $this->createLog(['resource_type' => 'order', 'description' => 'Order log']);

        $response = $this->getJson('/api/activity-logs?resource_type=product');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_export_returns_csv(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createLog(['description' => 'Export log']);

        $response = $this->getJson('/api/activity-logs/export');

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="activity-log.csv"');

        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/csv', $contentType);
    }

    public function test_export_contains_csv_header_and_data(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createLog([
            'description' => 'Test export entry',
            'action' => 'create',
        ]);

        $response = $this->getJson('/api/activity-logs/export');

        $response->assertOk();

        $streamedContent = $response->streamedContent();
        $this->assertStringContainsString('ID', $streamedContent);
        $this->assertStringContainsString('User', $streamedContent);
        $this->assertStringContainsString('Action', $streamedContent);
        $this->assertStringContainsString('Resource', $streamedContent);
    }

    public function test_export_filters_by_user(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createLog(['description' => 'Admin action', 'action' => 'admin_action']);

        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@test.com',
            'password' => bcrypt('password'),
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $this->createLog(['user_id' => $otherUser->id, 'description' => 'Other action', 'action' => 'other_action']);

        $response = $this->getJson('/api/activity-logs/export?user=' . $this->admin->id);

        $streamedContent = $response->streamedContent();
        $this->assertStringContainsString('admin_action', $streamedContent);
        $this->assertStringNotContainsString('other_action', $streamedContent);
    }

    public function test_unauthenticated_user_cannot_access_logs(): void
    {
        $this->getJson('/api/activity-logs')->assertStatus(401);
        $this->getJson('/api/activity-logs/export')->assertStatus(401);
    }

    public function test_logs_ordered_by_latest_first(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createLog(['description' => 'First', 'created_at' => now()->subHour()->toDateTimeString()]);
        $this->createLog(['description' => 'Second', 'created_at' => now()->toDateTimeString()]);

        $response = $this->getJson('/api/activity-logs');

        $data = $response->json('data.data');
        $this->assertEquals('Second', $data[0]['description']);
        $this->assertEquals('First', $data[1]['description']);
    }
}
