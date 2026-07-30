<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function userToken(): string
    {
        $user = User::factory()->create();

        return $user->createToken('user-token')->plainTextToken;
    }

    public function test_user_can_create_order_successfully(): void
    {
        $token = $this->userToken();
        $product = Product::factory()->create(['stock' => 10, 'price' => 25.00]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id', 'items', 'total', 'status', 'created_at',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'total' => 50.00,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 25.00,
            'subtotal' => 50.00,
        ]);
    }

    
    public function test_order_with_multiple_products_is_created_correctly(): void
    {
        $token = $this->userToken();
        $product1 = Product::factory()->create(['stock' => 10, 'price' => 10.00]);
        $product2 = Product::factory()->create(['stock' => 5, 'price' => 20.00]);
        $product3 = Product::factory()->create(['stock' => 3, 'price' => 30.00]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product1->id, 'quantity' => 3],
                    ['product_id' => $product2->id, 'quantity' => 2],
                    ['product_id' => $product3->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'total' => 100.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product1->id,
            'quantity' => 3,
            'price' => 10.00,
            'subtotal' => 30.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product2->id,
            'quantity' => 2,
            'price' => 20.00,
            'subtotal' => 40.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product3->id,
            'quantity' => 1,
            'price' => 30.00,
            'subtotal' => 30.00,
        ]);
    }

    public function test_validation_errors_returned_for_invalid_requests(): void
    {
        $token = $this->userToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', []);

        $response->assertStatus(422);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [],
            ]);

        $response->assertStatus(422);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => 99999, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => 1, 'quantity' => 0],
                ],
            ]);

        $response->assertStatus(422);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => 'not-a-number', 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_non_existent_products_are_rejected(): void
    {
        $token = $this->userToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => 99999, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_orders_fail_when_stock_is_insufficient(): void
    {
        $token = $this->userToken();
        $product = Product::factory()->create(['stock' => 2, 'price' => 10.00]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ]);

        $response->assertStatus(409);
    }

    public function test_stock_is_deducted_correctly(): void
    {
        $token = $this->userToken();
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ]);

        $this->assertEquals(7, $product->fresh()->stock);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $this->assertEquals(5, $product->fresh()->stock);
    }


    public function test_order_totals_are_calculated_correctly(): void
    {
        $token = $this->userToken();
        $product = Product::factory()->create(['stock' => 10, 'price' => 15.50]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 4],
                ],
            ]);

        $response->assertStatus(201);

        $response->assertJsonPath('data.total', 62);
        $response->assertJsonPath('data.items.0.subtotal', 62);
        $response->assertJsonPath('data.items.0.price', 15.5);
    }

    public function test_unauthenticated_users_cannot_create_orders(): void
    {
        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => 1, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(401);
    }


    public function test_duplicate_products_are_not_allowed(): void
    {
        $token = $this->userToken();
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ]);

        $response->assertStatus(422);
    }
}
