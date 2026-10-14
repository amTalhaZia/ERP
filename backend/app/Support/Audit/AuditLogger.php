<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Writes append-only audit rows. Secrets are redacted by key name and
 * long strings truncated so the log never becomes a dump of record bodies.
 */
class AuditLogger
{
    /** @var array<int, string> */
    private const REDACT_KEYS = [
        'password', 'password_confirmation', 'current_password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes', 'token', 'secret', 'api_key',
        'access_token', 'refresh_token', 'card_number', 'cvv', 'bank_account_number', 'iban',
        'bank_iban', 'national_id', 'tax_identifier', 'account_number', 'salary', 'base_salary',
    ];

    private const MAX_STRING = 500;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $action,
        ?Model $entity = null,
        ?array $old = null,
        ?array $new = null,
        array $context = [],
        ?int $companyId = null,
        ?string $entityLabel = null,
    ): AuditLog {
        $actor = $this->request->user();

        return AuditLog::query()->create([
            'company_id' => $companyId ?? $this->tenant->id() ?? ($entity?->getAttribute('company_id')),
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name,
            'action' => Str::limit($action, 64, ''),
            'entity_type' => $entity ? $entity->getMorphClass() : null,
            'entity_id' => $entity?->getKey(),
            'entity_label' => $entityLabel ?? ($entity ? $this->labelFor($entity) : null),
            'old_values' => $old === null ? null : $this->sanitize($old),
            'new_values' => $new === null ? null : $this->sanitize($new),
            'context' => $context === [] ? null : $this->sanitize($context),
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 255, ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitize(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if (in_array(Str::lower((string) $key), self::REDACT_KEYS, true)) {
                $out[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->sanitize($value);
            } elseif (is_string($value) && mb_strlen($value) > self::MAX_STRING) {
                $out[$key] = mb_substr($value, 0, self::MAX_STRING).'…';
            } elseif (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            } else {
                $out[$key] = (string) json_encode($value);
            }
        }

        return $out;
    }

    private function labelFor(Model $entity): ?string
    {
        foreach (['name', 'title', 'number', 'code', 'reference', 'email'] as $attr) {
            $value = $entity->getAttribute($attr);

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 200, '');
            }
        }

        return null;
    }

    /**
     * Diff two attribute arrays, returning only changed keys.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $changedKeys = array_keys(array_filter(
            $after,
            fn ($value, $key) => ! array_key_exists($key, $before) || $before[$key] != $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        return [Arr::only($before, $changedKeys), Arr::only($after, $changedKeys)];
    }
}
