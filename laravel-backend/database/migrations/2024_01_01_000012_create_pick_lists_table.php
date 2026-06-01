<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_lists', function (Blueprint $table) {
            $table->id();
            $table->string('pick_list_number')->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('route_id')->nullable()->constrained();
            $table->string('status')->default('pending');
            $table->foreignId('picked_by')->nullable()->constrained('users');
            $table->timestamp('picked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pick_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pick_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained();
            $table->foreignId('order_item_id')->constrained('order_items');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->constrained();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations');
            $table->decimal('quantity', 12, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_list_items');
        Schema::dropIfExists('pick_lists');
    }
};
