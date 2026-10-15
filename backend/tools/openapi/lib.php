<?php
// Shared helpers: AST, migrations-based column catalogue, type inference.

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

define('BASE', str_replace('\\', '/', dirname(__DIR__, 2)));

function ast(string $file): array
{
    static $cache = [];
    if (isset($cache[$file])) {
        return $cache[$file];
    }
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $nodes = $parser->parse(file_get_contents($file));
    $t = new NodeTraverser;
    $t->addVisitor(new NameResolver(null, ['replaceNodes' => true]));

    return $cache[$file] = $t->traverse($nodes);
}

function nf(): NodeFinder
{
    static $f;

    return $f ??= new NodeFinder;
}

/** ClassMethod node for Class::method (handles inheritance + traits). */
function methodNode(string $class, string $method): ?Node\Stmt\ClassMethod
{
    try {
        $rm = new ReflectionMethod($class, $method);
    } catch (Throwable) {
        return null;
    }
    $file = $rm->getFileName();
    if (! $file) {
        return null;
    }
    $line = $rm->getStartLine();

    return nf()->findFirst(ast($file), fn ($n) => $n instanceof Node\Stmt\ClassMethod
        && $n->name->toString() === $method && abs($n->getStartLine() - $line) <= 1 + count($n->attrGroups) * 3) ?:
        nf()->findFirst(ast($file), fn ($n) => $n instanceof Node\Stmt\ClassMethod && $n->name->toString() === $method);
}

function docSummary(?string $doc): ?string
{
    if (! $doc) {
        return null;
    }
    $lines = [];
    foreach (preg_split('/\R/', $doc) as $l) {
        $l = trim(preg_replace('#^\s*/?\*+/?#', '', $l));
        if ($l === '' && $lines) {
            break;
        }
        if ($l === '' || str_starts_with($l, '@')) {
            if (str_starts_with($l, '@')) {
                break;
            }

            continue;
        }
        $lines[] = $l;
    }
    $s = trim(implode(' ', $lines));

    return $s === '' ? null : $s;
}

/** Returns that belong to this function (not to nested closures). */
function ownReturns(Node $fn): array
{
    $out = [];
    $walk = function ($nodes) use (&$walk, &$out) {
        foreach ((array) $nodes as $n) {
            if (! $n instanceof Node) {
                continue;
            }
            if ($n instanceof Expr\Closure || $n instanceof Expr\ArrowFunction || $n instanceof Node\Stmt\Function_ || $n instanceof Node\Stmt\Class_) {
                continue;
            }
            if ($n instanceof Node\Stmt\Return_) {
                $out[] = $n;
            }
            foreach ($n->getSubNodeNames() as $sub) {
                $walk($n->$sub);
            }
        }
    };
    $walk($fn instanceof Expr\ArrowFunction ? [] : ($fn->stmts ?? []));

    return $out;
}

function localAssigns(Node $fn): array
{
    $vars = [];
    foreach (nf()->findInstanceOf($fn->stmts ?? [], Expr\Assign::class) as $a) {
        if ($a->var instanceof Expr\Variable && is_string($a->var->name)) {
            if (! isset($vars[$a->var->name])) {
                $vars[$a->var->name] = $a->expr; // first assignment = initial shape
            }
        } elseif ($a->var instanceof Expr\ArrayDimFetch && $a->var->var instanceof Expr\Variable && is_string($a->var->var->name) && $a->var->dim instanceof Scalar\String_) {
            $vars['__dim__'.$a->var->var->name][$a->var->dim->value] = $a->expr;
        }
    }

    return $vars;
}

// ---------------------------------------------------------------- columns (from migrations)

