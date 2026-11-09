<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class ProductController extends Controller
{
    protected StockProvider $stockProvider;

    // حقن الـ StockProvider عبر الـ Constructor
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
    // جلب قائمة المنتجات مع الكمية المتوفرة
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $products = $query->paginate($request->get('per_page', 10));

        // جلب معرفات المنتجات الحالية
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
    // عرض تفاصيل منتج واحد مع كميته المتوفرة
    public function show($id)
    {
        $product = Product::findOrFail($id);

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
    // إضافة منتج جديد (للأدمن)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string',
            'image' => 'nullable',
            'initial_stock' => 'required|integer|min:0', // الكمية الأولية للمخزون
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            // تخزين الصورة باسم عشوائي
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

        return response()->json([
            'message' => 'تم إنشاء المنتج بنجاح',
            'data' => $product
        ], 201);
    }
}