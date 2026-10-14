<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    public function test_valid_credentials_create_a_session_and_return_the_session_payload(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->inCompany($company, ['dashboard.view'])->create(['email' => 'ceo@example.com']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'ceo@example.com', 'password' => 'password']);

        $response->assertOk()
            ->assertJsonPath('two_factor', false)
            ->assertJsonPath('session.user.email', 'ceo@example.com')
            ->assertJsonPath('session.current_company.id', $company->id)
            ->assertJsonPath('session.permissions', ['dashboard.view']);
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_id' => $user->id]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_returns_422_with_generic_message_and_audits_the_failure(): void
    {
        User::factory()->create(['email' => 'ceo@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ceo@example.com', 'password' => 'nope-nope-nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
    }

    public function test_unknown_email_returns_the_same_message_as_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'whatever-123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'gone@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'gone@example.com', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest('web');
    }

    public function test_sixth_attempt_within_a_minute_returns_429(): void
    {
        User::factory()->create(['email' => 'ceo@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ceo@example.com', 'password' => 'bad-password-1'])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'ceo@example.com', 'password' => 'bad-password-1'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'throttled');
    }

    public function test_logout_invalidates_the_session_and_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'actor_id' => $user->id]);
    }

    public function test_session_endpoint_returns_401_for_guests(): void
    {
        $this->getJson('/api/v1/auth/session')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'auth.unauthenticated');
    }

    public function test_deactivated_user_with_live_session_is_logged_out_with_401(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web');
        $user->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/auth/session')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'auth.deactivated');
    }
}
