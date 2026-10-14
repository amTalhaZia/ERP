<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication (RFC 6238) with single-use recovery codes.
 */
class TwoFactorController extends ApiController
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditLogger $audit,
        private readonly LoginController $login,
    ) {}

    /** Generate a pending secret; 2FA only becomes active after `confirm`. */
    public function enable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return $this->message('Two-factor authentication is already enabled.', status: 409);
        }

        $secret = $this->google2fa->generateSecretKey(32);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();

        $otpauth = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        return response()->json([
            'secret' => $secret,
            'qr_svg' => $this->renderQr($otpauth),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        /** @var User $user */
        $user = $request->user();

        if ($user->two_factor_secret === null || ! $this->verify($user, (string) $request->string('code'))) {
            throw ValidationException::withMessages(['code' => ['The code is invalid or has expired.']]);
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes->map(fn (string $c) => Hash::make($c))->all(),
        ])->save();

        $this->audit->log('auth.two_factor_enabled', $user);

        return response()->json(['recovery_codes' => $codes->all()]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:web']]);

        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log('auth.two_factor_disabled', $user);

        return $this->noContent();
    }

    /** Regenerates codes; existing ones become invalid. Requires recent password confirmation. */
    public function recoveryCodes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return $this->message('Two-factor authentication is not enabled.', status: 409);
        }

        $codes = $this->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes->map(fn (string $c) => Hash::make($c))->all()])->save();

        $this->audit->log('auth.two_factor_recovery_regenerated', $user);

        return response()->json(['recovery_codes' => $codes->all()]);
    }

    /** Step 2 of login: verify TOTP or a recovery code for the pending challenge. */
    public function challenge(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'digits:6'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:30'],
        ]);

        $pending = $request->session()->get('two_factor');

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('two_factor');

            throw ValidationException::withMessages(['code' => ['Your login attempt has expired. Please sign in again.']]);
        }

        /** @var User|null $user */
        $user = User::query()->whereKey($pending['user_id'])->where('is_active', true)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['code' => ['Your login attempt has expired. Please sign in again.']]);
        }

        $ok = $request->filled('code')
            ? $this->verify($user, (string) $request->string('code'))
            : $this->consumeRecoveryCode($user, (string) $request->string('recovery_code'));

        if (! $ok) {
            $this->audit->log('auth.two_factor_failed', $user);

            throw ValidationException::withMessages(['code' => ['The code is invalid.']]);
        }

        return $this->login->completeLogin($request, $user, (bool) ($pending['remember'] ?? false));
    }

    private function verify(User $user, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey((string) $user->two_factor_secret, $code, window: 1);
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $hashes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashes as $index => $hash) {
            if (Hash::check(Str::upper(trim($code)), $hash)) {
                unset($hashes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                $this->audit->log('auth.two_factor_recovery_used', $user, context: ['remaining' => count($hashes)]);

                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, string>
     */
    private function generateRecoveryCodes(): Collection
    {
        return Collection::times(8, fn () => Str::upper(Str::random(5).'-'.Str::random(5)));
    }

    private function renderQr(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(15, 23, 42))),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($payload);
    }
}
