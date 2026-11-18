<?php

namespace App\Modules\Orders\Controllers;

use App\Events\OrderCreated;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Services\OrderDecisionService;
use App\Modules\Orders\Support\OrderFeedPayload;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Models\Product;
use App\Support\PerfMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Annotations as OA;
use App\Modules\Orders\Models\OrderStatusHistory;
use RuntimeException;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected StockProvider $inventoryGateway,
        protected OrderDecisionService $orderDecisions
    ) {
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


public function history($id)
{
    $order = Order::query()->findOrFail($id);

    $this->authorize('view', $order);

    $history = $order->statusHistories()
        ->with('changedBy:id,name')
        ->get()
        ->map(function ($entry) {
            return [
                'id' => $entry->id,
                'old_status' => $entry->old_status,
                'new_status' => $entry->new_status,
                'note' => $entry->note,
                'changed_by' => $entry->changedBy?->name,
                'created_at' => $entry->created_at,
            ];
        });

    return response()->json([
        'data' => $history,
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

                OrderStatusHistory::create([
    'order_id' => $order->id,
    'old_status' => null,
    'new_status' => 'PENDING',
    'changed_by' => $request->user()->id,
    'note' => 'تم إنشاء الطلب',
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

        event(new OrderCreated($order));

        return response()->json([
            'message' => 'تم إنشاء الطلب بنجاح',
            'data' => $order,
        ], 201);
    }

    /**
     * @OA\Patch(
     *      path="/api/v1/orders/{id}/cancel",
     *      summary="إلغاء الطلب (من PENDING أو CONFIRMED فقط) وإرجاع المخزون",
     *      tags={"Orders"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="تم الإلغاء أو الطلب ملغي مسبقاً"),
     *      @OA\Response(response=403, description="ليس طلبك"),
     *      @OA\Response(response=404, description="الطلب غير موجود"),
     *      @OA\Response(response=422, description="لا يمكن الإلغاء بعد الشحن أو التسليم"),
     *      @OA\Response(response=503, description="تعذّر إرجاع المخزون")
     * )
     */
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
            $order = $this->orderDecisions->changeStatus(
                (int) $id,
                'CANCELLED',
                $request->user(),
                null,
                'OrderCancelled',
                'تم إلغاء الطلب رقم #'.$id.' بنجاح وإرجاع المخزون.'
            );
        } catch (InvalidOrderTransitionException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TRANSITION',
                    'message' => 'لا يمكن إلغاء الطلب بعد شحنه أو تسليمه',
                    'current_status' => $e->from,
                ],
            ], 422);
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

       /**
     * @OA\Patch(
     *      path="/api/v1/orders/{id}/status",
     *      summary="تغيير حالة الطلب (أدمن فقط، للأمام بدون قفز)",
     *      tags={"Orders"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\RequestBody(required=true, @OA\JsonContent(
     *          required={"status"},
     *          @OA\Property(property="status", type="string", enum={"CONFIRMED","SHIPPED","DELIVERED","CANCELLED"}),
     *          @OA\Property(property="note", type="string", nullable=true)
     *      )),
     *      @OA\Response(response=200, description="تم التحديث"),
     *      @OA\Response(response=403, description="ليس أدمن"),
     *      @OA\Response(response=404, description="الطلب غير موجود"),
     *      @OA\Response(response=422, description="انتقال غير مسموح"),
     *      @OA\Response(response=503, description="تعذّر إرجاع المخزون")
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('updateStatus', $order);

        $validated = $request->validate([
            'status' => 'required|in:PENDING,CONFIRMED,SHIPPED,DELIVERED,CANCELLED',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $order = $this->orderDecisions->changeStatus(
                (int) $id,
                $validated['status'],
                $request->user(),
                $validated['note'] ?? null
            );
        } catch (InvalidOrderTransitionException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TRANSITION',
                    'message' => 'انتقال حالة الطلب غير مسموح',
                    'current_status' => $e->from,
                ],
            ], 422);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVENTORY_UNAVAILABLE',
                    'message' => 'تعذر إرجاع المخزون، لم يتم تحديث حالة الطلب',
                    'details' => $e->getMessage(),
                ],
            ], 503);
        }

        // يحتوي على حقول الطلب العادية (id) وحقول الصفحة اللحظية (order_id) معاً
        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => array_merge($order->fresh('items')->toArray(), OrderFeedPayload::from($order)),
        ]);
    }
}