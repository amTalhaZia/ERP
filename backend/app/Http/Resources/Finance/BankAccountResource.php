<?php

namespace App\Http\Resources\Finance;

use App\Models\Finance\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes the full account number or IBAN; only a masked form.
 *
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $iban = $this->iban;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => ['id' => $this->account->id, 'code' => $this->account->code, 'name' => $this->account->name]),
            'name' => $this->name,
            'bank_name' => $this->bank_name,
            'account_number_masked' => $this->maskedAccountNumber(),
            'account_number_last4' => $this->account_number_last4,
            'iban_masked' => $iban === null || $iban === '' ? null : substr($iban, 0, 4).str_repeat('•', max(0, strlen($iban) - 8)).substr($iban, -4),
            'swift' => $this->swift,
            'currency' => $this->currency,
            'opening_balance' => (string) $this->opening_balance,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