function columns(): array
{
    static $cols;
    if ($cols !== null) {
        return $cols;
    }
    $cols = [];
    $files = glob(BASE.'/database/migrations/*.php');
    sort($files);
    foreach ($files as $f) {
        $src = file_get_contents($f);
        if (preg_match('/function up\(\)(.*?)(public function down\(|$)/s', $src, $upm)) {
            $src = $upm[1];
        }
        if (! preg_match_all("/Schema::(?:create|table)\(\s*'([a-z0-9_]+)'\s*,\s*function\s*\([^)]*\)[^{]*\{(.*?)\n\s{8}\}\);/s", $src, $ms, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($ms as $m) {
            $table = $m[1];
            foreach (explode(';', $m[2]) as $stmt) {
                if (! preg_match('/\$table->([a-zA-Z]+)\(([^)]*)\)(.*)$/s', trim($stmt), $s)) {
                    continue;
                }
                [$all, $type, $args, $rest] = $s;
                $nullable = str_contains($rest, '->nullable(');
                preg_match_all("/'([^']*)'/", $args, $names);
                $nums = [];
                if (preg_match_all('/(?<![\'\w])(\d+)/', $args, $nm)) {
                    $nums = $nm[1];
                }
                $name = $names[1][0] ?? null;
                switch ($type) {
                    case 'id': $cols[$table]['id'] = ['type' => 'integer', 'nullable' => false]; break;
                    case 'timestamps': case 'timestampsTz': case 'nullableTimestamps':
                        $cols[$table]['created_at'] = $cols[$table]['updated_at'] = ['type' => 'datetime', 'nullable' => true]; break;
                    case 'softDeletes': case 'softDeletesTz': $cols[$table][$name ?? 'deleted_at'] = ['type' => 'datetime', 'nullable' => true]; break;
                    case 'rememberToken': $cols[$table]['remember_token'] = ['type' => 'string', 'nullable' => true]; break;
                    case 'morphs': case 'nullableMorphs':
                        if ($name) {
                            $cols[$table][$name.'_type'] = ['type' => 'string', 'nullable' => $type !== 'morphs'];
                            $cols[$table][$name.'_id'] = ['type' => 'integer', 'nullable' => $type !== 'morphs'];
                        }
                        break;
                    case 'dropColumn':
                        foreach ($names[1] as $n) {
                            unset($cols[$table][$n]);
                        }
                        break;
                    case 'renameColumn':
                        if (isset($names[1][1], $cols[$table][$names[1][0]])) {
                            $cols[$table][$names[1][1]] = $cols[$table][$names[1][0]];
                            unset($cols[$table][$names[1][0]]);
                        }
                        break;
                    default:
                        if (! $name) {
                            break;
                        }
                        $t = match (true) {
                            in_array($type, ['foreignId', 'foreignIdFor', 'bigInteger', 'unsignedBigInteger', 'integer', 'unsignedInteger', 'smallInteger', 'unsignedSmallInteger', 'tinyInteger', 'unsignedTinyInteger', 'mediumInteger', 'unsignedMediumInteger', 'increments', 'bigIncrements'], true) => 'integer',
                            $type === 'decimal' || $type === 'unsignedDecimal' => 'decimal:'.($nums[1] ?? 2),
                            in_array($type, ['float', 'double'], true) => 'number',
                            $type === 'boolean' => 'boolean',
                            $type === 'date' => 'date',
                            in_array($type, ['timestamp', 'timestampTz', 'dateTime', 'dateTimeTz'], true) => 'datetime',
                            $type === 'time' => 'time',
                            in_array($type, ['json', 'jsonb'], true) => 'json',
                            $type === 'uuid' => 'uuid',
                            $type === 'ulid' => 'ulid',
                            $type === 'enum' => 'enum',
                            in_array($type, ['string', 'char', 'text', 'longText', 'mediumText', 'tinyText', 'ipAddress', 'macAddress', 'binary', 'year'], true) => 'string',
                            default => null,
                        };
                        if ($t === null) {
                            break;
                        }
                        $c = ['type' => $t, 'nullable' => $nullable];
                        if ($t === 'enum' && preg_match('/\[(.*)\]/s', $args, $em)) {
                            preg_match_all("/'([^']*)'/", $em[1], $ev);
                            $c['enum'] = $ev[1];
                        }
                        if ($t === 'string' && in_array($type, ['string', 'char'], true) && isset($nums[0])) {
                            $c['maxLength'] = (int) $nums[0];
                        }
                        $cols[$table][$name] = $c;
                }
            }
        }
    }

    return $cols;
}

// ---------------------------------------------------------------- enums registry

$GLOBALS['ENUMS'] = [];
function enumRef(string $class): array
{
    if (! isset($GLOBALS['ENUMS'][$class])) {
        $GLOBALS['ENUMS'][$class] = null; // name assigned later
    }

    return ['$ref' => '#/components/schemas/__ENUM__'.$class];
}

function isEnum(?string $c): bool
{
    return $c && enum_exists($c) && is_subclass_of($c, BackedEnum::class);
}

function nullable(array $s): array
{
    if ($s === []) {
        return $s;
    }
    if (isset($s['$ref']) || isset($s['oneOf']) || isset($s['allOf'])) {
        if (isset($s['anyOf'])) {
            return $s;
        }

        return ['anyOf' => [$s, ['type' => 'null']]];
    }
    if (isset($s['anyOf'])) {
        foreach ($s['anyOf'] as $a) {
            if (($a['type'] ?? null) === 'null') {
                return $s;
            }
        }
        $s['anyOf'][] = ['type' => 'null'];

        return $s;
    }
    if (isset($s['type'])) {
        $t = (array) $s['type'];
        if (! in_array('null', $t, true)) {
            $t[] = 'null';
        }
        $s['type'] = count($t) === 1 ? $t[0] : $t;
        if (isset($s['enum']) && ! in_array(null, $s['enum'], true)) {
            $s['enum'][] = null;
        }
    }

    return $s;
}

function isMoneyName(string $col): bool
{
    return ! preg_match('/quantity|difference|reorder_level|hours|rating|days|percent|rate$|^rate|latitude|longitude/', $col)
        || preg_match('/hourly_rate|default_rate_amount/', $col);
}

function moneyLike(string $col, int $scale): array
{
    if ($scale === 4 && isMoneyName($col) && ! preg_match('/(^|_)rate$/', $col) || $col === 'hourly_rate') {
        return ['$ref' => '#/components/schemas/Money'];
    }

    return ['$ref' => '#/components/schemas/Decimal'];
}

function modelMeta(string $class): ?array
{
    static $cache = [];
    if (array_key_exists($class, $cache)) {
        return $cache[$class];
    }
    try {
        if (! class_exists($class) || ! is_subclass_of($class, Illuminate\Database\Eloquent\Model::class) || (new ReflectionClass($class))->isAbstract()) {
            return $cache[$class] = null;
        }
        $m = new $class;
        $casts = $m->getCasts();
        $table = $m->getTable();

        return $cache[$class] = ['model' => $m, 'casts' => $casts, 'table' => $table, 'cols' => columns()[$table] ?? [], 'hidden' => $m->getHidden(), 'key' => $m->getKeyName(), 'keyType' => $m->getKeyType()];
    } catch (Throwable $e) {
        return $cache[$class] = null;
    }
}

/** Schema for a column on a model, or null if unknown (not a column/cast). $raw: value not transformed (Carbon raw). */
function columnSchema(?string $model, string $col): ?array
{
    $meta = $model ? modelMeta($model) : null;
    if (! $meta) {
        return null;
    }
    $cast = $meta['casts'][$col] ?? null;
    $c = $meta['cols'][$col] ?? null;
    if ($cast === null && $c === null) {
        return null;
    }
    $nullable = $c['nullable'] ?? true;
    if ($col === $meta['key']) {
        $nullable = false;
    }
    $s = null;
    if (is_string($cast)) {
        $base = strtolower(explode(':', $cast)[0]);
        if (isEnum($cast)) {
            $s = enumRef($cast);
        } elseif ($base === 'decimal') {
            $s = moneyLike($col, (int) (explode(':', $cast)[1] ?? 2));
        } elseif (in_array($base, ['bool', 'boolean'], true)) {
            $s = ['type' => 'boolean'];
        } elseif (in_array($base, ['int', 'integer', 'timestamp'], true)) {
            $s = ['type' => 'integer'];
        } elseif (in_array($base, ['float', 'double', 'real'], true)) {
            $s = ['type' => 'number'];
        } elseif (in_array($base, ['date', 'immutable_date', 'datetime', 'immutable_datetime'], true)) {
            $s = ['type' => 'string', 'format' => 'date-time']; // Carbon serialises as ISO-8601
        } elseif (in_array($base, ['array', 'json', 'collection', 'encrypted:array', 'encrypted:collection', 'object'], true)
            || str_contains($cast, 'AsArrayObject') || str_contains($cast, 'AsCollection')) {
            $s = jsonGuess($col);
        } elseif ($base === 'hashed' || $base === 'string' || $base === 'encrypted') {
            $s = ['type' => 'string'];
        }
    }
    if ($s === null && $c) {
        $s = match (true) {
            $c['type'] === 'integer' => ['type' => 'integer'],
            str_starts_with($c['type'], 'decimal:') => moneyLike($col, (int) substr($c['type'], 8)),
            $c['type'] === 'number' => ['type' => 'number'],
            $c['type'] === 'boolean' => ['type' => 'boolean'],
            $c['type'] === 'date' => ['type' => 'string', 'format' => 'date'],
            $c['type'] === 'datetime' => ['type' => 'string', 'format' => 'date-time'],
            $c['type'] === 'time' => ['type' => 'string', 'format' => 'time'],
            $c['type'] === 'json' => jsonGuess($col),
            in_array($c['type'], ['uuid', 'ulid'], true) => ['type' => 'string', 'format' => $c['type'] === 'uuid' ? 'uuid' : null],
            $c['type'] === 'enum' => ['type' => 'string', 'enum' => $c['enum'] ?? []],
            default => ['type' => 'string'],
        };
        $s = array_filter($s, fn ($v) => $v !== null);
    }
    if ($s === null) {
        return null;
    }
    if (in_array($col, ['created_at', 'updated_at', 'deleted_at'], true)) {
        $s = ['type' => 'string', 'format' => 'date-time'];
    }

    return $nullable ? nullable($s) : $s;
}

function jsonGuess(string $col): array
{
    if (preg_match('/(s|_ids|list|items|codes|labels|tags)$/', $col) && ! preg_match('/(settings|preferences|address|details|metadata|options|params|meta|stats|totals|filters|channels)$/', $col)) {
        return ['type' => 'array', 'items' => new stdClass];
    }

    return ['type' => 'object', 'additionalProperties' => true];
}

function nameHeuristic(string $key): array
{
    return match (true) {
        (bool) preg_match('/^(is|has|can|should|allow|requires?)_|^(maintenance|marked_all|customer_created|truncated|ok|migrations_table)$/', $key) => ['type' => 'boolean'],
        (bool) preg_match('/(_time)$/', $key) => ['type' => ['string', 'null'], 'format' => 'time'],
        (bool) preg_match('/(_minutes|^minutes|^marked|^updated|^deleted)$/', $key) => ['type' => 'integer'],
        (bool) preg_match('/(hours|on_hand|quantity|quantity_\w+|percent_complete|conversion_rate|percent)$/', $key) => ['$ref' => '#/components/schemas/Decimal'],
        (bool) preg_match('/^(recovery_codes|known_types|skipped|rows|items|lines|children)$/', $key) => ['type' => 'array', 'items' => new stdClass],
        (bool) preg_match('/^(preferences|params|attributes|meta|account|settings|filters)$/', $key) => ['type' => 'object', 'additionalProperties' => true],
        (bool) preg_match('/^(phone|platform_role|color|hint)$/', $key) => ['type' => ['string', 'null']],
        (bool) preg_match('/^(app|environment|driver|version|framework|timezone|memory_limit|upload_max_filesize|queue|cache|session|mail|filesystem|disk|secret|group|group_by|period|kind)$/', $key) => ['type' => 'string'],
        (bool) preg_match('/(^id|_id|_count|^count|^total_count|^year|^month|^page|^per_page|^last_page|^total$|position|sort_order)$/', $key) => ['type' => 'integer'],
        (bool) preg_match('/_ids$/', $key) => ['type' => 'array', 'items' => ['type' => 'integer']],
        (bool) preg_match('/_at$/', $key) => ['type' => ['string', 'null'], 'format' => 'date-time'],
        (bool) preg_match('/(_date|^date|^from|^to|_from|_to|^as_of)$/', $key) => ['type' => 'string', 'format' => 'date'],
        (bool) preg_match('/(_url|^url)$/', $key) => ['type' => ['string', 'null'], 'format' => 'uri'],
        (bool) preg_match('/(amount|balance|price|cost|salary|subtotal|_paid|_due|revenue|receivable|payable|outstanding|gross|^net|_net|_value$|debit|credit|expenses?$|income|profit|budget|spent|tax$|total)$/', $key) => ['$ref' => '#/components/schemas/Decimal'],
        (bool) preg_match('/(name|title|label|code|number|email|status|type|key|description|message|currency|slug|sku|reference)$/', $key) => ['type' => 'string'],
        default => [],
    };
}

// ---------------------------------------------------------------- relations

function relatedModel(?string $class, string $rel): ?array
{
    static $cache = [];
    $k = $class.'::'.$rel;
    if (array_key_exists($k, $cache)) {
        return $cache[$k];
    }
    $meta = $class ? modelMeta($class) : null;
    if (! $meta || ! method_exists($class, $rel)) {
        return $cache[$k] = null;
    }
    try {
        $rm = new ReflectionMethod($class, $rel);
        if ($rm->getNumberOfRequiredParameters() > 0) {
            return $cache[$k] = null;
        }
        $r = $meta['model']->$rel();
        if ($r instanceof Illuminate\Database\Eloquent\Relations\Relation) {
            $many = ! ($r instanceof Illuminate\Database\Eloquent\Relations\BelongsTo || $r instanceof Illuminate\Database\Eloquent\Relations\HasOne
                || $r instanceof Illuminate\Database\Eloquent\Relations\MorphOne || $r instanceof Illuminate\Database\Eloquent\Relations\HasOneThrough);

            return $cache[$k] = [get_class($r->getRelated()), $many];
        }
    } catch (Throwable) {
    }

    return $cache[$k] = null;
}

// ---------------------------------------------------------------- resources

$GLOBALS['RESOURCES'] = [];   // class => schema (filled lazily)
$GLOBALS['UNKNOWN'] = [];     // diagnostics

function resourceRef(string $class): array
{
    if (! array_key_exists($class, $GLOBALS['RESOURCES'])) {
        $GLOBALS['RESOURCES'][$class] = false; // placeholder → built later
    }

    return ['$ref' => '#/components/schemas/__RES__'.$class];
}

function isResource(?string $c): bool
{
    return $c && class_exists($c) && is_subclass_of($c, Illuminate\Http\Resources\Json\JsonResource::class)
        && ! is_subclass_of($c, Illuminate\Http\Resources\Json\ResourceCollection::class);
}

function useMap(string $file): array
{
    $map = [];
    preg_match('/^namespace\s+([^;]+);/m', file_get_contents($file), $ns);
    foreach (nf()->findInstanceOf(ast($file), Node\Stmt\Use_::class) as $u) {
        foreach ($u->uses as $use) {
            $map[$use->getAlias()->toString()] = $use->name->toString();
        }
    }
    $map['__ns'] = $ns[1] ?? '';

    return $map;
}

function resourceModel(string $class): ?string
{
    $rc = new ReflectionClass($class);
    $doc = $rc->getDocComment() ?: '';
    if (preg_match('/@mixin\s+\\\\?([\w\\\\]+)/', $doc, $m)) {
        $uses = useMap($rc->getFileName());
        $n = $m[1];
        $fq = str_contains($n, '\\') ? $n : ($uses[$n] ?? $uses['__ns'].'\\'.$n);
        if (class_exists($fq)) {
            return $fq;
        }
    }
    $short = preg_replace('/Resource$/', '', $rc->getShortName());
    foreach (glob(BASE.'/app/Models/{,*/,*/*/}'.$short.'.php', GLOB_BRACE) as $f) {
        $rel = str_replace([BASE.'/app/', '/', '.php'], ['App\\', '\\', ''], $f);
        if (class_exists($rel)) {
            return $rel;
        }
    }

    return null;
}

function buildResource(string $class): array
{
    $model = resourceModel($class);
    $node = methodNode($class, 'toArray');
    if (! $node) {
        return ['type' => 'object', 'additionalProperties' => true];
    }
    $ctx = ['model' => $model, 'vars' => [], 'assigns' => localAssigns($node), 'where' => $class, 'depth' => 0, 'class' => $class];
    // typed `$user = $this->resource` etc.
    foreach ($ctx['assigns'] as $v => $e) {
        if ($e instanceof Expr\PropertyFetch && $e->var instanceof Expr\Variable && $e->var->name === 'this' && $e->name instanceof Node\Identifier && $e->name->toString() === 'resource') {
            $ctx['vars'][$v] = $model;
        }
    }
    $schemas = [];
    foreach (ownReturns($node) as $r) {
        if ($r->expr) {
            $schemas[] = infer($r->expr, $ctx, null);
        }
    }
    $s = mergeAll($schemas);
    if (($s['type'] ?? null) === 'object' && $model && ($rc = new ReflectionClass($class))) {
        $s['x-model'] = $model;
    }

    return $s;
}

function mergeAll(array $schemas): array
{
    $schemas = array_values(array_filter($schemas, fn ($s) => $s !== [] && $s !== ['type' => 'null']));
    if (! $schemas) {
        return ['type' => 'object', 'additionalProperties' => true];
    }
    $out = array_shift($schemas);
    foreach ($schemas as $s) {
        if (($out['type'] ?? null) === 'object' && ($s['type'] ?? null) === 'object' && isset($out['properties'], $s['properties'])) {
            $out['properties'] += $s['properties'];
        }
    }

    return $out;
}

// ---------------------------------------------------------------- expression inference

function strArg($arg): ?string
{
    $v = $arg instanceof Node\Arg ? $arg->value : $arg;

    return $v instanceof Scalar\String_ ? $v->value : null;
}

function classOf($n): ?string
{
    if ($n instanceof Node\Name) {
        return $n->toString();
    }
    if ($n instanceof Expr\ClassConstFetch && $n->class instanceof Node\Name && $n->name instanceof Node\Identifier && $n->name->toString() === 'class') {
        return $n->class->toString();
    }

    return null;
}

/** Resolve the model class an expression evaluates to (or null). Second element: is a collection. */
function modelOf(Expr $e, array $ctx): ?array
{
    if ($e instanceof Expr\Variable && is_string($e->name)) {
        if ($e->name === 'this') {
            return $ctx['model'] ? [$ctx['model'], false] : null;
        }
        if (isset($ctx['vars'][$e->name])) {
            $v = $ctx['vars'][$e->name];

            return $v ? (is_array($v) ? $v : [$v, false]) : null;
        }

        return null;
    }
    if (($e instanceof Expr\PropertyFetch || $e instanceof Expr\NullsafePropertyFetch) && $e->name instanceof Node\Identifier) {
        $name = $e->name->toString();
        if ($name === 'resource' && $e->var instanceof Expr\Variable && $e->var->name === 'this') {
            return $ctx['model'] ? [$ctx['model'], false] : null;
        }
        $p = modelOf($e->var, $ctx);
        if ($p && ! $p[1]) {
            $r = relatedModel($p[0], $name);
            if ($r) {
                return $r;
            }
        }
    }

    return null;
}

function infer(?Node $e, array $ctx, ?string $key): array
{
    if ($e === null) {
        return [];
    }
    if ($ctx['depth'] > 24) {
        return [];
    }
    $ctx['depth']++;
    $s = inferInner($e, $ctx, $key);
    if ($s === [] && $key !== null) {
        $h = nameHeuristic($key);
        if ($h === []) {
            $GLOBALS['UNKNOWN'][] = ($ctx['where'] ?? '?').' → '.$key;
        }

        return $h;
    }

    return $s;
}

function arrayLiteral(Expr\Array_ $e, array $ctx): array
{
    $keyed = false;
    foreach ($e->items as $it) {
        if ($it && $it->key) {
            $keyed = true;
        }
    }
    if (! $keyed) {
        $items = [];
        foreach ($e->items as $it) {
            if ($it && ! $it->unpack) {
                $items[] = infer($it->value, $ctx, null);
            }
        }
        $items = array_values(array_filter($items));

        return ['type' => 'array', 'items' => $items[0] ?? new stdClass];
    }
    $props = [];
    $extra = false;
    foreach ($e->items as $it) {
        if (! $it) {
            continue;
        }
        $k = $it->key instanceof Scalar\String_ ? $it->key->value : ($it->key instanceof Scalar\Int_ ? (string) $it->key->value : null);
        if ($it->unpack || $k === null) {
            $extra = true;

            continue;
        }
        $props[$k] = infer($it->value, $ctx, $k);
        if ($props[$k] === []) {
            static $printer;
            $printer ??= new PhpParser\PrettyPrinter\Standard;
            $code = preg_replace('/\s+/', ' ', $printer->prettyPrintExpr($it->value));
            if (strlen($code) > 140) {
                $code = substr($code, 0, 137).'...';
            }
            $props[$k] = ['description' => 'Structure not statically derivable; produced by `'.$code.'`'.(isset($ctx['where']) ? ' in `'.class_basename(explode('@', explode('::', $ctx['where'])[0])[0]).'`' : '').'.'];
        }
    }
    $s = ['type' => 'object', 'properties' => $props];
    if ($extra) {
        $s['additionalProperties'] = true;
    }

    return $s;
}

function closureReturn(Node $fn, array $ctx): array
{
    foreach ($fn->params as $p) {
        if ($p->var instanceof Expr\Variable && is_string($p->var->name) && $p->type instanceof Node\Name) {
            $c = $p->type->toString();
            if (modelMeta($c)) {
                $ctx['vars'][$p->var->name] = $c;
            }
        }
    }
    if ($fn instanceof Expr\ArrowFunction) {
        return infer($fn->expr, $ctx, null);
    }
    $ctx['assigns'] = localAssigns($fn) + ($ctx['assigns'] ?? []);
    $out = [];
    $null = false;
    foreach (ownReturns($fn) as $r) {
        $x = infer($r->expr, $ctx, null);
        if ($x === ['type' => 'null']) {
            $null = true;
        } elseif ($x !== []) {
            $out[] = $x;
        }
    }
    $s = mergeAll($out);

    return $null ? nullable($s) : $s;
}

function inferInner(Node $e, array $ctx, ?string $key): array
{
    // ---- literals
    if ($e instanceof Scalar\String_ || $e instanceof Scalar\InterpolatedString || $e instanceof Expr\BinaryOp\Concat) {
        return ['type' => 'string'];
    }
    if ($e instanceof Scalar\Int_) {
        return ['type' => 'integer'];
    }
    if ($e instanceof Scalar\Float_) {
        return ['type' => 'number'];
    }
    if ($e instanceof Expr\ConstFetch) {
        $n = strtolower($e->name->toString());

        return match ($n) {
            'true', 'false' => ['type' => 'boolean'],
            'null' => ['type' => 'null'],
            default => [],
        };
    }
    if ($e instanceof Expr\Array_) {
        return arrayLiteral($e, $ctx);
    }
    // ---- casts
    if ($e instanceof Expr\Cast\Bool_ || $e instanceof Expr\BooleanNot || $e instanceof Expr\BinaryOp\Identical || $e instanceof Expr\BinaryOp\NotIdentical
        || $e instanceof Expr\BinaryOp\Equal || $e instanceof Expr\BinaryOp\NotEqual || $e instanceof Expr\BinaryOp\Greater || $e instanceof Expr\BinaryOp\GreaterOrEqual
        || $e instanceof Expr\BinaryOp\Smaller || $e instanceof Expr\BinaryOp\SmallerOrEqual || $e instanceof Expr\BinaryOp\BooleanAnd || $e instanceof Expr\BinaryOp\BooleanOr
        || $e instanceof Expr\Isset_ || $e instanceof Expr\Empty_ || $e instanceof Expr\Instanceof_) {
        return ['type' => 'boolean'];
    }
    if ($e instanceof Expr\Cast\Int_) {
        return ['type' => 'integer'];
    }
    if ($e instanceof Expr\Cast\Double) {
        return ['type' => 'number'];
    }
    if ($e instanceof Expr\Cast\String_) {
        $in = infer($e->expr, $ctx, $key);
        if (isset($in['$ref']) || (isset($in['anyOf']) && isset($in['anyOf'][0]['$ref']))) {
            $r = $in['$ref'] ?? $in['anyOf'][0]['$ref'];
            if (str_contains($r, 'Money') || str_contains($r, 'Decimal')) {
                return ['$ref' => $r];
            }
        }

        return ['type' => 'string'];
    }
    if ($e instanceof Expr\Cast\Array_) {
        return ['type' => 'object', 'additionalProperties' => true];
    }
    if ($e instanceof Expr\BinaryOp\Plus || $e instanceof Expr\BinaryOp\Minus || $e instanceof Expr\BinaryOp\Mul || $e instanceof Expr\BinaryOp\Div) {
        return ['type' => 'number'];
    }
    if ($e instanceof Expr\BinaryOp\Coalesce) {
        $l = infer($e->left, $ctx, $key);
        $r = infer($e->right, $ctx, $key);
        $rNull = $r === ['type' => 'null'];
        if ($l === [] || $l === ['type' => 'null']) {
            return $r;
        }
        if (! $rNull) {
            $l = stripNull($l);
        }

        return $l;
    }
    if ($e instanceof Expr\Ternary) {
        $a = $e->if ? infer($e->if, $ctx, $key) : infer($e->cond, $ctx, $key);
        $b = infer($e->else, $ctx, $key);
        if ($a === ['type' => 'null']) {
            return nullable($b);
        }
        if ($b === ['type' => 'null']) {
            return nullable($a);
        }

        return $a !== [] ? $a : $b;
    }
    if ($e instanceof Expr\Match_) {
        foreach ($e->arms as $arm) {
            $x = infer($arm->body, $ctx, $key);
            if ($x !== [] && $x !== ['type' => 'null']) {
                return $x;
            }
        }

        return [];
    }
    if ($e instanceof Expr\Variable && is_string($e->name)) {
        if (isset($ctx['vars'][$e->name]) && ! is_array($ctx['vars'][$e->name])) {
            return ['type' => 'object', 'additionalProperties' => true];
        }
        if (isset($ctx['assigns'][$e->name])) {
            $a = $ctx['assigns'][$e->name];
            $dims = $ctx['assigns']['__dim__'.$e->name] ?? [];
            unset($ctx['assigns'][$e->name]);
            $out = infer($a, $ctx, $key);
            if (($out['type'] ?? null) === 'object' && $dims) {
                foreach ($dims as $dk => $dv) {
                    if (! isset($out['properties'][$dk])) {
                        $x = infer($dv, $ctx, $dk);
                        $out['properties'][$dk] = $x === [] ? new stdClass : $x;
                    }
                }
            }

            return $out;
        }

        return [];
    }
    // ---- property fetches
    if (($e instanceof Expr\PropertyFetch || $e instanceof Expr\NullsafePropertyFetch) && $e->name instanceof Node\Identifier) {
        $name = $e->name->toString();
        if ($name === 'value') {
            $in = infer($e->var, $ctx, $key);
            $ref = $in['$ref'] ?? ($in['anyOf'][0]['$ref'] ?? null);
            if ($ref && str_contains($ref, '__ENUM__')) {
                return $e instanceof Expr\NullsafePropertyFetch || isset($in['anyOf']) ? nullable(['$ref' => $ref]) : ['$ref' => $ref];
            }
        }
        $owner = modelOf($e->var, $ctx);
        if ($owner && ! $owner[1]) {
            $c = columnSchema($owner[0], $name);
            if ($c !== null) {
                return $c;
            }
            $r = relatedModel($owner[0], $name);
            if ($r) {
                return $r[1] ? ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]] : nullable(['type' => 'object', 'additionalProperties' => true]);
            }
            // accessor
            $acc = 'get'.Illuminate\Support\Str::studly($name).'Attribute';
            if (method_exists($owner[0], $acc)) {
                return [];
            }
        }

        return [];
    }
    // ---- static calls
    if ($e instanceof Expr\StaticCall && $e->class instanceof Node\Name && $e->name instanceof Node\Identifier) {
        $cls = $e->class->toString();
        if (in_array(strtolower($cls), ["self", "static"], true) && isset($ctx["class"])) {
            $cls = $ctx["class"];
        }
        $m = $e->name->toString();
        if (isResource($cls)) {
            if ($m === 'collection') {
                return ['type' => 'array', 'items' => resourceRef($cls)];
            }
            if ($m === 'make') {
                return resourceRef($cls);
            }
        }
        if (str_ends_with($cls, '\Cache') && in_array($m, ['remember', 'rememberForever', 'flexible', 'sear'], true)) {
            foreach ($e->args as $a) {
                if ($a->value instanceof Expr\Closure || $a->value instanceof Expr\ArrowFunction) {
                    return closureReturn($a->value, $ctx);
                }
            }
        }
        if (str_ends_with($cls, '\Money')) {
            return in_array($m, ['isZero', 'isNegative', 'isPositive', 'equals'], true) ? ['type' => 'boolean'] : ['$ref' => '#/components/schemas/Decimal'];
        }
        if (str_ends_with($cls, 'UserSummary') && $m === 'from') {
            return nullable(['$ref' => '#/components/schemas/UserSummary']);
        }
        if (isEnum($cls) && in_array($m, ['from', 'tryFrom'], true)) {
            return enumRef($cls);
        }
        if (($cls === 'Illuminate\Support\Facades\Storage' || $cls === 'Illuminate\Support\Facades\URL')) {
            return ['type' => 'string', 'format' => 'uri'];
        }
        if (str_starts_with($cls, 'App\\') && class_exists($cls)) {
            $node = methodNode($cls, $m);
            if ($node && $ctx['depth'] < 18) {
                return methodReturnSchema($cls, $node, $ctx);
            }
        }

        return [];
    }
    if ($e instanceof Expr\New_ && $e->class instanceof Node\Name) {
        $cls = $e->class->toString();
        if (isResource($cls)) {
            return resourceRef($cls);
        }

        return [];
    }
    if ($e instanceof Expr\FuncCall && $e->name instanceof Expr\Variable && is_string($e->name->name) && isset($ctx['assigns'][$e->name->name])) {
        $fn = $ctx['assigns'][$e->name->name];
        unset($ctx['assigns'][$e->name->name]); // stop recursive builders (trees)
        if ($fn instanceof Expr\Closure || $fn instanceof Expr\ArrowFunction) {
            return closureReturn($fn, $ctx);
        }

        return [];
    }
    if ($e instanceof Expr\FuncCall && $e->name instanceof Node\Name) {
        $f = strtolower($e->name->toString());

        return match ($f) {
            'count', 'intval', 'array_sum', 'round', 'floor', 'ceil', 'max', 'min', 'abs' => $f === 'count' || $f === 'intval' ? ['type' => 'integer'] : ['type' => 'number'],
            'number_format', 'sprintf', 'implode', 'trim', 'strtolower', 'strtoupper', 'ucfirst', 'str_pad', 'substr', 'bcadd', 'bcsub', 'bcmul', 'bcdiv', 'route', 'url', 'asset', 'basename', '__' => in_array($f, ['bcadd', 'bcsub', 'bcmul', 'bcdiv'], true) ? ['$ref' => '#/components/schemas/Decimal'] : ['type' => 'string'],
            'array_values', 'array_map', 'array_filter', 'array_keys', 'array_merge', 'array_slice', 'collect', 'iterator_to_array' => mapLike($e, $ctx),
            'is_null', 'in_array', 'is_array', 'filled', 'blank', 'str_contains', 'array_key_exists' => ['type' => 'boolean'],
            'now', 'today' => ['type' => 'string', 'format' => 'date-time'],
            'optional' => isset($e->args[0]) ? infer($e->args[0]->value, $ctx, $key) : [],
            default => [],
        };
    }
    // ---- method calls
    if (($e instanceof Expr\MethodCall || $e instanceof Expr\NullsafeMethodCall) && $e->name instanceof Node\Identifier) {
        return inferMethodCall($e, $ctx, $key);
    }

    return [];
}

