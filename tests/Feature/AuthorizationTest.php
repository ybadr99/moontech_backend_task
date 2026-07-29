<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_protected_admin_endpoints(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/products');

        $response->assertStatus(200);
    }

    public function test_regular_user_receives_403_for_admin_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('user-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/products');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_receives_401_for_admin_endpoints(): void
    {
        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(401);
    }

    public function test_users_are_assigned_correct_role_on_registration(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Role Test',
            'phone' => '1112223333',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'phone' => '1112223333',
            'role' => 'user',
        ]);
    }

    public function test_admin_user_has_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertEquals('admin', $admin->role);
        $this->assertTrue($admin->isAdmin());
    }

    public function test_regular_user_does_not_have_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->assertEquals('user', $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_role_middleware_blocks_non_admin_post_request(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('user-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/products', [
                'title' => 'Hacked Product',
                'description' => 'Should not be created',
                'price' => 1.99,
                'stock' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_product(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/products', [
                'title' => 'Admin Product',
                'description' => 'Created by admin',
                'price' => 29.99,
                'stock' => 10,
            ]);

        $response->assertStatus(201);
    }

    public function test_admin_can_update_product(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/admin/products/{$product->id}", [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(200);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_unauthenticated_user_cannot_access_any_admin_route(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/admin/products')->assertStatus(401);
        $this->postJson('/api/admin/products')->assertStatus(401);
        $this->getJson("/api/admin/products/{$product->id}")->assertStatus(401);
        $this->putJson("/api/admin/products/{$product->id}")->assertStatus(401);
        $this->deleteJson("/api/admin/products/{$product->id}")->assertStatus(401);
    }
}
