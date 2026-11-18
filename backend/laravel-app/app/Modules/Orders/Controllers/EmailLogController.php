<?php

namespace App\Modules\Orders\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderEmailJob;
use App\Modules\Orders\Models\EmailLog;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class EmailLogController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/orders/{id}/email-status",
     *      summary="حالة إيميل تأكيد الطلب",
     *      tags={"Emails"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="حالة الإيميل"),
     *      @OA\Response(response=403, description="غير مصرح"),
     *      @OA\Response(response=404, description="لا يوجد سجل إيميل")
     * )
     */
    public function orderStatus(Request $request, int $id)
    {
        $order = Order::query()->findOrFail($id);
        $user = $request->user();

        if ($user->role !== 'admin' && (int) $order->user_id !== (int) $user->id) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'ليس لديك صلاحية للقيام بهذه العملية',
                ],
            ], 403);
        }

        $log = EmailLog::query()
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        if (! $log) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'لا يوجد سجل إيميل لهذا الطلب',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'email_log_id' => $log->id,
                'order_id' => $log->order_id,
                'to' => $log->to,
                'status' => $log->status,
                'attempts' => $log->attempts,
                'error' => $log->error,
                'sent_at' => $log->sent_at,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/admin/email-logs",
     *      summary="سجل إيميلات الطلبات (مسؤول)",
     *      tags={"Emails"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
     *      @OA\Response(response=200, description="قائمة السجلات"),
     *      @OA\Response(response=403, description="غير مصرح")
     * )
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $query = EmailLog::query()->with('order:id,user_id,status,total_amount')->latest('id');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $logs = $query->paginate($request->integer('per_page', 20));

        $counts = EmailLog::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'data' => $logs,
            'stats' => [
                'queued' => (int) ($counts[EmailLog::STATUS_QUEUED] ?? 0),
                'sent' => (int) ($counts[EmailLog::STATUS_SENT] ?? 0),
                'failed' => (int) ($counts[EmailLog::STATUS_FAILED] ?? 0),
                'dead' => (int) ($counts[EmailLog::STATUS_DEAD] ?? 0),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/v1/admin/email-logs/{id}/retry",
     *      summary="إعادة إرسال إيميل ميت من الـ DLQ",
     *      tags={"Emails"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="أُعيدت المهمة للطابور"),
     *      @OA\Response(response=422, description="السجل ليس بحالة dead")
     * )
     */
    public function retry(int $id)
    {
        $log = EmailLog::query()->findOrFail($id);

        if (! $log->isDead()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'يمكن إعادة الإرسال فقط للإيميلات ذات الحالة dead',
                ],
            ], 422);
        }

        $log->update([
            'status' => EmailLog::STATUS_QUEUED,
            'error' => null,
        ]);

        SendOrderEmailJob::dispatch($log->id)->onQueue('emails');

        return response()->json([
            'message' => 'تمت إعادة نشر مهمة الإيميل على الطابور',
            'data' => $log->fresh(),
        ]);
    }
}