function stripNull(array $s): array
{
    if (isset($s['anyOf'])) {
        $a = array_values(array_filter($s['anyOf'], fn ($x) => ($x['type'] ?? null) !== 'null'));

        return count($a) === 1 ? $a[0] : ['anyOf' => $a];
    }
    if (isset($s['type']) && is_array($s['type'])) {
        $t = array_values(array_diff($s['type'], ['null']));
        $s['type'] = count($t) === 1 ? $t[0] : $t;
        if (isset($s['enum'])) {
            $s['enum'] = array_values(array_filter($s['enum'], fn ($v) => $v !== null));
        }
    }

    return $s;
}

function mapLike(Node $e, array $ctx): array
{
    foreach ($e->args as $a) {
        if ($a->value instanceof Expr\Closure || $a->value instanceof Expr\ArrowFunction) {
            return ['type' => 'array', 'items' => closureReturn($a->value, $ctx) ?: new stdClass];
        }
    }
    if (isset($e->args[0])) {
        $x = infer($e->args[0]->value, $ctx, null);
        if (($x['type'] ?? null) === 'array') {
            return $x;
        }
    }

    return ['type' => 'array', 'items' => new stdClass];
}

/** Root static model class of a query chain like Customer::query()->where()->get(). */
function chainModel(Expr $e): ?string
{
    while ($e instanceof Expr\MethodCall || $e instanceof Expr\NullsafeMethodCall) {
        $e = $e->var;
    }
    if ($e instanceof Expr\StaticCall && $e->class instanceof Node\Name) {
        $c = $e->class->toString();
        if (modelMeta($c)) {
            return $c;
        }
    }

    return null;
}

