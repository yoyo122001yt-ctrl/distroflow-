<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retail_store_id')->constrained();
            $table->integer('stop_order')->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('estimated_arrival')->nullable();
            $table->string('estimated_departure')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['route_id', 'retail_store_id']);
            $table->index(['route_id', 'stop_order']);
        });

        Schema::create('route_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users');
            $table->foreignId('truck_id')->constrained();
            $table->date('assignment_date');
            $table->string('status')->default('scheduled');
            $table->timestamps();

            $table->unique(['route_id', 'assignment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_assignments');
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
    }
};
