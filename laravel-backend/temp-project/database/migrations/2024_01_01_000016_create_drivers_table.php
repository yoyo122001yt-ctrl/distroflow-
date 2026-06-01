<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('plate_number')->unique();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->decimal('capacity_weight', 10, 2)->nullable();
            $table->decimal('capacity_volume', 10, 2)->nullable();
            $table->string('status')->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Drivers are stored in users table with role='driver'
        // This table extends driver-specific info
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->string('status')->default('available');
            $table->timestamps();
        });

        Schema::table('route_assignments', function (Blueprint $table) {
            $table->foreign('truck_id')->references('id')->on('trucks')->onDelete('cascade');
        });
        Schema::table('load_lists', function (Blueprint $table) {
            $table->foreign('truck_id')->references('id')->on('trucks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
        Schema::dropIfExists('trucks');
    }
};