function modelObjectSchema(string $model, ?array $cols): array
{
    $meta = modelMeta($model);
    $props = [];
    $names = $cols ?? array_keys($meta['cols']);
    foreach ($names as $c) {
        $c = trim(preg_replace('/^.*\.| as .*$/i', '', $c));
        if (in_array($c, $meta['hidden'], true) || $c === '*' || $c === 'company_id' && $cols === null) {
            continue;
        }
        $props[$c] = columnSchema($model, $c) ?? (nameHeuristic($c) ?: new stdClass);
    }

    return ['type' => 'object', 'properties' => $props];
}

function inferMethodCall(Expr $e, array $ctx, ?string $key): array
{
    $m = $e->name->toString();
    $var = $e->var;
    $isThis = $var instanceof Expr\Variable && $var->name === 'this';
    $args = $e->args;

    // JsonResource helpers
    if ($isThis) {
        switch ($m) {
            case 'whenLoaded':
                $rel = strArg($args[0] ?? null);
                if (isset($args[1]) && ($args[1]->value instanceof Expr\Closure || $args[1]->value instanceof Expr\ArrowFunction)) {
                    return closureReturn($args[1]->value, $ctx);
                }
                if (isset($args[1])) {
                    return infer($args[1]->value, $ctx, $key);
                }
                $r = $rel ? relatedModel($ctx['model'], $rel) : null;

                return $r && $r[1] ? ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]] : nullable(['type' => 'object', 'additionalProperties' => true]);
            case 'whenCounted':
                return ['type' => 'integer'];
            case 'whenAggregated':
                return ['$ref' => '#/components/schemas/Decimal'];
            case 'whenExistsLoaded':
                return ['type' => 'boolean'];
            case 'when':
            case 'whenHas':
            case 'whenNotNull':
            case 'whenPivotLoaded':
                $v = $m === 'when' ? ($args[1] ?? null) : ($m === 'whenNotNull' ? ($args[0] ?? null) : ($args[1] ?? null));
                if ($m === 'whenHas' && ! isset($args[1])) {
                    $attr = strArg($args[0] ?? null);

                    return $attr ? (columnSchema($ctx['model'], $attr) ?? []) : [];
                }
                if (! $v) {
                    return [];
                }
                if ($v->value instanceof Expr\Closure || $v->value instanceof Expr\ArrowFunction) {
                    return closureReturn($v->value, $ctx);
                }

                return infer($v->value, $ctx, $key);
            case 'userSummary':
                return nullable(['$ref' => '#/components/schemas/UserRef']);
        }
        // $this->otherMethod() in same class
        $cls = $ctx['class'] ?? null;
        if ($cls && method_exists($cls, $m) && $ctx['depth'] < 18) {
            $node = methodNode($cls, $m);
            if ($node) {
                return methodReturnSchema($cls, $node, $ctx, $args);
            }
        }
        if ($ctx['model'] && method_exists($ctx['model'], $m)) {
            return modelMethodSchema($ctx['model'], $m, $key);
        }

        return [];
    }

    // date formatting
    if (in_array($m, ['toJSON', 'toIso8601String', 'toISOString', 'toAtomString', 'toIso8601ZuluString', 'toRfc3339String'], true)) {
        $s = ['type' => 'string', 'format' => 'date-time'];

        return $e instanceof Expr\NullsafeMethodCall || nullableInner($var, $ctx) ? nullable($s) : $s;
    }
    if (in_array($m, ['toDateString'], true) || ($m === 'format' && in_array(strArg($args[0] ?? null), ['Y-m-d'], true))) {
        $s = ['type' => 'string', 'format' => 'date'];

        return $e instanceof Expr\NullsafeMethodCall || nullableInner($var, $ctx) ? nullable($s) : $s;
    }
    if ($m === 'format' || $m === 'toDateTimeString' || $m === 'toTimeString' || $m === 'label' || $m === 'diffForHumans') {
        $s = ['type' => 'string'];

        return $e instanceof Expr\NullsafeMethodCall ? nullable($s) : $s;
    }
    if (in_array($m, ['getKey'], true)) {
        return ['type' => 'integer'];
    }
    if (in_array($m, ['count', 'total', 'currentPage', 'lastPage', 'perPage'], true) && $args === []) {
        return ['type' => 'integer'];
    }
    if (in_array($m, ['exists', 'isEmpty', 'isNotEmpty', 'contains', 'has', 'isPast', 'isFuture', 'isToday', 'can', 'cannot'], true)) {
        return ['type' => 'boolean'];
    }
    // resource ->response()
    if ($m === 'resolve' || $m === 'jsonSerialize') {
        return infer($var, $ctx, $key);
    }

    // eloquent query chains
    $model = chainModel($e);
    if ($model) {
        if ($m === 'get' || $m === 'all' && $var instanceof Expr\MethodCall && $var->name->toString() === 'get') {
            $getCall = $m === 'get' ? $e : $var;
            $cols = null;
            if (isset($getCall->args[0]) && $getCall->args[0]->value instanceof Expr\Array_) {
                $cols = [];
                foreach ($getCall->args[0]->value->items as $it) {
                    if ($it && $it->value instanceof Scalar\String_) {
                        $cols[] = $it->value->value;
                    }
                }
            }

            return ['type' => 'array', 'items' => modelObjectSchema($model, $cols)];
        }
        if (in_array($m, ['first', 'firstOrFail', 'find', 'findOrFail', 'sole'], true)) {
            return nullable(modelObjectSchema($model, null));
        }
        if ($m === 'pluck') {
            $c = strArg($args[0] ?? null);

            return ['type' => 'array', 'items' => ($c ? columnSchema($model, $c) : null) ?: new stdClass];
        }
        if ($m === 'value') {
            $c = strArg($args[0] ?? null);

            return ($c ? columnSchema($model, $c) : null) ?: [];
        }
        if (in_array($m, ['sum', 'avg', 'max', 'min'], true)) {
            $c = strArg($args[0] ?? null);
            $cs = $c ? columnSchema($model, $c) : null;
            if ($cs && (str_contains(json_encode($cs), 'Money') || str_contains(json_encode($cs), 'Decimal'))) {
                return ['$ref' => '#/components/schemas/Decimal'];
            }

            return ['type' => 'number'];
        }
    }

    // collections
    if (in_array($m, ['map', 'mapWithKeys', 'flatMap', 'transform'], true)) {
        foreach ($args as $a) {
            if ($a->value instanceof Expr\Closure || $a->value instanceof Expr\ArrowFunction) {
                $item = closureReturn($a->value, ctxWithCollectionVar($a->value, $var, $ctx));
                if ($m === 'mapWithKeys') {
                    return ['type' => 'object', 'additionalProperties' => true];
                }

                return ['type' => 'array', 'items' => $item ?: new stdClass];
            }
        }

        return ['type' => 'array', 'items' => new stdClass];
    }
    if (in_array($m, ['values', 'all', 'toArray', 'sortBy', 'sortByDesc', 'filter', 'reject', 'unique', 'take', 'slice', 'reverse', 'where', 'whereIn', 'sort', 'merge', 'push', 'concat'], true)) {
        $in = infer($var, $ctx, $key);
        if (($in['type'] ?? null) === 'array' || ($in['type'] ?? null) === 'object') {
            return $in;
        }

        return in_array($m, ['toArray'], true) ? [] : ['type' => 'array', 'items' => new stdClass];
    }
    if (in_array($m, ['first', 'firstWhere', 'last', 'get', 'find'], true)) {
        $in = infer($var, $ctx, $key);
        if (($in['type'] ?? null) === 'array' && isset($in['items']) && is_array($in['items'])) {
            return nullable($in['items']);
        }

        return [];
    }
    if (in_array($m, ['pluck', 'keys', 'groupBy', 'chunk'], true)) {
        return ['type' => 'array', 'items' => new stdClass];
    }
    if (in_array($m, ['sum', 'avg', 'max', 'min'], true)) {
        return ['type' => 'number'];
    }
    if (in_array($m, ['implode', 'join', 'toString', 'url', 'temporaryUrl', 'signedUrl'], true)) {
        return ['type' => 'string'];
    }

    // model instance methods (e.g. $user->avatarUrl())
    $owner = modelOf($var, $ctx);
    if ($owner && ! $owner[1] && method_exists($owner[0], $m)) {
        return modelMethodSchema($owner[0], $m, $key);
    }

    // service call: $this->svc->method() / $svc->method() with typed property/param
    $svcClass = serviceClass($var, $ctx);
    if ($svcClass && method_exists($svcClass, $m) && $ctx['depth'] < 18) {
        $node = methodNode($svcClass, $m);
        if ($node) {
            return methodReturnSchema($svcClass, $node, $ctx, $args);
        }
    }

    return [];
}

