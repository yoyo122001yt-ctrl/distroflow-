<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_stock_movements', function (Blueprint $table) {
            $table->index(['product_id', 'created_at'], 'idx_stock_product_created');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['driver_id', 'status', 'scheduled_date'], 'idx_delivery_driver_status_date');
            $table->index(['route_id', 'status'], 'idx_delivery_route_status');
        });

        Schema::table('driver_locations', function (Blueprint $table) {
            $table->index(['driver_id', 'recorded_at'], 'idx_dloc_driver_time');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index(['retail_store_id', 'status', 'order_date'], 'idx_so_store_status_date');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['sales_order_id', 'product_id'], 'idx_oi_order_product');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->index(['product_id', 'warehouse_id', 'status'], 'idx_batch_product_wh_status');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_stock_product_created');
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('idx_delivery_driver_status_date');
            $table->dropIndex('idx_delivery_route_status');
        });
        Schema::table('driver_locations', function (Blueprint $table) {
            $table->dropIndex('idx_dloc_driver_time');
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex('idx_so_store_status_date');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('idx_oi_order_product');
        });
        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex('idx_batch_product_wh_status');
        });
    }
};
