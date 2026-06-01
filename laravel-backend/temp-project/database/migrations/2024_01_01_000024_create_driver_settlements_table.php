<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users');
            $table->foreignId('route_assignment_id')->constrained('route_assignments');
            $table->foreignId('delivery_id')->constrained();
            $table->date('settlement_date');
            $table->string('status')->default('pending');

            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_returns', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->default(0);
            $table->decimal('actual_cash', 12, 2)->default(0);
            $table->decimal('cash_variance', 12, 2)->default(0);

            $table->decimal('starting_inventory_value', 12, 2)->default(0);
            $table->decimal('loaded_value', 12, 2)->default(0);
            $table->decimal('sales_value', 12, 2)->default(0);
            $table->decimal('returns_value', 12, 2)->default(0);
            $table->decimal('expected_end_inventory_value', 12, 2)->default(0);
            $table->decimal('actual_end_inventory_value', 12, 2)->default(0);
            $table->decimal('inventory_variance', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_settlements');
    }
};