function ctxWithCollectionVar(Node $fn, Expr $coll, array $ctx): array
{
    // untyped `fn ($row) =>` over a model collection
    if (isset($fn->params[0]) && $fn->params[0]->type === null && $fn->params[0]->var instanceof Expr\Variable) {
        $model = chainModel($coll);
        if (! $model) {
            $o = modelOf($coll, $ctx);
            if ($o && $o[1]) {
                $model = $o[0];
            }
        }
        if ($model) {
            $ctx['vars'][$fn->params[0]->var->name] = $model;
        }
    }

    return $ctx;
}

function nullableInner(Expr $var, array $ctx): bool
{
    $s = infer($var, $ctx, null);

    return (isset($s['type']) && is_array($s['type']) && in_array('null', $s['type'], true)) || isset($s['anyOf']);
}

function modelMethodSchema(string $model, string $m, ?string $key): array
{
    try {
        $rm = new ReflectionMethod($model, $m);
        $t = $rm->getReturnType();
        if ($t instanceof ReflectionNamedType) {
            $n = $t->getName();
            $s = match ($n) {
                'string' => ['type' => 'string'],
                'int' => ['type' => 'integer'],
                'bool' => ['type' => 'boolean'],
                'float' => ['type' => 'number'],
                'array' => ['type' => 'object', 'additionalProperties' => true],
                default => [],
            };
            if ($n === 'string' && preg_match('/url/i', $m)) {
                $s['format'] = 'uri';
            }
            if ($n === 'array' && ($node = methodNode($model, $m))) {
                $x = methodReturnSchema($model, $node, ['model' => $model, 'vars' => [], 'assigns' => [], 'depth' => 3, 'where' => $model, 'class' => $model]);
                if ($x !== []) {
                    $s = $x;
                }
            }
            if ($s !== [] && $t->allowsNull()) {
                $s = nullable($s);
            }

            return $s;
        }
    } catch (Throwable) {
    }

    return [];
}

