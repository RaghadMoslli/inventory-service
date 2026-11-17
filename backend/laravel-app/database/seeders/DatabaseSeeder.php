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
        User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'customer@test.com'],
            [
                'name' => 'Test Customer',
                'password' => Hash::make('password123'),
                'role' => 'customer',
            ]
        );

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
