<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/notifications",
     *      summary="عرض قائمة الإشعارات الخاصة بالمستخدم",
     *      tags={"Notifications"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="تم جلب الإشعارات بنجاح"
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="غير مصرح"
     *      )
     * )
     */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($notifications);
    }

    /**
     * @OA\Patch(
     *      path="/api/notifications/{id}/read",
     *      summary="تحديد الإشعار كمقروء",
     *      tags={"Notifications"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="معرف الإشعار",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="تم تحديث الإشعار بنجاح"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="الإشعار غير موجود"
     *      )
     * )
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم تحديث الإشعار بنجاح',
            'data' => $notification
        ]);
    }
}