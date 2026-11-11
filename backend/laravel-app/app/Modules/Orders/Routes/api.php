<?php

use App\Modules\Orders\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// مسارات الطلبات المحمية بالمصادقة (JWT)
Route::middleware('auth:api')->group(function () {
    
    // عرض قائمة الطلبات الخاصة بالمستخدم أو الإدارة
    Route::get('/orders', [OrderController::class, 'index']);
    
    // إنشاء طلب جديد
    Route::post('/orders', [OrderController::class, 'store']);
    
    // عرض تفاصيل طلب معين (محمي بالـ OrderPolicy)
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    
    // إلغاء الطلب (مع إعادة المخزون وتأكيد الـ reservation_id)
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    
    // تحديث حالة الطلب (مخصصة للـ Admin حصراً عبر وسيط الـ role)
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware('role:admin');

        
});