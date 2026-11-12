<?php

namespace App\Modules\Orders\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Models\Product;
use App\Support\PerfMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Annotations as OA;
use RuntimeException;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected StockProvider $inventoryGateway)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = $this->withItems(Order::query())->latest();
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $orders = $query->paginate($request->integer('per_page', 10));
        $this->addProductNames($orders->getCollection());

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function show($id)
    {
        $order = $this->withItems(Order::query())->findOrFail($id);
        $this->authorize('view', $order);
        $this->addProductNames(collect([$order]));

        return response()->json([
            'data' => $order,
        ]);
    }

    private function withItems($query)
    {
        if (PerfMode::on('eager_loading')) {
            return $query->with('items.product:id,name');
        }

        return $query;
    }

    private function addProductNames($orders): void
    {
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $item->setAttribute('product_name', $item->product?->name);
                $item->unsetRelation('product');
            }
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $productIds = collect($validated['items'])->pluck('product_id')->unique()->values();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $reserveItems = [];
        $total = 0;
        foreach ($validated['items'] as $item) {
            $product = $products[$item['product_id']];
            $qty = (int) $item['quantity'];
            $reserveItems[] = [
                'product_id' => (int) $product->id,
                'quantity' => $qty,
            ];
            $total += ((float) $product->price) * $qty;
        }

        $requestId = (string) Str::uuid();

        try {
            $reservation = $this->inventoryGateway->reserve($requestId, $reserveItems);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVENTORY_UNAVAILABLE',
                    'message' => 'خدمة المخزون غير متاحة حالياً، حاول لاحقاً',
                    'details' => $e->getMessage(),
                ],
            ], 503);
        }

        if (! ($reservation['success'] ?? false)) {
            return response()->json([
                'error' => [
                    'code' => 'INSUFFICIENT_STOCK',
                    'message' => 'الكمية غير كافية لبعض المنتجات',
                    'shortages' => $reservation['shortages'] ?? [],
                ],
            ], 409);
        }

        $reservationId = $reservation['reservation_id'] ?? $reservation['reservationId'] ?? null;
        if (! $reservationId) {
            return response()->json([
                'error' => [
                    'code' => 'INVENTORY_UNAVAILABLE',
                    'message' => 'تم الحجز لكن لم يُرجع رقم الحجز من خدمة المخزون',
                ],
            ], 503);
        }
        try {
            $order = DB::transaction(function () use ($request, $validated, $products, $total, $reservationId, $productIds) {
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'status' => 'PENDING',
                    'reservation_id' => $reservationId,
                    'total_amount' => $total,
                ]);

                foreach ($validated['items'] as $item) {
                    $product = $products[$item['product_id']];
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => (int) $item['quantity'],
                        'unit_price' => $product->price,
                    ]);
                }

                DB::table('notifications')->insert([
                    'user_id' => $request->user()->id,
                    'type' => 'OrderCreated',
                    'message' => 'تم إنشاء طلبك رقم #' . $order->id . ' بنجاح وهو قيد المعالجة.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                try {
                    $stocks = $this->inventoryGateway->getStock($productIds->toArray());
                    foreach ($validated['items'] as $item) {
                        $prodId = $item['product_id'];
                        if (isset($stocks[$prodId]) && $stocks[$prodId] <= 0) {
                            $productName = $products[$prodId]->name ?? "المنتج #{$prodId}";
                            $admins = \App\Models\User::where('role', 'admin')->get();

                            foreach ($admins as $admin) {
                                DB::table('notifications')->insert([
                                    'user_id' => $admin->id,
                                    'type' => 'ProductOutOfStock',
                                    'message' => "تنبيه هام: نفدت كمية المنتج \"{$productName}\" تماماً من المخزون وأصبحت صفراً!",
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                }

                return $order->load('items');
            });
        } catch (\Throwable $e) {
            try {
                $this->inventoryGateway->release($reservationId);
            } catch (\Throwable) {
            }

            return response()->json([
                'error' => [
                    'code' => 'ORDER_SAVE_FAILED',
                    'message' => 'فشل حفظ الطلب وتم إرجاع الكمية المحجوزة',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }

        return response()->json([
            'message' => 'تم إنشاء الطلب بنجاح',
            'data' => $order,
        ], 201);
    }

    public function cancel(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('view', $order);

        if ($order->status === 'CANCELLED') {
            return response()->json([
                'message' => 'الطلب ملغي مسبقاً',
                'data' => $order,
            ]);
        }

        try {
            DB::transaction(function () use ($order) {
                if ($order->reservation_id) {
                    $released = $this->inventoryGateway->release($order->reservation_id);
                    if (! $released) {
                        throw new RuntimeException('Inventory release returned false for reservation '.$order->reservation_id);
                    }
                }
                $order->update(['status' => 'CANCELLED']);

                DB::table('notifications')->insert([
                    'user_id' => $order->user_id,
                    'type' => 'OrderCancelled',
                    'message' => 'تم إلغاء الطلب رقم #'.$order->id.' بنجاح وإرجاع المخزون.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVENTORY_UNAVAILABLE',
                    'message' => 'تعذر إرجاع المخزون، لم يتم إلغاء الطلب',
                    'details' => $e->getMessage(),
                ],
            ], 503);
        }

        return response()->json([
            'message' => 'تم إلغاء الطلب وإرجاع المخزون',
            'data' => $order->fresh('items'),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('updateStatus', $order);

        $request->validate([
            'status' => 'required|in:PENDING,CONFIRMED,SHIPPED,DELIVERED,CANCELLED',
        ]);

        $newStatus = $request->status;

        try {
            DB::transaction(function () use ($order, $newStatus) {
                if ($newStatus === 'CANCELLED' && $order->status !== 'CANCELLED') {
                    if ($order->reservation_id) {
                        $released = $this->inventoryGateway->release($order->reservation_id);
                        if (! $released) {
                            throw new RuntimeException('Inventory release returned false for reservation '.$order->reservation_id);
                        }
                    }
                }

                $order->update([
                    'status' => $newStatus,
                ]);

                DB::table('notifications')->insert([
                    'user_id' => $order->user_id,
                    'type' => 'OrderStatusUpdated',
                    'message' => 'تم تغيير حالة طلبك رقم #'.$order->id.' إلى: '.$newStatus,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVENTORY_UNAVAILABLE',
                    'message' => 'تعذر إرجاع المخزون، لم يتم تحديث حالة الطلب',
                    'details' => $e->getMessage(),
                ],
            ], 503);
        }

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => $order->fresh('items'),
        ]);
    }
}