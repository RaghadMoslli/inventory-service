<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Products\Controllers\ProductController;

Route::get('products', [ProductController::class, 'index'])->middleware('etag');
Route::get('products/{id}', [ProductController::class, 'show'])->middleware('etag');

Route::post('products', [ProductController::class, 'store'])->middleware(['auth:api', 'role:admin']);
Route::patch('products/{id}', [ProductController::class, 'update'])->middleware(['auth:api', 'role:admin']);
Route::delete('products/{id}', [ProductController::class, 'destroy'])->middleware(['auth:api', 'role:admin']);