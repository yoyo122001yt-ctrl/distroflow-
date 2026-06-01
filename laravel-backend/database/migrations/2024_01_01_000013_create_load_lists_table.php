<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('load_lists', function (Blueprint $table) {
            $table->id();
            $table->string('load_list_number')->unique();
            $table->foreignId('route_id')->constrained();
            $table->foreignId('truck_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('status')->default('pending');
            $table->foreignId('loaded_by')->nullable()->constrained('users');
            $table->timestamp('loaded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('load_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pick_list_item_id')->constrained('pick_list_items');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->constrained();
            $table->decimal('quantity', 12, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('load_list_items');
        Schema::dropIfExists('load_lists');
    }
};
