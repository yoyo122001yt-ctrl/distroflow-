<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->foreignId('route_assignment_id')->constrained('route_assignments');
            $table->foreignId('driver_id')->constrained('users');
            $table->foreignId('truck_id')->constrained();
            $table->date('delivery_date');
            $table->string('status')->default('pending');
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_collected', 12, 2)->default(0);
            $table->decimal('total_returns', 12, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('delivery_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
