<?php

namespace App\Modules\Orders\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(protected StockProvider $inventoryGateway)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::with('items')->latest();
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 10)),
        ]);
    }

    public function show($id)
    {
        $order = Order::with('items')->findOrFail($id);
        $this->authorize('view', $order);

        return response()->json([
            'data' => $order,
        ]);
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

        $reservationId = $reservation['reservation_id'];

        try {
            $order = DB::transaction(function () use ($request, $validated, $products, $total, $reservationId) {
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

                return $order->load('items');
            });
        } catch (\Throwable $e) {
            // Requirement 2: reservation succeeded but order save failed -> release
            try {
                $this->inventoryGateway->release($reservationId);
            } catch (\Throwable) {
                // ignore secondary failure; surface original error
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

        DB::transaction(function () use ($order) {
            if ($order->reservation_id) {
                $this->inventoryGateway->release($order->reservation_id);
            }
            $order->update(['status' => 'CANCELLED']);
        });

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

        DB::transaction(function () use ($order, $newStatus) {
            if ($newStatus === 'CANCELLED' && $order->status !== 'CANCELLED') {
                if ($order->reservation_id) {
                    $this->inventoryGateway->release($order->reservation_id);
                }
            }

            $order->update([
                'status' => $newStatus,
            ]);
        });

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => $order->fresh('items'),
        ]);
    }
}
