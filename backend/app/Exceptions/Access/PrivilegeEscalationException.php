<?php

namespace App\Exceptions\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by PrivilegeEscalationGuard when a member tries to hand out (to
 * someone else, a role, or themselves) access they do not hold. Rendered as
 * 403 with a stable code; nothing is persisted.
 */
class PrivilegeEscalationException extends RuntimeException
{
    public const CODE = 'access.escalation_denied';

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], 403);
    }
}
