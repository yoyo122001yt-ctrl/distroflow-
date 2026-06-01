<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->constrained();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('starting_quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['truck_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_inventory');
    }
};
