<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number')->unique();
            $table->foreignId('retail_store_id')->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained();
            $table->foreignId('delivery_return_id')->nullable()->constrained('delivery_returns');
            $table->date('credit_date');
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
