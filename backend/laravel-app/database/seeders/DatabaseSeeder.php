<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إنشاء حساب Admin افتراضي
        User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        // 2. إنشاء حساب Customer افتراضي
        User::firstOrCreate(
            ['email' => 'customer@test.com'],
            [
                'name' => 'Test Customer',
                'password' => Hash::make('password123'),
                'role' => 'customer',
            ]
        );

        // 3. إنشاء 15 منتجاً تجريبياً بتصنيفات مختلفة
        $categories = ['Electronics', 'Clothing', 'Books', 'Home'];

        for ($i = 1; $i <= 15; $i++) {
            Product::create([
                'name' => "منتج تجريبي رقم $i",
                'description' => "هذا وصف تفصيلي للمنتج التجريبي رقم $i يوضح ميزاته وخصائصه.",
                'price' => rand(10, 500) + 0.99,
                'category' => $categories[array_rand($categories)],
                'image_path' => null,
            ]);
        }
    }
}