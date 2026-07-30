<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_lock_for_update_is_used_to_prevent_race_conditions(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'price' => 10]);
        $token = $this->userToken();

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);

        $hasForUpdate = collect($queries)->contains(
            fn ($sql) => str_contains(strtolower($sql), 'for update')
        );

        $this->assertTrue($hasForUpdate);
    }

    public function test_transaction_rolls_back_when_midway_product_has_insufficient_stock(): void
    {
        $token = $this->userToken();
        $product1 = Product::factory()->create(['stock' => 5, 'price' => 10.00]);
        $product2 = Product::factory()->create(['stock' => 1, 'price' => 20.00]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product1->id, 'quantity' => 2],
                    ['product_id' => $product2->id, 'quantity' => 5],
                ],
            ]);

        $response->assertStatus(409);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertEquals(5, $product1->fresh()->stock);
        $this->assertEquals(1, $product2->fresh()->stock);
    }


    public function test_concurrent_order_attempts_do_not_oversell_inventory(): void
    {
        $token = $this->userToken();
        $product = Product::factory()->create(['stock' => 2, 'price' => 10.00]);

        $response1 = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response1->assertStatus(201);
        $this->assertEquals(0, $product->fresh()->stock);

        $response2 = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response2->assertStatus(409);
        $this->assertEquals(0, $product->fresh()->stock);
    }


}
