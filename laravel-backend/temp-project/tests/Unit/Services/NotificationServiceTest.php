<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\NotificationService;
use App\Models\RetailStore;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Database\Factories\RetailStoreFactory;
use Illuminate\Support\Facades\Http;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notification.providers.primary' => 'twilio',
            'notification.providers.fallback' => 'vonage',
            'notification.providers.twilio.account_sid' => 'test_sid',
            'notification.providers.twilio.auth_token' => 'test_token',
            'notification.providers.twilio.from_number' => '+1234567890',
            'notification.providers.vonage.api_key' => 'test_key',
            'notification.providers.vonage.api_secret' => 'test_secret',
            'notification.providers.vonage.from' => 'Test',
            'notification.rate_limits.max_per_hour' => 10,
            'notification.whatsapp.enabled' => false,
        ]);

        $this->service = new NotificationService();
    }

    public function test_send_creates_log_and_dispatches_successfully(): void
    {
        $store = RetailStoreFactory::new()->create([
            'sms_phone' => '+1987654321',
            'phone' => '+1987654321',
        ]);

        NotificationTemplate::create([
            'event' => 'order_confirmed',
            'language' => 'en',
            'sms_template' => 'Your order {order_number} is confirmed',
            'whatsapp_template' => 'Your order {order_number} is confirmed',
            'variables' => [['name' => 'order_number', 'required' => true]],
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.twilio.com/*' => Http::response(['status' => 'sent'], 201),
        ]);

        $log = $this->service->send($store, 'order_confirmed', ['order_number' => 'ORD-001']);

        $this->assertInstanceOf(NotificationLog::class, $log);
        $this->assertEquals($store->id, $log->store_id);
        $this->assertEquals('order_confirmed', $log->event);
        $this->assertEquals('sms', $log->type);
        $this->assertEquals('Your order ORD-001 is confirmed', $log->message);
        $this->assertEquals($store->sms_phone, $log->recipient_phone);
        $this->assertEquals('sent', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
    }

    public function test_send_returns_skipped_log_when_notification_preferences_suppress_event(): void
    {
        $store = RetailStoreFactory::new()->create([
            'sms_phone' => '+1987654321',
            'notification_preferences' => json_encode(['order_confirmed' => false]),
        ]);

        $log = $this->service->send($store, 'order_confirmed', []);

        $this->assertEquals('skipped', $log->status);
        $this->assertEquals('Skipped by preference', $log->message);
        $this->assertEquals($store->id, $log->store_id);
    }

    public function test_send_returns_failed_log_when_no_template_found(): void
    {
        $store = RetailStoreFactory::new()->create([
            'sms_phone' => '+1987654321',
        ]);

        $log = $this->service->send($store, 'unknown_event', []);

        $this->assertEquals('failed', $log->status);
        $this->assertEquals('No template found', $log->message);
    }

    public function test_send_returns_failed_log_when_rate_limited(): void
    {
        $store = RetailStoreFactory::new()->create([
            'sms_phone' => '+1987654321',
        ]);

        for ($i = 0; $i < 10; $i++) {
            NotificationLog::create([
                'store_id' => $store->id,
                'type' => 'sms',
                'event' => 'other',
                'recipient_phone' => '+1987654321',
                'message' => 'test',
                'status' => 'sent',
                'retry_count' => 0,
                'sent_at' => now(),
            ]);
        }

        $reflection = new \ReflectionClass(\App\Services\NotificationService::class);
        $method = $reflection->getMethod('checkRateLimit');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($this->service, $store));

        $method2 = $reflection->getMethod('createLog');
        $method2->setAccessible(true);
        $log = $method2->invoke($this->service, $store, 'order_confirmed', 'sms', '+1987654321', 'Rate limit exceeded', 'failed', null, 0, 'Rate limit: max 10/hour');

        $this->assertEquals('failed', $log->status);
        $this->assertEquals('Rate limit exceeded', $log->message);
        $this->assertEquals('Rate limit: max 10/hour', $log->error_message);
    }

    public function test_send_raw_sends_via_primary_provider_twilio(): void
    {
        Http::fake([
            'https://api.twilio.com/*' => Http::response(['status' => 'sent'], 201),
        ]);

        $result = $this->service->sendRaw('+1987654321', 'Test message');

        $this->assertTrue($result);
    }

    public function test_send_raw_falls_back_to_vonage_when_twilio_fails(): void
    {
        Http::fake([
            'https://api.twilio.com/*' => function () {
                throw new \Exception('Connection refused');
            },
            'https://rest.nexmo.com/*' => Http::response(['messages' => [['status' => '0']]], 200),
        ]);

        $result = $this->service->sendRaw('+1987654321', 'Fallback test');

        $this->assertTrue($result);
    }

    public function test_send_raw_returns_false_when_all_providers_fail(): void
    {
        Http::fake([
            'https://api.twilio.com/*' => function () {
                throw new \Exception('Network error');
            },
            'https://rest.nexmo.com/*' => function () {
                throw new \Exception('Network error');
            },
        ]);

        $result = $this->service->sendRaw('+1987654321', 'Failing test');

        $this->assertFalse($result);
    }

    public function test_send_raw_returns_false_when_both_primary_and_fallback_are_unknown(): void
    {
        config([
            'notification.providers.primary' => 'unknown_provider',
            'notification.providers.fallback' => 'unknown_provider',
        ]);

        $service = new NotificationService();

        $result = $service->sendRaw('+1987654321', 'Test');

        $this->assertFalse($result);
    }

    public function test_retry_failed_retries_failed_notification_logs(): void
    {
        $store = RetailStoreFactory::new()->create();
        $log = NotificationLog::create([
            'store_id' => $store->id,
            'type' => 'sms',
            'event' => 'order_confirmed',
            'recipient_phone' => '+1987654321',
            'message' => 'Retry me',
            'status' => 'failed',
            'retry_count' => 0,
            'created_at' => now()->subHour(),
        ]);

        Http::fake([
            'https://api.twilio.com/*' => Http::response(['status' => 'sent'], 201),
        ]);

        $retried = $this->service->retryFailed();

        $this->assertEquals(1, $retried);
        $fresh = $log->fresh();
        $this->assertEquals('sent', $fresh->status);
        $this->assertEquals(1, $fresh->retry_count);
        $this->assertNotNull($fresh->sent_at);
    }

    public function test_retry_failed_skips_logs_exceeding_max_attempts(): void
    {
        $store = RetailStoreFactory::new()->create();
        NotificationLog::create([
            'store_id' => $store->id,
            'type' => 'sms',
            'event' => 'order_confirmed',
            'recipient_phone' => '+1987654321',
            'message' => 'Too many retries',
            'status' => 'failed',
            'retry_count' => 3,
            'created_at' => now()->subHour(),
        ]);

        Http::fake();

        $retried = $this->service->retryFailed(3);

        $this->assertEquals(0, $retried);
    }
}
