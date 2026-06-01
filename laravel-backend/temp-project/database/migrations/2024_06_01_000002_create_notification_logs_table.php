<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('retail_stores')->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index(); // sms, whatsapp, email, push
            $table->string('event')->index(); // trip_started, arrival_soon, delivered, order_confirmed
            $table->string('recipient_phone');
            $table->text('message');
            $table->string('status')->default('pending')->index(); // pending, sent, failed, retrying
            $table->integer('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status', 'created_at']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event')->index(); // trip_started, arrival_soon, delivered, order_confirmed
            $table->string('language', 5)->default('ar'); // ar, en
            $table->text('sms_template');
            $table->text('whatsapp_template');
            $table->json('variables'); // [{name: "driver_name", required: true}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_logs');
    }
};
