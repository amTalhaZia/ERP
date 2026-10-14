<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Always responds 200 so account existence is not disclosed. */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:150']]);

        $status = Password::sendResetLink($request->only('email'));

        $this->audit->log('auth.password_reset_requested', context: [
            'email' => $request->string('email'),
            'sent' => $status === Password::RESET_LINK_SENT,
        ]);

        return $this->message('If an account exists for that email, a reset link has been sent.');
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke every other session for this user.
                $this->revokeOtherSessions($user);

                event(new PasswordReset($user));
                $this->audit->log('auth.password_reset', $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return $this->message('Your password has been reset. You can now sign in.');
    }

    /** Authenticated password change. */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', PasswordRule::defaults()],
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password'),
            'password_changed_at' => now(),
        ])->save();

        $this->revokeOtherSessions($user, exceptCurrent: $request->session()->getId());
        Auth::guard('web')->logoutOtherDevices((string) $request->string('password'));

        $this->audit->log('auth.password_changed', $user);

        return $this->message('Password updated. Other sessions have been signed out.');
    }

    /** Re-authentication gate for sensitive actions (`password.confirm` middleware). */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->string('password'), $request->user()->password)) {
            $this->audit->log('auth.reauth_failed', $request->user());

            throw ValidationException::withMessages(['password' => ['The provided password is incorrect.']]);
        }

        $request->session()->put('auth.password_confirmed_at', time());
        $this->audit->log('auth.reauth', $request->user());

        return response()->json([
            'confirmed_until' => now()->addMinutes(config('erp.security.password_confirmation_ttl'))->toJSON(),
        ]);
    }

    private function revokeOtherSessions(User $user, ?string $exceptCurrent = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($exceptCurrent, fn ($q) => $q->where('id', '!=', $exceptCurrent))
            ->delete();
    }
}
