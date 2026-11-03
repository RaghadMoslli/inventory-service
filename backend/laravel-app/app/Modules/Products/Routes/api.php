<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Products\Controllers\ProductController;

// مسارات عامة للمنتجات
Route::get('products', [ProductController::class, 'index']); // <-- أضفنا Route:: قبل الـ get
Route::get('products/{id}', [ProductController::class, 'show']);

// مسارات محمية للـ Admin فقط
Route::post('products', [ProductController::class, 'store'])->middleware(['auth:api', 'role:admin']);