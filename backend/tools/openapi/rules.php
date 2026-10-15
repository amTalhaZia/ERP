<?php
// Validation rules → JSON Schema.

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/** Normalise one rule item to a token: ['name', params] */
function ruleTokens($rules): array
{
    if (is_string($rules)) {
        $rules = explode('|', $rules);
    }
    $out = [];
    foreach ((array) $rules as $r) {
        if (is_string($r)) {
            if (str_starts_with($r, 'regex:') || str_starts_with($r, 'not_regex:')) {
                [$n, $p] = explode(':', $r, 2);
                $out[] = [$n, [$p]];

                continue;
            }
            foreach ($r === '' ? [] : [$r] as $one) {
                $parts = explode(':', $one, 2);
                $out[] = [strtolower($parts[0]), isset($parts[1]) ? str_getcsv($parts[1], ',', '"', '\\') : []];
            }

            continue;
        }
        if (is_array($r) && isset($r['__tok'])) {
            $out[] = $r['__tok'];

            continue;
        }
        if (is_object($r)) {
            $cls = get_class($r);
            $prop = function ($o, $p) {
                try {
                    $rp = new ReflectionProperty($o, $p);

                    return $rp->getValue($o);
                } catch (Throwable) {
                    return null;
                }
            };
            switch (true) {
                case $r instanceof Illuminate\Validation\Rules\Enum:
                    $out[] = ['enum_class', [$prop($r, 'type')]];
                    break;
                case $r instanceof Illuminate\Validation\Rules\In:
                    $out[] = ['in', array_map(fn ($v) => $v instanceof BackedEnum ? $v->value : $v, (array) $prop($r, 'values'))];
                    break;
                case $r instanceof Illuminate\Validation\Rules\NotIn:
                    break;
                case $r instanceof Illuminate\Validation\Rules\Exists:
                    $out[] = ['exists', [$prop($r, 'table')]];
                    break;
                case $r instanceof Illuminate\Validation\Rules\Unique:
                    $out[] = ['unique', [$prop($r, 'table')]];
                    break;
                case $r instanceof Illuminate\Validation\Rules\Password:
                    $out[] = ['string', []];
                    $out[] = ['min', [$prop($r, 'min') ?? 8]];
                    $out[] = ['__desc', ['Must satisfy the password policy.']];
                    break;
                case $r instanceof Illuminate\Validation\Rules\ImageFile:
                case $r instanceof Illuminate\Validation\Rules\File:
                    $out[] = ['file', []];
                    $types = array_merge((array) $prop($r, 'allowedMimetypes'), (array) $prop($r, 'allowedExtensions'));
                    if ($types) {
                        $out[] = ['mimes', $types];
                    }
                    if ($max = $prop($r, 'maximumFileSize')) {
                        $out[] = ['max', [$max]];
                    }
                    break;
                case $r instanceof Illuminate\Validation\Rules\RequiredIf:
                    $out[] = ['required_if', ['(condition)']];
                    break;
                case $r instanceof Closure:
                    $out[] = ['__desc', ['Custom validation applies.']];
                    break;
                case $r instanceof Stringable:
                    foreach (ruleTokens((string) $r) as $t) {
                        $out[] = $t;
                    }
                    break;
                default:
                    $out[] = ['__custom', [class_basename($cls)]];
            }
        }
    }

    return $out;
}

/** Static evaluation of a rules array literal (inline $request->validate([...]) or fallback). */
function staticRules(Expr\Array_ $arr, string $httpMethod, array $assigns = []): array
{
    $out = [];
    foreach ($arr->items as $it) {
        if (! $it || ! $it->key) {
            continue;
        }
        $key = staticString($it->key, $assigns);
        if ($key === null) {
            continue;
        }
        $out[$key] = staticRuleValue($it->value, $httpMethod, $assigns);
    }

    return $out;
}

function staticString(Node $n, array $assigns): ?string
{
    if ($n instanceof Scalar\String_) {
        return $n->value;
    }
    if ($n instanceof Expr\BinaryOp\Concat) {
        $l = staticString($n->left, $assigns);
        $r = staticString($n->right, $assigns);

        return $l !== null && $r !== null ? $l.$r : ($l !== null ? $l.'…' : null);
    }
    if ($n instanceof Expr\ClassConstFetch && $n->class instanceof Node\Name && $n->name instanceof Node\Identifier) {
        try {
            $v = constant($n->class->toString().'::'.$n->name->toString());

            return is_scalar($v) ? (string) $v : null;
        } catch (Throwable) {
            return null;
        }
    }
    if ($n instanceof Scalar\Int_) {
        return (string) $n->value;
    }

    return null;
}

