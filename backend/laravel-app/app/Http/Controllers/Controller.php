<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "E-commerce Microservices API",
    description: "API Documentation for Laravel & Java gRPC Inventory microservices"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
#[OA\PathItem(
    path: "/api"
)]
class Controller
{
    //
}