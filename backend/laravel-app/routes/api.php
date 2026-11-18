<?php

use Illuminate\Support\Facades\Route;
use L5Swagger\Http\Controllers\SwaggerController;

Route::get('docs', [SwaggerController::class, 'docs'])->name('l5-swagger.default.docs');

Route::get('documentation', function () {
    return view('swagger');
});
