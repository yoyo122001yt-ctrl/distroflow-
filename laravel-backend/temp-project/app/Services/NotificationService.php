<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\RetailStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('notification');
    }

    public function send(
        RetailStore $store,
        string $event,
        array $data = [],
        ?int $deliveryId = null
    ): NotificationLog {
        if (!$this->shouldNotify($store, $event)) {
            $log = $this->createLog($store, $event, 'sms', $store->sms_phone ?? $store->phone, 'Skipped by preference', 'skipped', $deliveryId);
            return $log;
        }

        $lang = app()->getLocale();
        $rendered = NotificationTemplate::render($event, $lang, $data);

        $phone = $store->sms_phone ?? $store->phone;
        $message = $rendered['sms'];

        if (!$message) {
            $log = $this->createLog($store, $event, 'sms', $phone, 'No template found', 'failed', $deliveryId);
            return $log;
        }

        if (!$this->checkRateLimit($store)) {
            $log = $this->createLog($store, $event, 'sms', $phone, 'Rate limit exceeded', 'failed', $deliveryId, null, 'Rate limit: max ' . $this->config['rate_limits']['max_per_hour'] . '/hour');
            return $log;
        }

        $log = $this->createLog($store, $event, 'sms', $phone, $message, 'pending', $deliveryId);
        $this->dispatchSend($log);

        if ($this->config['whatsapp']['enabled'] && $store->whatsapp_phone) {
            $waMessage = $rendered['whatsapp'] ?: $message;
            $waLog = $this->createLog($store, $event, 'whatsapp', $store->whatsapp_phone, $waMessage, 'pending', $deliveryId);
            $this->dispatchSend($waLog);
        }

        return $log;
    }

    public function sendRaw(string $phone, string $message, string $type = 'sms'): bool
    {
        $provider = $this->getActiveProvider();

        try {
            return match ($provider) {
                'twilio' => $this->sendViaTwilio($phone, $message),
                'vonage' => $this->sendViaVonage($phone, $message),
                'victorylink' => $this->sendViaVictoryLink($phone, $message),
                default => throw new \RuntimeException("Unknown provider: $provider"),
            };
        } catch (\Throwable $e) {
            Log::warning("Primary provider {$provider} failed: {$e->getMessage()}. Trying fallback...");
            $fallback = $this->config['providers']['fallback'] ?? 'twilio';
            if ($fallback !== $provider) {
                try {
                    return match ($fallback) {
                        'twilio' => $this->sendViaTwilio($phone, $message),
                        'vonage' => $this->sendViaVonage($phone, $message),
                        'victorylink' => $this->sendViaVictoryLink($phone, $message),
                        default => false,
                    };
                } catch (\Throwable $e2) {
                    Log::error("Fallback provider {$fallback} also failed: {$e2->getMessage()}");
                }
            }
            return false;
        }
    }

    public function retryFailed(int $maxAttempts = 3): int
    {
        $failed = NotificationLog::failed()
            ->where('retry_count', '<', $maxAttempts)
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $retried = 0;
        foreach ($failed as $log) {
            $log->update(['status' => 'retrying', 'retry_count' => $log->retry_count + 1]);
            $success = $this->sendRaw($log->recipient_phone, $log->message, $log->type);
            $log->update([
                'status' => $success ? 'sent' : 'failed',
                'sent_at' => $success ? now() : null,
                'error_message' => $success ? null : 'Retry failed',
            ]);
            if ($success) $retried++;
        }

        return $retried;
    }

    protected function dispatchSend(NotificationLog $log): void
    {
        try {
            $success = $this->sendRaw($log->recipient_phone, $log->message, $log->type);
            $log->update([
                'status' => $success ? 'sent' : 'failed',
                'sent_at' => $success ? now() : null,
                'error_message' => $success ? null : 'Send failed',
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function sendViaTwilio(string $phone, string $message): bool
    {
        $config = $this->config['providers']['twilio'];
        $response = Http::withBasicAuth($config['account_sid'], $config['auth_token'])
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$config['account_sid']}/Messages.json", [
                'From' => $config['from_number'],
                'To' => $phone,
                'Body' => $message,
            ]);

        return $response->successful();
    }

    protected function sendViaVonage(string $phone, string $message): bool
    {
        $config = $this->config['providers']['vonage'];
        $response = Http::post('https://rest.nexmo.com/sms/json', [
            'api_key' => $config['api_key'],
            'api_secret' => $config['api_secret'],
            'from' => $config['from'],
            'to' => $phone,
            'text' => $message,
        ]);

        return $response->successful();
    }

    protected function sendViaVictoryLink(string $phone, string $message): bool
    {
        $config = $this->config['providers']['victorylink'];
        $response = Http::post('https://smsvas.victorylink.net/SMSWS/SendSMS.aspx', [
            'UserName' => $config['username'],
            'Password' => $config['password'],
            'SMSText' => $message,
            'SMSLang' => 'A',
            'SMSSender' => $config['sender'],
            'SMSReceiver' => $phone,
        ]);

        return $response->successful();
    }

    protected function getActiveProvider(): string
    {
        return $this->config['providers']['primary'] ?? 'twilio';
    }

    protected function shouldNotify(RetailStore $store, string $event): bool
    {
        $prefs = $store->notification_preferences;
        if (!$prefs) return true;
        $prefs = is_array($prefs) ? $prefs : json_decode($prefs, true);
        return !isset($prefs[$event]) || $prefs[$event] !== false;
    }

    protected function checkRateLimit(RetailStore $store): bool
    {
        $recentCount = NotificationLog::where('store_id', $store->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentCount < ($this->config['rate_limits']['max_per_hour'] ?? 10);
    }

    protected function createLog(
        RetailStore $store,
        string $event,
        string $type,
        string $phone,
        string $message,
        string $status,
        ?int $deliveryId = null,
        ?int $retryCount = 0,
        ?string $errorMessage = null
    ): NotificationLog {
        return NotificationLog::create([
            'store_id' => $store->id,
            'delivery_id' => $deliveryId,
            'type' => $type,
            'event' => $event,
            'recipient_phone' => $phone,
            'message' => $message,
            'status' => $status,
            'retry_count' => $retryCount,
            'sent_at' => $status === 'sent' ? now() : null,
            'error_message' => $errorMessage,
        ]);
    }
}
