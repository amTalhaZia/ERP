<?php

namespace Tests\Feature\HR;

use App\Models\Branch;
use App\Models\User;
use App\Notifications\HR\HolidayAnnounced;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HolidayNotificationTest extends TestCase
{
    public function test_new_company_holiday_notifies_active_members_in_app_only(): void
    {
        Notification::fake();
        [$hr, $company] = $this->userWith(['shifts.manage']);
        $member = User::factory()->inCompany($company)->create();
        $inactive = User::factory()->inactive()->inCompany($company)->create();
        $outsider = User::factory()->create();

        $this->actingAsIn($hr, $company)->postJson('/api/v1/holidays', ['name' => 'Eid', 'date' => now()->addDays(10)->toDateString()])
            ->assertCreated()
            ->assertJsonPath('meta.notified', 2);

        Notification::assertSentTo($member, HolidayAnnounced::class, fn ($n, array $channels) => $channels === ['database']);
        Notification::assertSentTo($hr, HolidayAnnounced::class);
        Notification::assertNotSentTo($inactive, HolidayAnnounced::class);
        Notification::assertNotSentTo($outsider, HolidayAnnounced::class);
    }

    public function test_branch_holiday_only_reaches_that_branch_and_past_or_opted_out_holidays_send_nothing(): void
    {
        Notification::fake();
        [$hr, $company] = $this->userWith(['shifts.manage']);
        $boston = Branch::factory()->for($company)->create();
        $chicago = Branch::factory()->for($company)->create();
        $inBoston = User::factory()->inCompany($company, [], true, [$boston->id])->create();
        $inChicago = User::factory()->inCompany($company, [], true, [$chicago->id])->create();

        $this->actingAsIn($hr, $company)->postJson('/api/v1/holidays', ['name' => 'Founders day', 'date' => now()->addDays(5)->toDateString(), 'branch_id' => $boston->id])
            ->assertCreated();

        Notification::assertSentTo($inBoston, HolidayAnnounced::class);
        Notification::assertNotSentTo($inChicago, HolidayAnnounced::class);

        Notification::fake();
        $this->actingAsIn($hr, $company)->postJson('/api/v1/holidays', ['name' => 'Old', 'date' => now()->subDays(3)->toDateString()])
            ->assertCreated()->assertJsonPath('meta.notified', 0);
        $this->actingAsIn($hr, $company)->postJson('/api/v1/holidays', ['name' => 'Quiet', 'date' => now()->addDays(3)->toDateString(), 'notify' => false])
            ->assertCreated()->assertJsonPath('meta.notified', 0);
        Notification::assertNothingSent();
    }
}
