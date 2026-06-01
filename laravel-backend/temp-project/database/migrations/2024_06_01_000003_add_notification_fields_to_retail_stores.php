<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retail_stores', function (Blueprint $table) {
            if (!Schema::hasColumn('retail_stores', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('retail_stores', 'whatsapp_phone')) {
                $table->string('whatsapp_phone', 20)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('retail_stores', 'sms_phone')) {
                $table->string('sms_phone', 20)->nullable()->after('whatsapp_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('retail_stores', function (Blueprint $table) {
            $table->dropColumn(['notification_preferences', 'whatsapp_phone', 'sms_phone']);
        });
    }
};
