<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="E-commerce Microservices API",
 *      description="API Documentation for Laravel & Java gRPC Inventory microservices"
 * )
 *
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT"
 * )
 */
class SwaggerInfo
{
    // هذا الملف مخصص فقط لتعريف إعدادات السويغر العامة ولن يتم استخدامه كـ Controller فعلي
}