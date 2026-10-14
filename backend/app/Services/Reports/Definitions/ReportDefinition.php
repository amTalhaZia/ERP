<?php

namespace App\Services\Reports\Definitions;

use App\Models\User;
use App\Services\Reports\ReportContext;
use App\Support\Tenancy\Rules\AccessibleBranch;
use Illuminate\Validation\Rule;

/**
 * A report: metadata for the catalogue, a params schema, typed columns and a
 * tenant-scoped data source. Aggregate reports implement `compute()` (small
 * result sets built in memory); row-level registers extend
 * QueryReportDefinition and page/stream from a query instead.
 *
 * Column types: money (DECIMAL string), number, date (Y-m-d / ISO), text.
 * Param types: date (Y-m-d), select (one of `options`), branch (branch id).
 */
abstract class ReportDefinition
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    /** @var array<string, array<string, mixed>> */
    private array $memo = [];

    abstract public function key(): string;

    abstract public function module(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * The user needs ANY of these permissions to run the report.
     *
     * @return list<string>
     */
    abstract public function permissions(): array;

    /**
     * @param  array<string, mixed>  $params
     * @return list<array{key: string, label: string, type: string}>
     */
    abstract public function columns(array $params): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array{rows: list<array<string, mixed>>, totals?: array<string, mixed>|null}
     */
    abstract protected function compute(ReportContext $context, array $params): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function params(): array
    {
        return [];
    }

    /**
     * Defaults for optional params (resolved in the company's timezone).
     *
     * @return array<string, mixed>
     */
    public function defaults(ReportContext $context): array
    {
        return [];
    }

    public function authorizedFor(User $user): bool
    {
        return $user->hasAnyPermission($this->permissions());
    }

    /**
     * Validation rules derived from the params schema.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->params() as $param) {
            $base = [$param['required'] ? 'required' : 'nullable'];

            $rules[$param['name']] = match ($param['type']) {
                'date' => [...$base, 'date_format:Y-m-d'],
                'select' => [...$base, 'string', Rule::in(array_column($param['options'] ?? [], 'value'))],
                'branch' => [...$base, 'integer', new AccessibleBranch],
                default => [...$base, 'string', 'max:100'],
            };
        }

        if (isset($rules['from'], $rules['to'])) {
            $rules['to'][] = 'after_or_equal:from';
        }

        return $rules;
    }

    /**
     * Keep only declared params, drop blanks, apply defaults.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function resolveParams(ReportContext $context, array $validated): array
    {
        $names = array_column($this->params(), 'name');
        $values = array_intersect_key($validated, array_flip($names));
        $values = array_filter($values, fn ($value) => $value !== null && $value !== '');

        foreach ($this->defaults($context) as $name => $default) {
            $values[$name] ??= $default;
        }

        if (isset($values['branch_id'])) {
            $values['branch_id'] = (int) $values['branch_id'];
        }

        ksort($values);

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'key' => $this->key(),
            'module' => $this->module(),
            'name' => $this->name(),
            'description' => $this->description(),
            'params' => $this->params(),
            'formats' => self::FORMATS,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function count(ReportContext $context, array $params): int
    {
        return count($this->result($context, $params)['rows']);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function slice(ReportContext $context, array $params, int $offset, int $limit): array
    {
        return array_slice($this->result($context, $params)['rows'], $offset, $limit);
    }

    /**
     * Every row, for exports.
     *
     * @param  array<string, mixed>  $params
     * @return iterable<array<string, mixed>>
     */
    public function cursor(ReportContext $context, array $params): iterable
    {
        return $this->result($context, $params)['rows'];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function totals(ReportContext $context, array $params): ?array
    {
        return $this->result($context, $params)['totals'] ?? null;
    }

    /**
     * compute() memoised per tenant + params for the lifetime of this instance.
     *
     * @param  array<string, mixed>  $params
     * @return array{rows: list<array<string, mixed>>, totals?: array<string, mixed>|null}
     */
    protected function result(ReportContext $context, array $params): array
    {
        $key = $context->companyId().':'.$context->user->id.':'.md5((string) json_encode($params));

        return $this->memo[$key] ??= $this->compute($context, $params);
    }

    // -- Schema helpers --------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected static function dateParam(string $name, string $label, bool $required = false): array
    {
        return ['name' => $name, 'label' => $label, 'type' => 'date', 'required' => $required];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function branchParam(): array
    {
        return ['name' => 'branch_id', 'label' => 'Branch', 'type' => 'branch', 'required' => false];
    }

    /**
     * @param  array<string, string>  $options  value => label
     * @return array<string, mixed>
     */
    protected static function selectParam(string $name, string $label, array $options, bool $required = false): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'type' => 'select',
            'required' => $required,
            'options' => array_map(fn (string|int $value, string $text) => ['value' => (string) $value, 'label' => $text], array_keys($options), $options),
        ];
    }

    /**
     * @return array{key: string, label: string, type: string}
     */
    protected static function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }
}
