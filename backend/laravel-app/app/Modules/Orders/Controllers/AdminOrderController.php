<?php

namespace App\Modules\Orders\Controllers;

use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderDecisionService;
use App\Modules\Orders\Support\OrderFeedPayload;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RuntimeException;

class AdminOrderController extends Controller
{
    public function __construct(protected OrderDecisionService $decisions)
    {
    }

    /**
     * @OA\Patch(
     *      path="/api/v1/admin/orders/{id}/status",
     *      summary="موافقة المسؤول على الطلب أو رفضه (مرة واحدة فقط)",
     *      tags={"Admin Orders"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\RequestBody(required=true, @OA\JsonContent(
     *          required={"status"},
     *          @OA\Property(property="status", type="string", enum={"approved","rejected"}),
     *          @OA\Property(property="note", type="string", nullable=true)
     *      )),
     *      @OA\Response(response=200, description="تم القرار"),
     *      @OA\Response(response=403, description="ليس مسؤولاً"),
     *      @OA\Response(response=404, description="الطلب غير موجود"),
     *      @OA\Response(response=422, description="قيمة غير معروفة أو الطلب ليس PENDING"),
     *      @OA\Response(response=503, description="تعذّر إرجاع المخزون عند الرفض")
     * )
     */
    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'note' => 'nullable|string|max:500',
        ]);

        // 404 إن لم يوجد الطلب (قبل أي منطق)
        Order::query()->findOrFail($id);

        try {
            $order = $this->decisions->decide($id, $data['status'], $request->user(), $data['note'] ?? null);
        } catch (InvalidOrderTransitionException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TRANSITION',
                    'message' => 'لا يمكن تغيير حالة هذا الطلب، تم اتخاذ قرار عليه مسبقاً',
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

        return response()->json([
            'message' => $data['status'] === 'approved' ? 'تمت الموافقة على الطلب' : 'تم رفض الطلب',
            'data' => OrderFeedPayload::from($order),
        ]);
    }
}
