<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('driver_profiles', 'vehicle_plate')) {
                $table->string('vehicle_plate')->nullable()->after('license_number');
            }
            if (!Schema::hasColumn('driver_profiles', 'pay_rate')) {
                $table->decimal('pay_rate', 10, 2)->nullable()->after('emergency_phone');
            }
            if (!Schema::hasColumn('driver_profiles', 'pay_type')) {
                $table->string('pay_type')->nullable()->after('pay_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn(['vehicle_plate', 'pay_rate', 'pay_type']);
        });
    }
};