function staticRuleValue(Node $v, string $httpMethod, array $assigns): array
{
    if ($v instanceof Expr\Variable && isset($assigns[$v->name])) {
        return staticRuleValue($assigns[$v->name], $httpMethod, $assigns);
    }
    if ($v instanceof Expr\Ternary) {
        return staticRuleValue($httpMethod === 'POST' ? ($v->if ?? $v->cond) : $v->else, $httpMethod, $assigns);
    }
    if ($v instanceof Expr\BinaryOp\Concat || $v instanceof Scalar\String_ || $v instanceof Expr\ClassConstFetch) {
        $s = staticString($v, $assigns);

        return $s === null ? [] : [$s];
    }
    if ($v instanceof Expr\FuncCall && $v->name instanceof Node\Name && $v->name->toString() === 'array_merge') {
        $o = [];
        foreach ($v->args as $a) {
            $o = array_merge($o, staticRuleValue($a->value, $httpMethod, $assigns));
        }

        return $o;
    }
    if (! $v instanceof Expr\Array_) {
        return [];
    }
    $rules = [];
    foreach ($v->items as $it) {
        if (! $it) {
            continue;
        }
        $x = $it->value;
        if ($x instanceof Expr\Variable && isset($assigns[$x->name])) {
            $x = $assigns[$x->name];
        }
        if ($x instanceof Expr\Ternary) {
            $x = $httpMethod === 'POST' ? ($x->if ?? $x->cond) : $x->else;
        }
        $s = staticString($x, $assigns);
        if ($s !== null) {
            $rules[] = $s;

            continue;
        }
        if ($x instanceof Expr\StaticCall && $x->class instanceof Node\Name && $x->name instanceof Node\Identifier) {
            $cls = $x->class->toString();
            $m = $x->name->toString();
            if (str_ends_with($cls, 'Rule') && $m === 'enum' && isset($x->args[0])) {
                $rules[] = ['__tok' => ['enum_class', [classOf($x->args[0]->value)]]];
            } elseif (str_ends_with($cls, 'Rule') && $m === 'in' && isset($x->args[0]) && $x->args[0]->value instanceof Expr\Array_) {
                $vals = [];
                foreach ($x->args[0]->value->items as $i) {
                    $sv = $i ? staticString($i->value, $assigns) : null;
                    if ($sv !== null) {
                        $vals[] = $sv;
                    }
                }
                $rules[] = ['__tok' => ['in', $vals]];
            } elseif (str_ends_with($cls, 'Rule') && in_array($m, ['exists', 'unique'], true)) {
                $rules[] = ['__tok' => [$m, [strArg($x->args[0] ?? null)]]];
            } elseif (str_ends_with($cls, 'Password')) {
                $rules[] = 'string';
                $rules[] = 'min:'.config('erp.security.password_min_length', 10);
            } elseif (str_ends_with($cls, 'File')) {
                $rules[] = 'file';
            } elseif (str_ends_with($cls, 'Rule') && in_array($m, ['requiredIf', 'prohibitedIf', 'excludeIf'], true)) {
                $rules[] = ['__tok' => [strtolower(preg_replace('/([A-Z])/', '_$1', $m)), ['(condition)']]];
            }

            continue;
        }
        if ($x instanceof Expr\New_ && $x->class instanceof Node\Name) {
            $rules[] = ['__tok' => ['__custom', [class_basename($x->class->toString())]]];
        }
    }

    return $rules;
}

/** Runtime rules for a FormRequest. Returns [rules, source]. */
function formRequestRules(string $class, string $httpMethod, $route): array
{
    try {
        $base = Request::create('/'.$route->uri(), $httpMethod);
        /** @var FormRequest $req */
        $req = $class::createFrom($base, new $class);
        $req->setContainer(app())->setRedirector(app('redirect'));
        $r = clone $route;
        (new ReflectionProperty($r, 'parameters'))->setValue($r, []);
        $req->setRouteResolver(fn () => $r);
        $req->setUserResolver(fn () => null);
        $rules = method_exists($req, 'rules') ? app()->call([$req, 'rules']) : [];

        return [$rules, 'runtime'];
    } catch (Throwable $e) {
        $node = methodNode($class, 'rules');
        if ($node) {
            $assigns = localAssigns($node);
            foreach (ownReturns($node) as $ret) {
                if ($ret->expr instanceof Expr\Array_) {
                    return [staticRules($ret->expr, $httpMethod, $assigns), 'static ('.class_basename($e).': '.substr($e->getMessage(), 0, 80).')'];
                }
            }
        }

        return [[], 'failed: '.$e->getMessage()];
    }
}

