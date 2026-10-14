<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Self-service account bits: UI preferences, profile, email verification.
 */
class AccountController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
            'high_contrast' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'string', 'max:10'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['preferences' => array_merge($user->uiPreferences(), $validated)])->saveQuietly();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'preferences' => $user->uiPreferences(),
        ]]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->fill($validated)->save();

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone]]);
    }

    public function sendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->message('Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->message('Verification link sent.');
    }

    /** Signed URL target (`verification.verify`) — hit from the email link. */
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->message('Invalid verification link.', status: 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
            $this->audit->log('auth.email_verified', $user);
        }

        return $this->message('Email verified.');
    }
}
