<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_store_id')->constrained()->cascadeOnDelete();
            $table->string('license_type');
            $table->string('license_number');
            $table->date('issued_date')->nullable();
            $table->date('expiry_date');
            $table->string('issuing_authority')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['retail_store_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_licenses');
    }
};
