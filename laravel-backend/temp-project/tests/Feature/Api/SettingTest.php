<?php

namespace Tests\Feature\Api;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingTest extends TestCase
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

    public function test_index_returns_all_settings_as_key_value(): void
    {
        Sanctum::actingAs($this->admin);

        SystemSetting::create(['key' => 'company_name', 'value' => 'DistroFlow', 'group' => 'general']);
        SystemSetting::create(['key' => 'currency', 'value' => 'USD', 'group' => 'financial']);
        SystemSetting::create(['key' => 'tax_rate', 'value' => '15', 'group' => 'financial']);

        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'company_name',
                    'currency',
                    'tax_rate',
                ],
                'message',
            ])
            ->assertJsonFragment([
                'company_name' => 'DistroFlow',
                'currency' => 'USD',
                'tax_rate' => '15',
            ]);
    }

    public function test_index_returns_empty_when_no_settings(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonFragment(['data' => []]);
    }

    public function test_update_creates_new_settings(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/settings', [
            'company_name' => 'New Company',
            'currency' => 'EUR',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Settings saved']);

        $this->assertDatabaseHas('system_settings', ['key' => 'company_name', 'value' => 'New Company']);
        $this->assertDatabaseHas('system_settings', ['key' => 'currency', 'value' => 'EUR']);
    }

    public function test_update_overwrites_existing_settings(): void
    {
        Sanctum::actingAs($this->admin);

        SystemSetting::create(['key' => 'currency', 'value' => 'USD', 'group' => 'financial']);

        $response = $this->postJson('/api/settings', [
            'currency' => 'GBP',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('system_settings', ['key' => 'currency', 'value' => 'GBP']);
        $this->assertDatabaseCount('system_settings', 1);
    }

    public function test_update_mixed_create_and_update(): void
    {
        Sanctum::actingAs($this->admin);

        SystemSetting::create(['key' => 'existing', 'value' => 'old', 'group' => 'general']);

        $this->postJson('/api/settings', [
            'existing' => 'new',
            'brand_new' => 'created',
        ])->assertOk();

        $this->assertDatabaseHas('system_settings', ['key' => 'existing', 'value' => 'new']);
        $this->assertDatabaseHas('system_settings', ['key' => 'brand_new', 'value' => 'created']);
    }

    public function test_empty_request_handling(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/settings', []);

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Settings saved']);
    }

    public function test_unauthenticated_user_cannot_access_settings(): void
    {
        $this->getJson('/api/settings')->assertStatus(401);
        $this->postJson('/api/settings', ['key' => 'value'])->assertStatus(401);
    }

    public function test_settings_with_null_value(): void
    {
        Sanctum::actingAs($this->admin);

        SystemSetting::create(['key' => 'nullable_key', 'value' => null, 'group' => 'general']);

        $response = $this->getJson('/api/settings');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('nullable_key', $data);
        $this->assertNull($data['nullable_key']);
    }
}
