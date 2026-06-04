<?php

namespace Tests\Feature\Commands;

use App\Models\NotificationLog;
use App\Models\RetailStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetryFailedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private RetailStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new RetailStore;
        $this->store->code = 'STR-TEST';
        $this->store->business_name = 'Test Store';
        $this->store->store_type = 'grocery';
        $this->store->contact_person = 'John';
        $this->store->phone = '0123456789';
        $this->store->email = 'store@test.com';
        $this->store->address = '123 Test St';
        $this->store->city = 'Test City';
        $this->store->state = 'Test State';
        $this->store->credit_limit = 50000;
        $this->store->current_balance = 0;
        $this->store->payment_terms = 'net_30';
        $this->store->status = 'active';
        $this->store->save();
    }

    public function test_retries_failed_notifications(): void
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        NotificationLog::create([
            'store_id' => $this->store->id,
            'type' => 'sms',
            'event' => 'delivered',
            'recipient_phone' => '0123456789',
            'message' => 'Your order has been delivered',
            'status' => 'failed',
            'retry_count' => 1,
        ]);

        NotificationLog::create([
            'store_id' => $this->store->id,
            'type' => 'sms',
            'event' => 'arrival_soon',
            'recipient_phone' => '0987654321',
            'message' => 'Driver arriving soon',
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        $this->artisan('notifications:retry-failed')
            ->assertExitCode(0)
            ->expectsOutput('Retrying failed notifications...')
            ->expectsOutputToContain('Successfully retried 2 notification(s).');
    }

    public function test_skips_notifications_exceeding_max_retries(): void
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        NotificationLog::create([
            'store_id' => $this->store->id,
            'type' => 'sms',
            'event' => 'delivered',
            'recipient_phone' => '0123456789',
            'message' => 'Test message',
            'status' => 'failed',
            'retry_count' => 3,
        ]);

        $this->artisan('notifications:retry-failed')
            ->assertExitCode(0)
            ->expectsOutputToContain('Successfully retried 0 notification(s).');
    }

    public function test_skips_successful_notifications(): void
    {
        NotificationLog::create([
            'store_id' => $this->store->id,
            'type' => 'sms',
            'event' => 'delivered',
            'recipient_phone' => '0123456789',
            'message' => 'Already sent',
            'status' => 'sent',
            'retry_count' => 0,
        ]);

        $this->artisan('notifications:retry-failed')
            ->assertExitCode(0)
            ->expectsOutputToContain('Successfully retried 0 notification(s).');
    }

    public function test_outputs_zero_when_no_failed_notifications(): void
    {
        $this->artisan('notifications:retry-failed')
            ->assertExitCode(0)
            ->expectsOutput('Retrying failed notifications...')
            ->expectsOutputToContain('Successfully retried 0 notification(s).');
    }

    public function test_marks_notification_as_retrying_then_sent_on_success(): void
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $log = NotificationLog::create([
            'store_id' => $this->store->id,
            'type' => 'sms',
            'event' => 'order_confirmed',
            'recipient_phone' => '0123456789',
            'message' => 'Order confirmed',
            'status' => 'failed',
            'retry_count' => 1,
        ]);

        $this->artisan('notifications:retry-failed')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_logs', [
            'id' => $log->id,
            'status' => 'sent',
            'retry_count' => 2,
        ]);

        $this->assertNotNull($log->fresh()->sent_at);
    }

    public function test_marks_notification_as_failed_on_http_error(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $log = NotificationLog::create([
            'store_id' => $this->store->id,
            'type' => 'sms',
            'event' => 'delivered',
            'recipient_phone' => '0123456789',
            'message' => 'Will fail',
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        $this->artisan('notifications:retry-failed')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_logs', [
            'id' => $log->id,
            'status' => 'failed',
            'retry_count' => 1,
            'error_message' => 'Retry failed',
        ]);
    }

    public function test_uses_custom_max_attempts_option(): void
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        NotificationLog::create([
            'store_id' => $this->store->id,
            'type' => 'sms',
            'event' => 'delivered',
            'recipient_phone' => '0123456789',
            'message' => 'Custom max test',
            'status' => 'failed',
            'retry_count' => 4,
        ]);

        $this->artisan('notifications:retry-failed', ['--max' => '5'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Successfully retried 1 notification(s).');
    }

    public function test_only_retries_notifications_from_last_24_hours(): void
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $log = NotificationLog::create([
            'store_id' => $this->store->id,
            'type' => 'sms',
            'event' => 'delivered',
            'recipient_phone' => '0123456789',
            'message' => 'Old notification',
            'status' => 'failed',
            'retry_count' => 0,
        ]);
        $log->created_at = now()->subHours(48);
        $log->save();

        $this->artisan('notifications:retry-failed')
            ->assertExitCode(0)
            ->expectsOutputToContain('Successfully retried 0 notification(s).');
    }
}
