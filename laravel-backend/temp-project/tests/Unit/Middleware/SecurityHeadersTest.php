<?php

namespace Tests\Unit\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'security.headers'])->get('/test-headers', fn () => response()->json(['ok' => true]));
    }

    protected function getResponse()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        return $this->getJson('/test-headers');
    }

    public function test_x_frame_options_header_present(): void
    {
        $response = $this->getResponse();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_x_content_type_options_header(): void
    {
        $response = $this->getResponse();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_x_xss_protection_header_present(): void
    {
        $response = $this->getResponse();
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    public function test_referrer_policy_header_present(): void
    {
        $response = $this->getResponse();
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_permissions_policy_header_present(): void
    {
        $response = $this->getResponse();
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
    }

    public function test_content_security_policy_header_present(): void
    {
        $response = $this->getResponse();
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_hsts_not_set_on_non_secure_connection(): void
    {
        $response = $this->getResponse();
        $response->assertHeaderMissing('Strict-Transport-Security');
    }
}
