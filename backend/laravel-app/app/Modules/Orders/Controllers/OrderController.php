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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use RuntimeException;
use OpenApi\Annotations as OA;

class OrderController extends Controller
{
    use AuthorizesRequests;
    
    public function __construct(protected StockProvider $inventoryGateway)
    {
    }

    /**
     * @OA\Get(
     *      path="/api/v1/orders",
     *      summary="عرض قائمة الطلبات (للمستخدم أو المشرف)",
     *      tags={"Orders"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          required=false,
     *          description="عدد العناصر في الصفحة الواحدة",
     *          @OA\Schema(type="integer", default=10)
     *      ),
     *      @OA\Response(response=200, description="تم جلب قائمة الطلبات بنجاح"),
     *      @OA\Response(response=401, description="غير مصرح")
     * )
     */
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

    /**
     * @OA\Get(
     *      path="/api/v1/orders/{id}",
     *      summary="عرض تفاصيل طلب محدد",
     *      tags={"Orders"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="معرف الطلب",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(response=200, description="تم جلب الطلب بنجاح"),
     *      @OA\Response(response=403, description="غير مسموح لك بعرض هذا الطلب"),
     *      @OA\Response(response=404, description="الطلب غير موجود")
     * )
     */
    public function show($id)
    {
        $order = Order::with('items')->findOrFail($id);
        $this->authorize('view', $order);

        return response()->json([
            'data' => $order,
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/v1/orders",
     *      summary="إنشاء طلب جديد وحجز المخزون",
     *      tags={"Orders"},
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"items"},
     *              @OA\Property(
     *                  property="items",
     *                  type="array",
     *                  @OA\Items(
     *                      required={"product_id", "quantity"},
     *                      @OA\Property(property="product_id", type="integer", example=1),
     *                      @OA\Property(property="quantity", type="integer", example=2)
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(response=201, description="تم إنشاء الطلب بنجاح"),
     *      @OA\Response(response=409, description="الكمية غير كافية لبعض المنتجات"),
     *      @OA\Response(response=503, description="خدمة المخزون غير متاحة حالياً"),
     *      @OA\Response(response=500, description="فشل حفظ الطلب وتم إرجاع الكمية المحجوزة")
     * )
     */
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

                // 🔔 إنشاء إشعار للمستخدم عند نجاح إنشاء الطلب
                DB::table('notifications')->insert([
                    'user_id' => $request->user()->id,
                    'type' => 'OrderCreated',
                    'message' => 'تم إنشاء طلبك رقم #' . $order->id . ' بنجاح وهو قيد المعالجة.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 🔔 فحص المخزون المتبقي وإرسال إشعار للأدمن إذا نفد المنتج تماماً
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
                    // تجاهل خطأ فحص المخزون الثانوي لئلا يعطل العملية الأساسية
                }

                return $order->load('items');
            });
        } catch (\Throwable $e) {
            try {
                $this->inventoryGateway->release($reservationId);
            } catch (\Throwable) {
                // ignore secondary failure
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

    /**
     * @OA\Post(
     *      path="/api/v1/orders/{id}/cancel",
     *      summary="إلغاء الطلب وإرجاع المخزون",
     *      tags={"Orders"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="معرف الطلب",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(response=200, description="تم إلغاء الطلب وإرجاع المخزون بنجاح"),
     *      @OA\Response(response=404, description="الطلب غير موجود")
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

    /**
     * @OA\Patch(
     *      path="/api/v1/orders/{id}/status",
     *      summary="تحديث حالة الطلب (للمشرفين)",
     *      tags={"Orders"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="معرف الطلب",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"status"},
     *              @OA\Property(property="status", type="string", enum={"PENDING","CONFIRMED","SHIPPED","DELIVERED","CANCELLED"}, example="CONFIRMED")
     *          )
     *      ),
     *      @OA\Response(response=200, description="تم تحديث حالة الطلب بنجاح"),
     *      @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
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