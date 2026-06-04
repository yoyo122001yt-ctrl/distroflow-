<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckStoreCredit;
use App\Models\RetailStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckStoreCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', CheckStoreCredit::class])->post('/test-credit', fn () => response()->json(['ok' => true]));
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

    public function test_credit_approved_returns_200(): void
    {
        $this->createUser();

        $store = RetailStore::create([
            'code' => 'STR-001',
            'business_name' => 'Test Store',
            'store_type' => 'grocery',
            'phone' => '1234567890',
            'credit_limit' => 10000,
            'current_balance' => 5000,
            'status' => 'active',
        ]);

        $this->postJson('/test-credit', [
            'store_id' => $store->id,
            'total' => 4000,
        ])->assertOk();
    }

    public function test_credit_exceeded_returns_422(): void
    {
        $this->createUser();

        $store = RetailStore::create([
            'code' => 'STR-002',
            'business_name' => 'Test Store 2',
            'store_type' => 'grocery',
            'phone' => '1234567891',
            'credit_limit' => 10000,
            'current_balance' => 8000,
            'status' => 'active',
        ]);

        $this->postJson('/test-credit', [
            'store_id' => $store->id,
            'total' => 3000,
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Order exceeds credit limit']);
    }

    public function test_missing_store_id_passes_through(): void
    {
        $this->createUser();

        $this->postJson('/test-credit', [
            'total' => 5000,
        ])->assertOk();
    }

    public function test_nonexistent_store_passes_through(): void
    {
        $this->createUser();

        $this->postJson('/test-credit', [
            'store_id' => 99999,
            'total' => 5000,
        ])->assertOk();
    }

    public function test_credit_check_result_attached_to_request(): void
    {
        $this->createUser();

        Route::middleware(['auth:sanctum', CheckStoreCredit::class])->post('/test-credit-attach', function () {
            $creditCheck = request()->get('credit_check');
            return response()->json(['credit_check' => $creditCheck]);
        });

        $store = RetailStore::create([
            'code' => 'STR-003',
            'business_name' => 'Test Store 3',
            'store_type' => 'grocery',
            'phone' => '1234567892',
            'credit_limit' => 10000,
            'current_balance' => 2000,
            'status' => 'active',
        ]);

        $response = $this->postJson('/test-credit-attach', [
            'store_id' => $store->id,
            'total' => 1000,
        ]);

        $response->assertOk();
        $data = $response->json('credit_check');
        $this->assertTrue($data['approved']);
        $this->assertEquals(2000, $data['current_balance']);
        $this->assertEquals(10000, $data['credit_limit']);
    }

    public function test_exactly_at_credit_limit_is_approved(): void
    {
        $this->createUser();

        $store = RetailStore::create([
            'code' => 'STR-004',
            'business_name' => 'Test Store 4',
            'store_type' => 'grocery',
            'phone' => '1234567893',
            'credit_limit' => 10000,
            'current_balance' => 5000,
            'status' => 'active',
        ]);

        $this->postJson('/test-credit', [
            'store_id' => $store->id,
            'total' => 5000,
        ])->assertOk();
    }

    public function test_uses_retail_store_id_input(): void
    {
        $this->createUser();

        Route::middleware(['auth:sanctum', CheckStoreCredit::class])->post('/test-credit-retail', fn () => response()->json(['ok' => true]));

        $store = RetailStore::create([
            'code' => 'STR-005',
            'business_name' => 'Test Store 5',
            'store_type' => 'grocery',
            'phone' => '1234567894',
            'credit_limit' => 10000,
            'current_balance' => 2000,
            'status' => 'active',
        ]);

        $this->postJson('/test-credit-retail', [
            'retail_store_id' => $store->id,
            'total' => 3000,
        ])->assertOk();
    }
}
