<?php

namespace App\Exceptions\Finance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Business-state refusal from the ledger, rendered as the standard API error
 * envelope `{ message, code }`. 409 by default; validation-style refusals
 * (unbalanced entry) use 422.
 */
class FinanceException extends HttpException
{
    public function __construct(string $message, public readonly string $errorCode = 'conflict', int $status = 409)
    {
        parent::__construct($status, $message);
    }

    public static function conflict(string $message, string $code = 'conflict'): static
    {
        return new static($message, $code);
    }

    public static function periodClosed(string $periodName): static
    {
        return new static("Financial period {$periodName} is closed.", 'finance.period_closed');
    }

    public static function noPeriod(string $date): static
    {
        return new static("No financial period covers {$date}. Create the financial year first.", 'finance.no_period');
    }

    public static function unbalanced(string $debit, string $credit): static
    {
        return new static(
            "Journal entry is not balanced: debits {$debit} do not equal credits {$credit}, or the entry is empty.",
            'finance.unbalanced',
            422,
        );
    }

    public static function immutable(string $what = 'Posted journal entries'): static
    {
        return new static("{$what} are immutable; post a reversing entry instead.", 'finance.immutable');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->getStatusCode());
    }
}
