<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        // A hosted demo (APP_ENV=demo) behaves like production here; only the demo seeder treats it differently.
        $hosted = $this->app->environment('production', 'demo');

        Model::preventLazyLoading(! $hosted);
        Model::preventSilentlyDiscardingAttributes(! $hosted);

        if ($hosted) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => Password::min(config('erp.security.password_min_length'))
            ->letters()
            ->numbers()
            ->uncompromised());

        $this->configureRateLimiting();
        $this->configureAuthLinks();
    }

    /**
     * Email links must open the SPA, not the API host.
     */
    private function configureAuthLinks(): void
    {
        ResetPassword::createUrlUsing(fn (object $notifiable, string $token) => rtrim(config('erp.frontend_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()));

        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $apiUrl = URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->addMinutes(60), [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]);

            return rtrim(config('erp.frontend_url'), '/').'/verify-email?url='.urlencode($apiUrl);
        });
    }

    private function configureRateLimiting(): void
    {
        // Brute-force protection: per email+IP so one attacker cannot lock out
        // a user from elsewhere, and one IP cannot spray many accounts.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->string('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by(
            ($request->session()->get('two_factor.user_id') ?? $request->ip()).'|2fa',
        ));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip(),
        ));

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip(),
        ));
    }
}
