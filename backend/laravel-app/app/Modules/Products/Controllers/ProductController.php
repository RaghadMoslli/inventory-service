<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected StockProvider $stockProvider;

    // حقن الـ StockProvider عبر الـ Constructor
    public function __construct(StockProvider $stockProvider)
    {
        $this->stockProvider = $stockProvider;
    }

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