function serviceClass(Expr $var, array $ctx): ?string
{
    if ($var instanceof Expr\Variable && is_string($var->name) && isset($ctx['types'][$var->name])) {
        return $ctx['types'][$var->name];
    }
    if ($var instanceof Expr\PropertyFetch && $var->var instanceof Expr\Variable && $var->var->name === 'this' && $var->name instanceof Node\Identifier && isset($ctx['class'])) {
        try {
            $p = new ReflectionProperty($ctx['class'], $var->name->toString());
            $t = $p->getType();
            if ($t instanceof ReflectionNamedType && ! $t->isBuiltin()) {
                return $t->getName();
            }
        } catch (Throwable) {
        }
    }
    if ($var instanceof Expr\FuncCall && $var->name instanceof Node\Name && $var->name->toString() === 'app' && isset($var->args[0])) {
        return classOf($var->args[0]->value);
    }

    return null;
}

function methodReturnSchema(string $cls, Node\Stmt\ClassMethod $node, array $ctx, array $args = []): array
{
    static $stack = [];
    $k = $cls.'::'.$node->name->toString();
    if (isset($stack[$k])) {
        return [];
    }
    $stack[$k] = true;
    $c2 = $ctx;
    $c2['class'] = $cls;
    $c2['assigns'] = localAssigns($node);
    $c2['types'] = [];
    $c2['vars'] = [];
    $c2['where'] = $cls.'::'.$node->name->toString();
    foreach ($node->params as $p) {
        if ($p->type instanceof Node\Name && $p->var instanceof Expr\Variable) {
            $t = $p->type->toString();
            if (modelMeta($t)) {
                $c2['vars'][$p->var->name] = $t;
            } else {
                $c2['types'][$p->var->name] = $t;
            }
        }
    }
    if (is_subclass_of($cls, Illuminate\Database\Eloquent\Model::class)) {
        $c2['model'] = $cls;
    } elseif (! is_subclass_of($cls, Illuminate\Http\Resources\Json\JsonResource::class)) {
        $c2['model'] = null;
    }
    $out = [];
    $null = false;
    foreach (ownReturns($node) as $r) {
        $x = infer($r->expr, $c2, null);
        if ($x === ['type' => 'null']) {
            $null = true;
        } elseif ($x !== []) {
            $out[] = $x;
        }
    }
    unset($stack[$k]);
    if (! $out) {
        // fall back on declared return type
        $rt = $node->returnType;
        $n = $rt instanceof Node\NullableType ? $rt->type : $rt;
        $s = [];
        if ($n instanceof Node\Identifier) {
            $s = match ($n->toString()) {
                'string' => ['type' => 'string'], 'int' => ['type' => 'integer'], 'bool' => ['type' => 'boolean'], 'float' => ['type' => 'number'],
                'array' => ['type' => 'object', 'additionalProperties' => true], default => [],
            };
        }

        return $s;
    }
    $s = mergeAll($out);

    return $null ? nullable($s) : $s;
}
