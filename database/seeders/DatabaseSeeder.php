<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => "Admin",
            'phone' => "01001234567",
            'password' => "password",
            'role' => 'admin',
        ]);
        
        User::factory()->create([
            'name' => "User",
            'phone' => "01101234567",
            'password' => "password",
            'role' => 'user',
        ]);
        
        $products = [
            [
                'title' => 'Wireless Headphones',
                'price' => 59.99,
                'description' => 'Over-ear wireless headphones with noise cancellation and 30-hour battery life.',
                'stock' => 25,
                'image' => 'products/headphones.jpg',
            ],
            [
                'title' => 'Running Shoes',
                'price' => 79.50,
                'description' => 'Lightweight running shoes with breathable mesh upper and cushioned sole.',
                'stock' => 0,
                'image' => 'products/shoes.jpg',
            ],
            [
                'title' => 'Smart Watch',
                'price' => 129.00,
                'description' => 'Fitness tracking smart watch with heart rate monitor and 7-day battery life.',
                'stock' => 10,
                'image' => 'products/smartwatch.jpg',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
    



}
