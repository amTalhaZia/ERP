<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use App\Notifications\HR\HolidayAnnounced;
use App\Notifications\UserInvitedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-user channel preferences, stored in `users.preferences.notifications`
 * as `{ "<type>": { "in_app": bool, "email": bool } }`. Unknown types are
 * accepted so modules can introduce new ones without touching this code.
 */
class NotificationPreferenceController extends ApiController
{
    /** Types shipped with the core; modules may register more via GenericNotification. */
    private const KNOWN_TYPES = [
        UserInvitedNotification::TYPE,
        HolidayAnnounced::TYPE,
    ];

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->payload($user);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array', 'max:200'],
            'preferences.*' => ['required', 'array:in_app,email'],
            'preferences.*.in_app' => ['required', 'boolean'],
            'preferences.*.email' => ['required', 'boolean'],
        ]);

        $clean = [];
        foreach ($validated['preferences'] as $type => $channels) {
            $type = (string) $type;

            if ($type === '' || mb_strlen($type) > 60 || ! preg_match('/^[a-z0-9_.-]+$/i', $type)) {
                continue;
            }

            $clean[$type] = ['in_app' => (bool) $channels['in_app'], 'email' => (bool) $channels['email']];
        }

        /** @var User $user */
        $user = $request->user();

        $preferences = $user->preferences ?? [];
        $preferences['notifications'] = array_merge($preferences['notifications'] ?? [], $clean);

        $user->forceFill(['preferences' => $preferences])->saveQuietly();

        return $this->payload($user);
    }

    private function payload(User $user): JsonResponse
    {
        $stored = (array) data_get($user->preferences, 'notifications', []);

        return response()->json(['data' => [
            'defaults' => ['in_app' => true, 'email' => true],
            'known_types' => self::KNOWN_TYPES,
            'preferences' => $stored === [] ? new \stdClass : $stored,
        ]]);
    }
}