/** Convert flat rules map to a JSON schema object. Returns [schema, isMultipart]. */
function rulesToSchema(array $rules): array
{
    $root = ['type' => 'object', 'properties' => [], 'required' => []];
    $multipart = false;
    ksort($rules);
    // ensure parents processed before children
    uksort($rules, fn ($a, $b) => substr_count($a, '.') <=> substr_count($b, '.') ?: strcmp($a, $b));
    foreach ($rules as $field => $r) {
        $toks = ruleTokens($r);
        [$schema, $required, $isFile] = tokensToSchema($toks, (string) $field);
        $multipart = $multipart || $isFile;
        setPath($root, explode('.', (string) $field), $schema, $required, $toks);
        foreach ($toks as [$n]) {
            if ($n === 'confirmed') {
                setPath($root, explode('.', $field.'_confirmation'), stripMeta($schema, true), $required, []);
            }
        }
    }
    objectify($root);
    cleanup($root);

    return [$root, $multipart];
}

function stripMeta(array $s, bool $keepType): array
{
    return array_intersect_key($s, array_flip(['type', 'format', 'maxLength', 'minLength']));
}

function setPath(array &$node, array $path, array $schema, bool $required, array $toks): void
{
    $seg = array_shift($path);
    if ($seg === '*') {
        $node['type'] = $node['type'] ?? 'array';
        if (is_array($node['type'])) {
            // keep
        } elseif ($node['type'] !== 'array') {
            $node['type'] = 'array';
        }
        $node['items'] = $node['items'] ?? [];
        if (! $path) {
            $node['items'] = array_merge($node['items'], $schema);

            return;
        }
        setPath($node['items'], $path, $schema, $required, $toks);

        return;
    }
    if (! isset($node['properties'])) {
        $node['properties'] = [];
        $t = $node['type'] ?? null;
        if ($t === null || $t === 'array' || (is_array($t) && in_array('array', $t, true) && ! in_array('object', $t, true))) {
            $node['type'] = is_array($t) && in_array('null', $t, true) ? ['object', 'null'] : 'object';
        }
        unset($node['maxItems'], $node['minItems']);
    }
    if (! $path) {
        $node['properties'][$seg] = array_merge($node['properties'][$seg] ?? [], $schema);
        if ($required) {
            $node['required'][] = $seg;
        }

        return;
    }
    $node['properties'][$seg] = $node['properties'][$seg] ?? ['type' => 'object'];
    setPath($node['properties'][$seg], $path, $schema, false, $toks);
}

function cleanup(array &$s): void
{
    if (isset($s['required'])) {
        $s['required'] = array_values(array_unique($s['required']));
        if (! $s['required']) {
            unset($s['required']);
        }
    }
    if (isset($s['properties'])) {
        if (! $s['properties']) {
            unset($s['properties']);
        } else {
            foreach ($s['properties'] as &$p) {
                cleanup($p);
            }
            unset($p);
        }
    }
    if (isset($s['items']) && is_array($s['items'])) {
        if ($s['items'] === []) {
            $s['items'] = new stdClass;
        } else {
            cleanup($s['items']);
        }
    }
    if (($s['type'] ?? null) === 'array' && ! isset($s['items'])) {
        $s['items'] = new stdClass;
    }
}

