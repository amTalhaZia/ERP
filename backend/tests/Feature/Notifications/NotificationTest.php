<?php

namespace Tests\Feature\Notifications;

use App\Models\Company;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Notifications\UserInvitedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_user_only_sees_their_own_notifications_for_the_active_company(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);
        $other = User::factory()->inCompany($company)->create();
        $companyB = Company::factory()->create();

        $user->notify(new GenericNotification('test.ping', 'Hello', 'Visible', '/dashboard', companyId: $company->id));
        $user->notify(new GenericNotification('test.ping', 'Global', 'Also visible', null, companyId: null));
        $user->notify(new GenericNotification('test.ping', 'Hidden', 'Other company', null, companyId: $companyB->id));
        $other->notify(new GenericNotification('test.ping', 'Not mine', 'Nope', null, companyId: $company->id));

        $response = $this->actingAsIn($user, $company)->getJson('/api/v1/notifications')->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertEqualsCanonicalizing(['Hello', 'Global'], $titles);
        $response->assertJsonPath('meta.pagination.total', 2);

        $row = collect($response->json('data'))->firstWhere('title', 'Hello');
        $this->assertSame('test.ping', $row['type']);
        $this->assertSame('Visible', $row['body']);
        $this->assertSame(rtrim(config('erp.frontend_url'), '/').'/dashboard', $row['url']);
        $this->assertSame($company->id, $row['company_id']);
        $this->assertFalse($row['is_read']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread', 2);
    }

    public function test_marks_one_or_all_as_read_and_filters_by_read_state(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);
        $user->notify(new GenericNotification('test.a', 'A', 'a', companyId: $company->id));
        $user->notify(new GenericNotification('test.b', 'B', 'b', companyId: $company->id));
        $first = DatabaseNotification::query()->where('notifiable_id', $user->id)->orderBy('created_at')->firstOrFail();

        $this->actingAsIn($user, $company)->patchJson("/api/v1/notifications/{$first->id}/read")
            ->assertOk()->assertJsonPath('data.is_read', true);
        $this->assertNotNull($first->fresh()->read_at);

        $this->actingAsIn($user, $company)->getJson('/api/v1/notifications?filter[read]=false')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAsIn($user, $company)->getJson('/api/v1/notifications?filter[read]=true')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAsIn($user, $company)->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.marked', 1);
        $this->actingAsIn($user, $company)->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread', 0);
    }

    public function test_cannot_read_or_delete_another_users_notification(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);
        $other = User::factory()->inCompany($company)->create();
        $other->notify(new GenericNotification('test.a', 'A', 'a', companyId: $company->id));
        $theirs = DatabaseNotification::query()->where('notifiable_id', $other->id)->firstOrFail();

        $this->actingAsIn($user, $company)->patchJson("/api/v1/notifications/{$theirs->id}/read")->assertNotFound();
        $this->actingAsIn($user, $company)->deleteJson("/api/v1/notifications/{$theirs->id}")->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $theirs->id, 'read_at' => null]);
    }

    public function test_deletes_own_notification(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);
        $user->notify(new GenericNotification('test.a', 'A', 'a', companyId: $company->id));
        $mine = DatabaseNotification::query()->where('notifiable_id', $user->id)->firstOrFail();

        $this->actingAsIn($user, $company)->deleteJson("/api/v1/notifications/{$mine->id}")->assertNoContent();
        $this->assertDatabaseMissing('notifications', ['id' => $mine->id]);
    }

    public function test_preferences_are_stored_on_the_user_and_honoured_by_via(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);

        $this->actingAsIn($user, $company)->putJson('/api/v1/notifications/preferences', [])->assertUnprocessable()->assertJsonValidationErrors(['preferences']);

        $response = $this->actingAsIn($user, $company)->putJson('/api/v1/notifications/preferences', [
            'preferences' => [
                UserInvitedNotification::TYPE => ['in_app' => true, 'email' => false],
                'test.quiet' => ['in_app' => false, 'email' => false],
            ],
        ])->assertOk();
        $this->assertFalse($response->json('data.preferences')[UserInvitedNotification::TYPE]['email']);

        $this->assertEquals(
            ['in_app' => true, 'email' => false],
            $user->fresh()->preferences['notifications'][UserInvitedNotification::TYPE],
        );
        $this->assertSame('system', $user->fresh()->uiPreferences()['theme'], 'UI preferences must survive');

        $shown = $this->actingAsIn($user, $company)->getJson('/api/v1/notifications/preferences')->assertOk();
        $this->assertFalse($shown->json('data.preferences')['test.quiet']['in_app']);

        Notification::fake();
        $fresh = $user->fresh();
        $fresh->notify(new UserInvitedNotification($company, null, false));
        $fresh->notify(new GenericNotification('test.quiet', 'Q', 'q', email: true));
        $fresh->notify(new GenericNotification('test.loud', 'L', 'l', email: true));

        Notification::assertSentTo($fresh, UserInvitedNotification::class, fn ($n, array $channels) => $channels === ['database']);
        Notification::assertNotSentTo($fresh, GenericNotification::class, fn (GenericNotification $n) => $n->type() === 'test.quiet');
        $this->assertSame([], (new GenericNotification('test.quiet', 'Q', 'q', email: true))->via($fresh));
        Notification::assertSentTo($fresh, GenericNotification::class, fn (GenericNotification $n, array $channels) => $n->type() === 'test.loud' && $channels === ['database', 'mail']);
    }

    public function test_database_payload_has_the_standard_shape(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);

        $user->notify(new GenericNotification('test.shape', 'Title', 'Body', '/x', ['k' => 'v'], companyId: $company->id));

        $row = DatabaseNotification::query()->where('notifiable_id', $user->id)->firstOrFail();
        $this->assertSame(['type', 'title', 'body', 'url', 'company_id', 'meta'], array_keys($row->data));
        $this->assertSame(['k' => 'v'], $row->data['meta']);
        $this->assertSame($company->id, $row->data['company_id']);
    }
}
