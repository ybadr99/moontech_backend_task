<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // User::factory()->create([
        //     'name' => env('ADMIN_NAME', 'Admin'),
        //     'phone' => env('ADMIN_PHONE', '123123123123'),
        //     'password' => env('ADMIN_PASSWORD', 'password'),
        //     'role' => 'admin',
        // ]);

        User::factory()->create([
            'name' => "User",
            'phone' => "888999000",
            'password' => "123123123",
            'role' => 'user',
        ]);
    }
}