function tokensToSchema(array $toks, string $field): array
{
    $names = array_column($toks, 0);
    $has = fn ($n) => in_array($n, $names, true);
    $s = [];
    $desc = [];
    $isFile = false;
    $type = null;
    $enumRef = null;
    foreach ($toks as [$n, $p]) {
        switch ($n) {
            case 'string': $type ??= 'string'; break;
            case 'integer': case 'int': $type = 'integer'; break;
            case 'numeric': case 'decimal': $type = $type === 'integer' ? 'integer' : 'decimal'; if ($n === 'decimal') {
                $desc[] = 'Up to '.end($p).' decimal places.';
            } break;
            case 'boolean': case 'bool': $type = 'boolean'; break;
            case 'accepted': case 'declined': $type = 'boolean'; $desc[] = $n === 'accepted' ? 'Must be accepted (true).' : 'Must be declined (false).'; break;
            case 'array': case 'list': $type = $type === 'object' ? 'object' : 'array'; if ($n === 'array' && $p) {
                $type = 'object';
                $s['x-allowed-keys'] = $p;
            } break;
            case 'date': $type = 'date'; break;
            case 'date_format': $type = preg_match('/[HhisGgU]/', $p[0] ?? '') ? (preg_match('/[Yy]/', $p[0]) ? 'date-time' : 'time') : 'date'; if (($p[0] ?? '') !== 'Y-m-d') {
                $desc[] = 'Format: `'.($p[0] ?? '').'`.';
            } break;
            case 'email': $type = 'string'; $s['format'] = 'email'; break;
            case 'url': case 'active_url': $type = 'string'; $s['format'] = 'uri'; break;
            case 'uuid': $type = 'string'; $s['format'] = 'uuid'; break;
            case 'ulid': $type = 'string'; $s['pattern'] = '^[0-9A-HJKMNP-TV-Z]{26}$'; break;
            case 'ip': case 'ipv4': case 'ipv6': $type = 'string'; $s['format'] = $n === 'ip' ? 'ipv4' : $n; break;
            case 'json': $type = 'string'; $desc[] = 'JSON-encoded string.'; break;
            case 'timezone': $type = 'string'; $desc[] = 'IANA timezone identifier.'; break;
            case 'file': case 'image': $type = 'file'; $isFile = true; if ($n === 'image') {
                $desc[] = 'Image file.';
            } break;
            case 'mimes': case 'mimetypes': case 'extensions': $type = 'file'; $isFile = true; $desc[] = 'Allowed types: '.implode(', ', $p).'.'; break;
            case 'digits': $type = 'string'; $s['pattern'] = '^\d{'.$p[0].'}$'; break;
            case 'digits_between': $type = 'string'; $s['pattern'] = '^\d{'.$p[0].','.$p[1].'}$'; break;
            case 'alpha': $s['pattern'] ??= '^[A-Za-z]+$'; break;
            case 'alpha_dash': $s['pattern'] ??= '^[A-Za-z0-9_-]+$'; break;
            case 'alpha_num': $s['pattern'] ??= '^[A-Za-z0-9]+$'; break;
            case 'lowercase': $desc[] = 'Lowercase.'; break;
            case 'uppercase': $desc[] = 'Uppercase.'; break;
            case 'regex':
                $re = $p[0] ?? '';
                if (preg_match('/^(.)(.*)\1([a-z]*)$/s', $re, $m)) {
                    $s['pattern'] = $m[2];
                }
                break;
            case 'in': $s['enum'] = array_map(fn ($v) => is_string($v) ? trim($v, '"') : $v, $p); break;
            case 'enum_class': if (isEnum($p[0] ?? null)) {
                $enumRef = enumRef($p[0]);
            } break;
            case 'exists': $desc[] = 'Must reference an existing `'.($p[0] ?? '?').'` record in the current company.'; break;
            case 'unique': $desc[] = 'Must be unique'.(($p[0] ?? null) ? ' within `'.$p[0].'`' : '').' (per company).'; break;
            case 'confirmed': $desc[] = 'Requires matching `'.basename(str_replace('.', '/', $field)).'_confirmation`.'; break;
            case 'current_password': $desc[] = "Must equal the user's current password."; break;
            case 'distinct': $desc[] = 'Values must be distinct.'; break;
            case 'present': $desc[] = 'Must be present (may be empty).'; break;
            case 'filled': $desc[] = 'Must not be empty when present.'; break;
            case 'prohibited': $desc[] = 'Prohibited.'; break;
            case '__desc': $desc[] = $p[0]; break;
            case '__custom': $desc[] = 'Validated by `'.$p[0].'`.'; break;
            default:
                if (preg_match('/^(required_|prohibited_|exclude_|missing_)/', $n) || in_array($n, ['gt', 'gte', 'lt', 'lte', 'after', 'after_or_equal', 'before', 'before_or_equal', 'different', 'same', 'starts_with', 'ends_with', 'doesnt_start_with', 'multiple_of', 'not_regex', 'dimensions', 'size_if'], true)) {
                    $desc[] = '`'.$n.($p ? ':'.implode(',', $p) : '').'`.';
                }
        }
    }
    // bounds
    foreach ($toks as [$n, $p]) {
        if (! in_array($n, ['max', 'min', 'between', 'size'], true)) {
            continue;
        }
        $lo = $n === 'between' ? $p[0] : ($n === 'min' ? $p[0] : ($n === 'size' ? $p[0] : null));
        $hi = $n === 'between' ? $p[1] : ($n === 'max' ? $p[0] : ($n === 'size' ? $p[0] : null));
        $num = fn ($v) => $v === null ? null : (str_contains((string) $v, '.') ? (float) $v : (int) $v);
        switch ($type) {
            case 'integer': case 'decimal':
                if ($lo !== null) {
                    $s['minimum'] = $num($lo);
                }
                if ($hi !== null) {
                    $s['maximum'] = $num($hi);
                }
                break;
            case 'array':
                if ($lo !== null) {
                    $s['minItems'] = (int) $lo;
                }
                if ($hi !== null) {
                    $s['maxItems'] = (int) $hi;
                }
                break;
            case 'file':
                if ($hi !== null) {
                    $desc[] = 'Max size '.$hi.' KB.';
                }
                break;
            case 'boolean': case 'date': case 'date-time': break;
            default:
                if ($lo !== null) {
                    $s['minLength'] = (int) $lo;
                }
                if ($hi !== null) {
                    $s['maxLength'] = (int) $hi;
                }
        }
    }
    $nullable = $has('nullable');
    $required = $has('required') || $has('accepted') && ! $has('sometimes');
    if ($has('sometimes') && $has('required')) {
        $desc[] = 'Required when present (`sometimes|required`).';
        $required = false;
    }
    $out = match ($type) {
        'string', null => $type === null && ! $enumRef && ! isset($s['enum']) ? [] : ['type' => 'string'],
        'integer' => ['type' => 'integer'],
        'decimal' => ['type' => ['string', 'number'], 'description' => 'Decimal value; send as a string (e.g. "1250.5000") to avoid float rounding.'],
        'boolean' => ['type' => 'boolean'],
        'array' => ['type' => 'array'],
        'object' => ['type' => 'object'],
        'date' => ['type' => 'string', 'format' => 'date'],
        'date-time' => ['type' => 'string', 'format' => 'date-time'],
        'time' => ['type' => 'string', 'format' => 'time'],
        'file' => ['type' => 'string', 'format' => 'binary', 'contentMediaType' => 'application/octet-stream'],
    };
    if ($type === 'date' && ! $has('date_format')) {
        $out['description'] = 'Any date parseable by PHP; ISO-8601 (`YYYY-MM-DD`) recommended.';
    }
    if ($enumRef) {
        $out = $enumRef;
    }
    if ($type === 'integer' && isset($s['enum'])) {
        $s['enum'] = array_map('intval', $s['enum']);
    }
    $out = array_merge($out, $s);
    if ($desc) {
        $out['description'] = trim(($out['description'] ?? '').' '.implode(' ', array_unique($desc)));
    }
    if ($nullable) {
        if (isset($out['$ref'])) {
            $ref = $out;
            $d = $ref['description'] ?? null;
            unset($ref['description']);
            $out = ['anyOf' => [$ref, ['type' => 'null']]];
            if ($d) {
                $out['description'] = $d;
            }
        } elseif (isset($out['type'])) {
            $out = nullable($out);
        }
    }
    if (isset($out['type']) && $out['type'] === 'array' && ! isset($out['items'])) {
        // items filled by children
    }

    return [$out, $required, $isFile];
}

/** `array` rules without `*`/child rules on map-like names are JSON objects (PHP assoc arrays). */
function objectify(array &$node): void
{
    foreach ($node['properties'] ?? [] as $name => &$p) {
        $t = (array) ($p['type'] ?? []);
        if (in_array('array', $t, true) && ! isset($p['items']) && ! isset($p['properties'])
            && preg_match('/^(params|filters?|preferences|settings|meta|metadata|attributes|options|payload|config|channels|context|data|values|overrides|custom_fields|address)$/', (string) $name)) {
            $p['type'] = in_array('null', $t, true) ? ['object', 'null'] : 'object';
            $p['additionalProperties'] = true;
            unset($p['maxItems'], $p['minItems']);
        }
        objectify($p);
    }
    unset($p);
    if (isset($node['items']) && is_array($node['items'])) {
        objectify($node['items']);
    }
}