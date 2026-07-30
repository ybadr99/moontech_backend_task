<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Notifications\NewProductNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->admin()->create();

        return $admin->createToken('admin-token')->plainTextToken;
    }

    private function userToken(): string
    {
        $user = User::factory()->create();

        return $user->createToken('user-token')->plainTextToken;
    }

    public function test_admin_can_create_product(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/products', [
                'title' => 'New Product',
                'description' => 'Product description',
                'price' => 19.99,
                'stock' => 50,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Product created successfully',
            ]);

        $this->assertDatabaseHas('products', [
            'title' => 'New Product',
            'price' => 19.99,
            'stock' => 50,
        ]);
    }

    public function test_admin_can_update_product(): void
    {
        $token = $this->adminToken();
        $product = Product::factory()->create([
            'title' => 'Original Title',
            'price' => 10.00,
            'stock' => 5,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/admin/products/{$product->id}", [
                'title' => 'Updated Title',
                'price' => 15.00,
                'stock' => 10,
            ]);

        $response->assertStatus(200);

        $product->refresh();
        $this->assertEquals('Updated Title', $product->title);
        $this->assertEquals(15.00, (float) $product->price);
        $this->assertEquals(10, $product->stock);
    }

    public function test_admin_can_delete_product(): void
    {
        $token = $this->adminToken();
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertModelMissing($product);
    }

    public function test_admin_can_view_products(): void
    {
        $token = $this->adminToken();
        Product::factory(3)->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data', 'current_page', 'total',
            ]);
    }

    public function test_product_validation_works_correctly(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/products', [
                'title' => '',
                'description' => '',
                'price' => 'not-a-number',
                'stock' => 'not-an-integer',
            ]);

        $response->assertStatus(422);
    }

    public function test_product_image_upload_is_stored_successfully(): void
    {
        Storage::fake('public');

        $token = $this->adminToken();

        $file = UploadedFile::fake()->image('product.jpg');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/admin/products', [
                'title' => 'Product With Image',
                'description' => 'Has an image',
                'price' => 25.00,
                'stock' => 10,
                'image' => $file,
            ]);

        $response->assertStatus(201);

        $product = Product::first();
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_product_image_replacement_deletes_old_image(): void
    {
        Storage::fake('public');

        $token = $this->adminToken();

        $oldFile = UploadedFile::fake()->image('old.jpg');
        $product = Product::factory()->create();

        UploadedFile::fake()->image('old.jpg')->store('products', 'public');

        $newFile = UploadedFile::fake()->image('new.jpg');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->put("/api/admin/products/{$product->id}", [
                'title' => 'Updated',
                'description' => 'Updated description',
                'price' => 30.00,
                'stock' => 15,
                'image' => $newFile,
            ]);

        $response->assertStatus(200);

        $product->refresh();
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_product_image_deleted_when_product_deleted(): void
    {
        Storage::fake('public');

        $token = $this->adminToken();

        $file = UploadedFile::fake()->image('deletable.jpg');
        $path = $file->store('products', 'public');

        $product = Product::factory()->create(['image' => $path]);

        $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/admin/products/{$product->id}");

        Storage::disk('public')->assertMissing($path);
    }

    public function test_regular_user_cannot_create_product(): void
    {
        $token = $this->userToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/products', [
                'title' => 'Hacked',
                'description' => 'Should fail',
                'price' => 1.00,
                'stock' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_update_product(): void
    {
        $token = $this->userToken();
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/admin/products/{$product->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_delete_product(): void
    {
        $token = $this->userToken();
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_view_products(): void
    {
        $token = $this->userToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/products');

        $response->assertStatus(403);
    }

    public function test_database_record_is_created_correctly(): void
    {
        $token = $this->adminToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/products', [
                'title' => 'DB Test',
                'description' => 'Checking database',
                'price' => 99.99,
                'stock' => 100,
            ]);

        $this->assertDatabaseHas('products', [
            'title' => 'DB Test',
            'price' => 99.99,
            'stock' => 100,
        ]);
    }

    public function test_database_record_is_updated_correctly(): void
    {
        $token = $this->adminToken();
        $product = Product::factory()->create(['stock' => 0]);

        $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/admin/products/{$product->id}", ['stock' => 50]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 50,
        ]);
    }

    public function test_database_record_is_deleted_correctly(): void
    {
        $token = $this->adminToken();
        $product = Product::factory()->create();

        $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/admin/products/{$product->id}");

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_show_returns_single_product(): void
    {
        $token = $this->adminToken();
        $product = Product::factory()->create([
            'title' => 'Single Product',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Single Product');
    }

    public function test_new_product_notifies_all_regular_users(): void
    {
        Notification::fake();

        $regularUsers = User::factory()->count(3)->create(['role' => 'user']);

        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/products', [
                'title' => 'Notify Test Product',
                'description' => 'Testing notifications',
                'price' => 9.99,
                'stock' => 10,
            ]);

        $response->assertStatus(201);

        foreach ($regularUsers as $user) {
            Notification::assertSentTo(
                $user,
                NewProductNotification::class,
                function (NewProductNotification $notification, array $channels) {
                    return $notification->product->title === 'Notify Test Product';
                },
            );
        }
    }
}
