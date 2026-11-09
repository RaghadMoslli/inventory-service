<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Annotations as OA;

class ProductController extends Controller
{
    protected StockProvider $stockProvider;

    public function __construct(StockProvider $stockProvider)
    {
        $this->stockProvider = $stockProvider;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/products",
     *      summary="جلب قائمة المنتجات مع الكمية المتوفرة",
     *      tags={"Products"},
     *      @OA\Parameter(
     *          name="category",
     *          in="query",
     *          required=false,
     *          description="تصفية المنتجات حسب التصنيف",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          required=false,
     *          description="عدد العناصر في الصفحة الواحدة",
     *          @OA\Schema(type="integer", default=10)
     *      ),
     *      @OA\Response(response=200, description="تم جلب قائمة المنتجات بنجاح"),
     *      @OA\Response(response=503, description="خدمة المخزون غير متاحة حالياً")
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $category = $request->get('category', 'all');
        $page = $request->get('page', 1);

        // مفتاح كاش فريد يعتمد على التصنيف وعدد العناصر ورقم الصفحة
        $cacheKey = "products_page_{$page}_{$perPage}_category_{$category}";

        // **تخزين القائمة في Redis باستخدام Cache::remember (لمدة 60 دقيقة)**
        $products = Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = Product::query();

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            return $query->orderBy('id')->paginate($request->get('per_page', 10));
        });
        
        $productIds = $products->pluck('id')->toArray();
        
        try {
            $stocks = $this->stockProvider->getStock($productIds);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVENTORY_UNAVAILABLE',
                    'message' => 'خدمة المخزون غير متاحة حالياً، حاول لاحقاً',
                    'details' => $e->getMessage(),
                ],
            ], 503);
        }

        $products->getCollection()->transform(function ($product) use ($stocks) {
            $product->available_quantity = $stocks[$product->id] ?? 0;
            return $product;
        });

        return response()->json($products);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/products/{id}",
     *      summary="عرض تفاصيل منتج واحد مع كميته المتوفرة",
     *      tags={"Products"},
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="معرف المنتج",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(response=200, description="تم جلب تفاصيل المنتج بنجاح"),
     *      @OA\Response(response=404, description="المنتج غير موجود"),
     *      @OA\Response(response=503, description="خدمة المخزون غير متاحة حالياً")
     * )
     */
    public function show($id)
    {
        // **تخزين تفاصيل المنتج الواحد أيضاً في Redis**
        $product = Cache::remember("product_{$id}", 3600, function () use ($id) {
            return Product::findOrFail($id);
        });

        try {
            $stocks = $this->stockProvider->getStock([$product->id]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVENTORY_UNAVAILABLE',
                    'message' => 'خدمة المخزون غير متاحة حالياً، حاول لاحقاً',
                    'details' => $e->getMessage(),
                ],
            ], 503);
        }

        $product->available_quantity = $stocks[$product->id] ?? 0;

        return response()->json([
            'data' => $product
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/v1/products",
     *      summary="إضافة منتج جديد وتحديد كميته الأولية (للمشرفين)",
     *      tags={"Products"},
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  required={"name", "price", "category", "initial_stock"},
     *                  @OA\Property(property="name", type="string", example="هاتف ذكي"),
     *                  @OA\Property(property="description", type="string", example="وصف المنتج هنا"),
     *                  @OA\Property(property="price", type="number", format="float", example=499.99),
     *                  @OA\Property(property="category", type="string", example="Electronics"),
     *                  @OA\Property(property="image", type="string", format="binary", description="صورة المنتج"),
     *                  @OA\Property(property="initial_stock", type="integer", example=50)
     *              )
     *          )
     *      ),
     *      @OA\Response(response=201, description="تم إنشاء المنتج بنجاح"),
     *      @OA\Response(response=422, description="خطأ في التحقق من صحة البيانات"),
     *      @OA\Response(response=503, description="تم إلغاء إنشاء المنتج لأن خدمة المخزون غير متاحة")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string',
            'image' => 'nullable',
            'initial_stock' => 'required|integer|min:0',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'category' => $validated['category'],
            'image_path' => $imagePath,
        ]);

        try {
            $this->stockProvider->setStock($product->id, $validated['initial_stock']);
        } catch (\RuntimeException $e) {
            $product->delete();

            return response()->json([
                'error' => [
                    'code' => 'INVENTORY_UNAVAILABLE',
                    'message' => 'تم إلغاء إنشاء المنتج لأن خدمة المخزون غير متاحة',
                    'details' => $e->getMessage(),
                ],
            ], 503);
        }

        // إبطال الكاش فوراً عند إضافة منتج جديد
        Cache::flush();

        return response()->json([
            'message' => 'تم إنشاء المنتج بنجاح',
            'data' => $product
        ], 201);
    }

    /**
     * @OA\Patch(
     *      path="/api/products/{id}",
     *      summary="تعديل منتج (للأدمن)",
     *      tags={"Products"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="تم التعديل بنجاح")
     * )
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|string',
        ]);

        $product->update($validated);

        // إبطال الكاش فوراً عند التعديل
        Cache::forget('product_' . $id);
        Cache::flush();

        return response()->json([
            'message' => 'تم تحديث المنتج بنجاح وإبطال الكاش',
            'data' => $product
        ]);
    }

    /**
     * @OA\Delete(
     *      path="/api/products/{id}",
     *      summary="حذف منتج (للأدمن)",
     *      tags={"Products"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="تم الحذف بنجاح")
     * )
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        
        $product->delete();

        // إبطال الكاش فوراً عند الحذف
        Cache::forget('product_' . $id);
        Cache::flush();

        return response()->json([
            'message' => 'تم حذف المنتج بنجاح وإبطال الكاش'
        ]);
    }
}