<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $table = 'notification_templates';

    protected $fillable = [
        'event', 'language', 'sms_template', 'whatsapp_template', 'variables', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'json',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    public static function render(string $event, string $language, array $data = []): array
    {
        $template = static::active()->byEvent($event)->byLanguage($language)->first();

        if (!$template) {
            return ['sms' => '', 'whatsapp' => ''];
        }

        $replacements = [];
        foreach ($data as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }

        $sms = strtr($template->sms_template, $replacements);
        $whatsapp = strtr($template->whatsapp_template, $replacements);

        return ['sms' => $sms, 'whatsapp' => $whatsapp];
    }
}
