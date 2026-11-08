<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Documentation - Swagger UI</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        body { background: #fafafa; margin: 0; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js" charset="UTF-8"></script>
    <script>
        window.onload = function() {
            window.ui = SwaggerUIBundle({
                spec: {
                    "openapi": "3.0.0",
                    "info": {
                        "title": "E-commerce Microservices API",
                        "description": "API Documentation for Laravel & Java gRPC Inventory microservices",
                        "version": "1.0.0"
                    },
                    "servers": [
                        {
                            "url": "http://localhost:8000"
                        }
                    ],
                    "paths": {
                        "/api/auth/register": {
                            "post": {
                                "tags": ["Auth"],
                                "summary": "تسجيل مستخدم جديد",
                                "requestBody": {
                                    "required": true,
                                    "content": {
                                        "application/json": {
                                            "schema": {
                                                "type": "object",
                                                "required": ["name", "email", "password"],
                                                "properties": {
                                                    "name": {"type": "string", "example": "أحمد محمد"},
                                                    "email": {"type": "string", "format": "email", "example": "ahmad@example.com"},
                                                    "password": {"type": "string", "format": "password", "example": "secret12345"}
                                                }
                                            }
                                        }
                                    }
                                },
                                "responses": {
                                    "201": {"description": "تم إنشاء الحساب بنجاح"},
                                    "422": {"description": "خطأ في التحقق من صحة البيانات"}
                                }
                            }
                        },
                        "/api/auth/login": {
                            "post": {
                                "tags": ["Auth"],
                                "summary": "تسجيل الدخول للمستخدم",
                                "requestBody": {
                                    "required": true,
                                    "content": {
                                        "application/json": {
                                            "schema": {
                                                "type": "object",
                                                "required": ["email", "password"],
                                                "properties": {
                                                    "email": {"type": "string", "format": "email", "example": "ahmad@example.com"},
                                                    "password": {"type": "string", "format": "password", "example": "secret12345"}
                                                }
                                            }
                                        }
                                    }
                                },
                                "responses": {
                                    "200": {"description": "تم تسجيل الدخول بنجاح وإرجاع التوكن"},
                                    "401": {"description": "بيانات الاعتماد غير صحيحة"}
                                }
                            }
                        },
                        "/api/auth/me": {
                            "get": {
                                "tags": ["Auth"],
                                "summary": "جلب بيانات المستخدم الحالي",
                                "security": [{"bearerAuth": []}],
                                "responses": {
                                    "200": {"description": "تم جلب البيانات بنجاح"},
                                    "401": {"description": "غير مصرح"}
                                }
                            }
                        },
                        "/api/auth/logout": {
                            "post": {
                                "tags": ["Auth"],
                                "summary": "تسجيل الخروج وإبطال التوكن",
                                "security": [{"bearerAuth": []}],
                                "responses": {
                                    "200": {"description": "تم تسجيل الخروج بنجاح"},
                                    "401": {"description": "غير مصرح"}
                                }
                            }
                        },
                        "/api/products": {
                            "get": {
                                "tags": ["Products"],
                                "summary": "جلب قائمة المنتجات مع الكمية المتوفرة",
                                "parameters": [
                                    {"name": "category", "in": "query", "required": false, "schema": {"type": "string"}},
                                    {"name": "per_page", "in": "query", "required": false, "schema": {"type": "integer", "default": 10}}
                                ],
                                "responses": {
                                    "200": {"description": "تم جلب قائمة المنتجات بنجاح"},
                                    "503": {"description": "خدمة المخزون غير متاحة حالياً"}
                                }
                            },
                            "post": {
                                "tags": ["Products"],
                                "summary": "إضافة منتج جديد (للأدمن)",
                                "security": [{"bearerAuth": []}],
                                "responses": {
                                    "201": {"description": "تم إنشاء المنتج بنجاح"}
                                }
                            }
                        },
                        "/api/products/{id}": {
                            "get": {
                                "tags": ["Products"],
                                "summary": "عرض تفاصيل منتج واحد",
                                "parameters": [
                                    {"name": "id", "in": "path", "required": true, "schema": {"type": "integer"}}
                                ],
                                "responses": {
                                    "200": {"description": "تم جلب تفاصيل المنتج بنجاح"},
                                    "404": {"description": "المنتج غير موجود"}
                                }
                            }
                        },
                        "/api/orders": {
                            "get": {
                                "tags": ["Orders"],
                                "summary": "عرض قائمة الطلبات",
                                "security": [{"bearerAuth": []}],
                                "responses": {
                                    "200": {"description": "تم جلب قائمة الطلبات بنجاح"}
                                }
                            },
                            "post": {
                                "tags": ["Orders"],
                                "summary": "إنشاء طلب جديد وحجز المخزون",
                                "security": [{"bearerAuth": []}],
                                "responses": {
                                    "201": {"description": "تم إنشاء الطلب بنجاح"},
                                    "409": {"description": "الكمية غير كافية"}
                                }
                            }
                        },
                        "/api/orders/{id}": {
                            "get": {
                                "tags": ["Orders"],
                                "summary": "عرض تفاصيل طلب محدد",
                                "security": [{"bearerAuth": []}],
                                "parameters": [
                                    {"name": "id", "in": "path", "required": true, "schema": {"type": "integer"}}
                                ],
                                "responses": {
                                    "200": {"description": "تم جلب الطلب بنجاح"}
                                }
                            }
                        },
                        "/api/orders/{id}/cancel": {
                            "post": {
                                "tags": ["Orders"],
                                "summary": "إلغاء الطلب وإرجاع المخزون",
                                "security": [{"bearerAuth": []}],
                                "parameters": [
                                    {"name": "id", "in": "path", "required": true, "schema": {"type": "integer"}}
                                ],
                                "responses": {
                                    "200": {"description": "تم إلغاء الطلب بنجاح"}
                                }
                            }
                        },
                        "/api/orders/{id}/status": {
                            "patch": {
                                "tags": ["Orders"],
                                "summary": "تحديث حالة الطلب (للمشرفين)",
                                "security": [{"bearerAuth": []}],
                                "parameters": [
                                    {"name": "id", "in": "path", "required": true, "schema": {"type": "integer"}}
                                ],
                                "responses": {
                                    "200": {"description": "تم تحديث حالة الطلب بنجاح"}
                                }
                            }
                        },
                        "/api/notifications": {
                            "get": {
                                "tags": ["Notifications"],
                                "summary": "عرض قائمة الإشعارات الخاصة بالمستخدم",
                                "security": [{"bearerAuth": []}],
                                "responses": {
                                    "200": {"description": "تم جلب الإشعارات بنجاح"}
                                }
                            }
                        },
                        "/api/notifications/{id}/read": {
                            "patch": {
                                "tags": ["Notifications"],
                                "summary": "تحديد الإشعار كمقروء",
                                "security": [{"bearerAuth": []}],
                                "parameters": [
                                    {"name": "id", "in": "path", "required": true, "schema": {"type": "string"}}
                                ],
                                "responses": {
                                    "200": {"description": "تم تحديث الإشعار بنجاح"}
                                }
                            }
                        }
                    },
                    "components": {
                        "securitySchemes": {
                            "bearerAuth": {
                                "type": "http",
                                "bearerFormat": "JWT",
                                "scheme": "bearer"
                            }
                        }
                    }
                },
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis
                ],
            });
        };
    </script>
</body>
</html>