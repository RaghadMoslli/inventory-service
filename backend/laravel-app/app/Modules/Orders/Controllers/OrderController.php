<?php

namespace App\Modules\Orders\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Contracts\StockProvider; // أو InventoryGateway حسب التسمية لديكم
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected StockProvider $inventoryGateway; // أو InventoryGateway

    public function __construct(StockProvider $inventoryGateway)
    {
        $this->inventoryGateway = $inventoryGateway;
    }

    // عرض تفاصيل الطلب (مع التحقق من السياسة: المالك أو الأدمن فقط)
    public function show($id)
    {
        $order = Order::with('items')->findOrFail($id);

        $this->authorize('view', $order);

        return response()->json([
            'data' => $order
        ]);
    }

    // تعديل حالة الطلب (للأدمن فقط)
    public function updateStatus(Request $request, $id)
{
    $order = Order::findOrFail($id);

    $this->authorize('updateStatus', $order);

    $request->validate([
        'status' => 'required|in:PENDING,CONFIRMED,SHIPPED,DELIVERED,CANCELLED'
    ]);

    $newStatus = $request->status;

    // استخدام DB::transaction لضمان التزامن والسلامة
    DB::transaction(function () use ($order, $newStatus) {
        // إذا تم تغيير الحالة إلى CANCELLED، يتم إرجاع المخزون أولاً
        if ($newStatus === 'CANCELLED' && $order->status !== 'CANCELLED') {
            if ($order->reservation_id) {
                $this->inventoryGateway->release($order->reservation_id);
            }
        }

        $order->update([
            'status' => $newStatus
        ]);
    });

    return response()->json([
        'message' => 'تم تحديث حالة الطلب بنجاح',
        'data' => $order
    ]);
}
}