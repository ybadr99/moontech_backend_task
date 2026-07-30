<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderStatusTest extends TestCase
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

    public function test_admin_can_update_order_status(): void
    {
        $token = $this->adminToken();
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.current_status', 'confirmed')
            ->assertJsonPath('data.previous_status', 'pending');
    }

    public function test_non_admin_users_receive_forbidden(): void
    {
        $token = $this->userToken();
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_users_receive_unauthorized(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(401);
    }

    public function test_invalid_status_values_return_validation_error(): void
    {
        $token = $this->adminToken();
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'invalid-status',
            ]);

        $response->assertStatus(422);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", []);

        $response->assertStatus(422);
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        $token = $this->adminToken();

        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'shipped',
            ]);

        $response->assertStatus(422);

        $order = Order::factory()->create(['status' => 'delivered']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(422);

        $order = Order::factory()->create(['status' => 'cancelled']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'pending',
            ]);

        $response->assertStatus(422);
    }

    public function test_valid_transitions_follow_the_allowed_path(): void
    {
        $token = $this->adminToken();

        $transitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
        ];

        foreach ($transitions as $from => $toList) {
            foreach ($toList as $to) {
                $order = Order::factory()->create(['status' => $from]);

                $response = $this->withHeader('Authorization', "Bearer $token")
                    ->patchJson("/api/admin/orders/{$order->id}/status", [
                        'status' => $to,
                    ]);

                $response->assertStatus(200);
                $response->assertJsonPath('data.current_status', $to);
                $response->assertJsonPath('data.previous_status', $from);

                $this->assertDatabaseHas('orders', [
                    'id' => $order->id,
                    'status' => $to,
                ]);
            }
        }
    }

    public function test_history_record_is_created_for_every_successful_status_change(): void
    {
        $token = $this->adminToken();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'previous_status' => 'pending',
            'new_status' => 'confirmed',
        ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'cancelled',
            ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'previous_status' => 'confirmed',
            'new_status' => 'cancelled',
        ]);
    }

    public function test_history_records_contain_correct_data(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin-token')->plainTextToken;
        $order = Order::factory()->create(['status' => 'pending']);

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'previous_status' => 'pending',
            'new_status' => 'confirmed',
            'changed_by' => $admin->id,
        ]);

        $history = $order->statusHistories()->first();

        $this->assertNotNull($history->created_at);
        $this->assertEquals('pending', $history->previous_status);
        $this->assertEquals('confirmed', $history->new_status);
        $this->assertEquals($admin->id, $history->changed_by);
    }

    public function test_submitting_current_status_again_does_not_create_history(): void
    {
        $token = $this->adminToken();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'pending',
            ]);

        $this->assertDatabaseCount('order_status_histories', 0);

        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_notifications_are_dispatched_only_when_status_actually_changes(): void
    {
        Notification::fake();

        $token = $this->adminToken();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        Notification::assertSentTo(
            $order->user,
            OrderStatusChanged::class,
        );

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        Notification::assertSentToTimes(
            $order->user,
            OrderStatusChanged::class,
            1,
        );
    }

    public function test_transaction_rolls_back_if_history_creation_fails(): void
    {
        $token = $this->adminToken();
        $order = Order::factory()->create(['status' => 'pending']);

        $thrown = false;
        DB::listen(function ($query) use (&$thrown) {
            if (! $thrown && str_contains($query->sql, 'order_status_histories')) {
                $thrown = true;
                throw new \Exception('Database error');
            }
        });

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(500);

        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);

        $this->assertDatabaseMissing('order_status_histories', [
            'order_id' => $order->id,
        ]);
    }

    public function test_terminal_statuses_cannot_be_changed(): void
    {
        $token = $this->adminToken();

        $deliveredOrder = Order::factory()->create(['status' => 'delivered']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$deliveredOrder->id}/status", [
                'status' => 'shipped',
            ]);

        $response->assertStatus(422);

        $cancelledOrder = Order::factory()->create(['status' => 'cancelled']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/admin/orders/{$cancelledOrder->id}/status", [
                'status' => 'pending',
            ]);

        $response->assertStatus(422);
    }
}
