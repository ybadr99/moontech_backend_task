<?php

namespace Tests\Feature;

use App\Events\ProductBackInStock;
use App\Models\Product;
use App\Models\StockSubscription;
use App\Models\User;
use App\Notifications\ProductBackInStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StockSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function userToken(): string
    {
        return User::factory()->create()->createToken('user-token')->plainTextToken;
    }

    private function adminToken(): string
    {
        return User::factory()->admin()->create()->createToken('admin-token')->plainTextToken;
    }

    public function test_subscribing_to_in_stock_product_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken())
            ->postJson("/api/products/{$product->id}/notify-me");

        $response->assertStatus(422)
            ->assertExactJson(['message' => 'Product is already in stock.']);
    }

    public function test_subscribing_twice_is_idempotent(): void
    {
        $product = Product::factory()->create(['stock' => 0]);
        $token = $this->userToken();

        $first = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/products/{$product->id}/notify-me");

        $first->assertStatus(200)
            ->assertExactJson(['message' => 'You will be notified when this product is back in stock.']);

        $second = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/products/{$product->id}/notify-me");

        $second->assertStatus(200)
            ->assertExactJson(['message' => 'You are already subscribed to this product.']);

        $this->assertDatabaseCount('stock_subscriptions', 1);
    }

    public function test_restock_notifies_only_subscribed_users(): void
    {
        Notification::fake();

        $product = Product::factory()->create(['stock' => 0]);

        $subscribedUsers = User::factory()->count(2)->create();
        $unsubscribedUser = User::factory()->create();

        foreach ($subscribedUsers as $user) {
            StockSubscription::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
        }

        $token = $this->adminToken();
        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/products/{$product->id}", [
                'stock' => 15,
            ]);

        foreach ($subscribedUsers as $user) {
            Notification::assertSentTo(
                $user,
                ProductBackInStockNotification::class,
                fn (ProductBackInStockNotification $notification) => $notification->product->id === $product->id,
            );
        }

        Notification::assertNotSentTo(
            $unsubscribedUser,
            ProductBackInStockNotification::class,
        );
    }

    public function test_notified_users_are_not_notified_again_for_same_restock(): void
    {
        Notification::fake();

        $product = Product::factory()->create(['stock' => 0]);
        $user = User::factory()->create();

        StockSubscription::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $token = $this->adminToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/products/{$product->id}", [
                'stock' => 10,
            ]);

        Notification::assertSentTo($user, ProductBackInStockNotification::class);

        $this->assertDatabaseHas('stock_subscriptions', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'notified_at' => now(),
        ]);

        Notification::fake();

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/products/{$product->id}", [
                'stock' => 0,
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/products/{$product->id}", [
                'stock' => 5,
            ]);

        Notification::assertNothingSent();
    }
}
