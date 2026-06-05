<?php

namespace Database\Seeders;

use App\Models\SalesOrder;
use App\Models\OrderItem;
use App\Models\PickList;
use App\Models\PickListItem;
use App\Models\LoadList;
use App\Models\LoadListItem;
use App\Models\Delivery;
use App\Models\DeliveryStop;
use App\Models\DeliveryItem;
use App\Models\DeliveryPayment;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseZone;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\RouteStop;
use App\Models\RetailStore;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoFulfillmentSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();
        $completedOrders = SalesOrder::whereIn('status', ['delivered', 'completed'])->get();
        $processingOrders = SalesOrder::where('status', 'processing')->get();
        $approvedOrders = SalesOrder::whereIn('status', ['approved', 'pending'])->get();
        $zoneIds = $warehouse ? WarehouseZone::where('warehouse_id', $warehouse->id)->pluck('id') : collect();
        $locations = WarehouseLocation::whereIn('warehouse_zone_id', $zoneIds)->get();
        $batches = Batch::where('available_quantity', '>', 0)->get();
        $routes = Route::all();
        $assignments = RouteAssignment::all();
        $drivers = User::where('role', 'driver')->get();
        $trucks = Truck::all();

        if ($completedOrders->isEmpty() && $processingOrders->isEmpty()) {
            $this->command->warn('No sales orders to fulfill. Run DemoOrdersSeeder first.');
            return;
        }

        $allTargetOrders = $completedOrders->merge($processingOrders)->merge($approvedOrders->take(2));

        $pickCount = 0;
        $loadCount = 0;
        $deliveryCount = 0;

        foreach ($allTargetOrders as $order) {
            $routeStop = RouteStop::where('retail_store_id', $order->retail_store_id)->first();
            $route = $routeStop?->route;

            if (!$route) continue;

            $routeAssignment = RouteAssignment::where('route_id', $route->id)
                ->where('assignment_date', now()->toDateString())->first();

            $driver = $routeAssignment ? User::find($routeAssignment->driver_id) : $drivers->first();
            $truck = $routeAssignment ? Truck::find($routeAssignment->truck_id) : $trucks->first();

            $items = $order->items;

            // ──────────────────────────────
            // PICK LIST
            // ──────────────────────────────
            $pickList = PickList::create([
                'pick_list_number' => 'PL-' . str_pad($pickCount + 1, 4, '0', STR_PAD_LEFT),
                'warehouse_id' => $warehouse->id,
                'route_id' => $route->id,
                'status' => $order->status === 'delivered' ? 'completed' : 'pending',
                'picked_by' => $order->status === 'delivered' ? User::where('role', 'manager')->first()?->id : null,
                'picked_at' => $order->status === 'delivered' ? now()->subHours(rand(2, 8)) : null,
                'notes' => 'Auto-generated for order ' . $order->order_number,
            ]);

            foreach ($items as $item) {
                $batch = $batches->where('product_id', $item->product_id)->first();
                $location = $locations->random();

                PickListItem::create([
                    'pick_list_id' => $pickList->id,
                    'sales_order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'batch_id' => $batch?->id,
                    'warehouse_location_id' => $location?->id,
                    'quantity' => $item->quantity_ordered,
                    'status' => $order->status === 'delivered' ? 'picked' : 'pending',
                ]);
            }
            $pickCount++;

            // ──────────────────────────────
            // LOAD LIST (for completed/processing only)
            // ──────────────────────────────
            if (in_array($order->status, ['delivered', 'completed', 'processing'])) {
                $pickListItems = $pickList->items;

                $loadList = LoadList::create([
                    'load_list_number' => 'LL-' . str_pad($loadCount + 1, 4, '0', STR_PAD_LEFT),
                    'route_id' => $route->id,
                    'truck_id' => $truck?->id,
                    'warehouse_id' => $warehouse->id,
                    'status' => $order->status === 'delivered' ? 'loaded' : 'loading',
                    'loaded_by' => $order->status === 'delivered' ? User::where('role', 'manager')->first()?->id : null,
                    'loaded_at' => $order->status === 'delivered' ? now()->subHours(rand(1, 4)) : null,
                    'notes' => 'Auto-generated for pick list ' . $pickList->pick_list_number,
                ]);

                foreach ($pickListItems as $pli) {
                    LoadListItem::create([
                        'load_list_id' => $loadList->id,
                        'pick_list_item_id' => $pli->id,
                        'product_id' => $pli->product_id,
                        'batch_id' => $pli->batch_id,
                        'quantity' => $pli->quantity,
                        'status' => $order->status === 'delivered' ? 'loaded' : 'pending',
                    ]);
                }
                $loadCount++;

                // ──────────────────────────────
                // DELIVERY (for completed orders with assignment)
                if ($order->status === 'delivered' && $driver && $truck && $routeAssignment) {
                    $delivery = Delivery::create([
                        'delivery_number' => 'DEL-' . str_pad($deliveryCount + 1, 4, '0', STR_PAD_LEFT),
                        'route_assignment_id' => $routeAssignment->id,
                        'driver_id' => $driver->id,
                        'truck_id' => $truck->id,
                        'delivery_date' => now()->toDateString(),
                        'status' => 'delivered',
                        'total_sales' => $order->total,
                        'total_collected' => $order->total,
                        'total_returns' => 0,
                        'started_at' => now()->subHours(rand(6, 10)),
                        'completed_at' => now()->subHours(rand(1, 3)),
                        'notes' => 'Delivery completed for order ' . $order->order_number,
                    ]);

                    // Delivery stop for the store
                    $stop = DeliveryStop::create([
                        'delivery_id' => $delivery->id,
                        'route_stop_id' => $routeStop?->id,
                        'retail_store_id' => $order->retail_store_id,
                        'stop_order' => 1,
                        'status' => 'completed',
                        'arrived_at' => now()->subHours(rand(3, 5)),
                        'departed_at' => now()->subHours(rand(2, 4)),
                        'collected_amount' => $order->total,
                        'payment_method' => ['cash', 'credit', 'check'][array_rand(['cash', 'credit', 'check'])],
                        'notes' => 'Delivered successfully',
                    ]);

                    // Delivery items
                    foreach ($items as $item) {
                        DeliveryItem::create([
                            'delivery_id' => $delivery->id,
                            'sales_order_id' => $order->id,
                            'order_item_id' => $item->id,
                            'product_id' => $item->product_id,
                            'batch_id' => $batches->where('product_id', $item->product_id)->first()?->id,
                            'quantity_loaded' => $item->quantity_ordered,
                            'quantity_delivered' => $item->quantity_ordered,
                            'quantity_returned' => 0,
                            'unit_price' => $item->unit_price,
                            'total_price' => $item->total_price,
                            'status' => 'delivered',
                        ]);
                    }

                    // Payment
                    DeliveryPayment::create([
                        'delivery_stop_id' => $stop->id,
                        'delivery_id' => $delivery->id,
                        'sales_order_id' => $order->id,
                        'amount' => $order->total,
                        'payment_method' => 'cash',
                        'status' => 'completed',
                    ]);

                    $deliveryCount++;
                }
            }
        }

        $this->command->info("$pickCount pick lists, $loadCount load lists, $deliveryCount deliveries created.");
    }
}
