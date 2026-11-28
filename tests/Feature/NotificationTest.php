<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createNotificationFor(User $user, array $data = [], bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\SystemNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => array_merge([
                'title' => 'إشعار تجريبي',
                'body' => 'محتوى الإشعار',
                'type' => 'enrollment',
                'action_url' => '/dashboard/courses',
            ], $data),
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
        $this->postJson('/api/notifications/fake-id/read')->assertUnauthorized();
    }

    public function test_user_can_list_own_notifications_with_unread_count_and_bounded_per_page(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 0; $i < 25; $i++) {
            $this->createNotificationFor($user, ['title' => "Notification {$i}"], $i < 5);
        }
        $this->createNotificationFor($other, ['title' => 'Other user notification']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?per_page=100');

        $response->assertOk()
            ->assertJsonPath('meta.unread_count', 20)
            ->assertJsonCount(20, 'data');
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherNotification = $this->createNotificationFor($other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$otherNotification->id}/read")
            ->assertNotFound();

        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotificationFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_own_notifications_as_read_without_affecting_others(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $n1 = $this->createNotificationFor($user);
        $n2 = $this->createNotificationFor($user);
        $otherN = $this->createNotificationFor($other);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/read-all')
            ->assertOk();

        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNotNull($n2->fresh()->read_at);
        $this->assertNull($otherN->fresh()->read_at);
    }
}
