<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Products\Controllers\ProductController;

// مسارات عامة للمنتجات (القراءة) — middleware 'etag' يضيف ETag ويرد 304 عند عدم التغيّر
Route::get('products', [ProductController::class, 'index'])->middleware('etag');
Route::get('products/{id}', [ProductController::class, 'show'])->middleware('etag');

// مسارات محمية للـ Admin فقط
Route::post('products', [ProductController::class, 'store'])->middleware(['auth:api', 'role:admin']);
Route::patch('products/{id}', [ProductController::class, 'update'])->middleware(['auth:api', 'role:admin']);
Route::delete('products/{id}', [ProductController::class, 'destroy'])->middleware(['auth:api', 'role:admin']);
