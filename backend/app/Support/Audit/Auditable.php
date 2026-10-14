<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Automatically records create/update/delete of a model to audit_logs.
 * Declare `protected array $auditExclude = [...]` to skip noisy attributes.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->log(
                static::auditAction('created'),
                $model,
                null,
                $model->auditableAttributes($model->getAttributes()),
            );
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $before = $model->auditableAttributes(Arr::only($model->getOriginal(), array_keys($changes)));

            app(AuditLogger::class)->log(static::auditAction('updated'), $model, $before, $changes);
        });

        static::deleted(function (Model $model): void {
            $action = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting() ? 'archived' : 'deleted';

            app(AuditLogger::class)->log(static::auditAction($action), $model, $model->auditableAttributes($model->getAttributes()), null);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        $exclude = array_merge(
            ['updated_at', 'created_at', 'remember_token', 'password'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return Arr::except($attributes, $exclude);
    }

    protected static function auditAction(string $event): string
    {
        $entity = str_replace('_', '.', (new static)->getTable());

        return "{$entity}.{$event}";
    }
}
