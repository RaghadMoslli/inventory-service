<?php

use Illuminate\Support\Facades\Route;
use L5Swagger\Http\Controllers\SwaggerController;

// مسار توليد ملف الـ JSON الخاص بالحزمة
Route::get('docs', [SwaggerController::class, 'docs'])->name('l5-swagger.default.docs');

// مسار واجهة العرض المخصصة (Swagger UI)
Route::get('documentation', function () {
    return view('swagger');
});