<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_stores', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('business_name');
            $table->string('trade_name')->nullable();
            $table->string('store_type')->default('grocery');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->string('payment_terms')->default('cod');
            $table->string('tax_id')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained();
            $table->timestamps();

            $table->index('store_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_stores');
    }
};
