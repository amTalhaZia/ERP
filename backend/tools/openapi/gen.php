<?php
// Generates docs/api/openapi.yaml from the live route table + FormRequests + Resources.
// Read-only with respect to the application: nothing in backend/ is written.

ini_set('memory_limit', '2G');
error_reporting(E_ALL & ~E_DEPRECATED);

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require __DIR__.'/lib.php';
require __DIR__.'/rules.php';

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr;
use Symfony\Component\Yaml\Yaml;

$OUT = dirname(__DIR__, 3).'/docs/api/openapi.yaml';
$diag = ['rules' => [], 'responses' => [], 'notes' => []];

// ------------------------------------------------------------------ routes
$routes = [];
foreach (app('router')->getRoutes() as $r) {
    if (! str_starts_with($r->uri(), 'api/')) {
        continue;
    }
    $routes[] = $r;
}
$routeCount = count($routes);

$RECEIVABLES = ['BillController', 'ExpenseClaimController', 'InvoiceController', 'PaymentController', 'ReceivablesReportController'];
$TAGS = [
    'Health' => 'Liveness / readiness probes (public).',
    'Auth' => 'Login, 2FA, password management, session bootstrap and profile/preferences.',
    'Company' => 'Current company profile, branches, departments and members.',
    'Access' => 'Company users, roles, permission catalogue and audit trail.',
    'Notifications' => 'In-app notifications and per-user notification preferences.',
    'HR' => 'Employees, attendance, shifts, leave, holidays, contracts, documents, performance reviews.',
    'Payroll' => 'Pay components, salary structures, tax tables, payroll runs and payslips.',
    'Finance – Ledger' => 'Chart of accounts, financial years/periods, journal entries, tax rates, bank accounts, ledger reports.',
    'Finance – Receivables' => 'Invoices, bills, payments, expense claims and AR/AP reports.',
    'Sales' => 'Customers, CRM (leads, opportunities, pipeline), quotations, sales orders, deliveries.',
    'Procurement' => 'Suppliers, purchase requisitions, purchase orders, goods receipts, purchase returns.',
    'Inventory' => 'Products, categories, units, warehouses, locations, stock levels/movements, adjustments, transfers.',
    'Projects' => 'Projects, members, milestones, tasks, comments, time entries, expenses.',
    'Documents' => 'Document library: folders, uploads, versions, sharing and downloads.',
    'Dashboard' => 'Role-aware dashboard widgets.',
    'Approvals' => 'Unified approval inbox.',
    'Reports' => 'Report catalogue, JSON preview, exports (sync file or async export job).',
    'Search' => 'Global search across modules.',
    'Platform' => 'Platform administration (platform staff only; 404 for everyone else). No tenant context.',
];

function tagFor(string $controller, string $uri): string
{
    global $RECEIVABLES;
    if (str_contains($controller, 'HealthController')) {
        return 'Health';
    }
    if (! preg_match('/Api\\\\V1\\\\([A-Za-z]+)\\\\([A-Za-z]+)$/', $controller, $m)) {
        return 'Health';
    }
    $map = ['Access' => 'Access', 'Approvals' => 'Approvals', 'Auth' => 'Auth', 'Company' => 'Company', 'Dashboard' => 'Dashboard', 'Documents' => 'Documents',
        'HR' => 'HR', 'Inventory' => 'Inventory', 'Notifications' => 'Notifications', 'Payroll' => 'Payroll', 'Platform' => 'Platform', 'Procurement' => 'Procurement',
        'Projects' => 'Projects', 'Reports' => 'Reports', 'Sales' => 'Sales', 'Search' => 'Search'];
    if ($m[1] === 'Finance') {
        return in_array($m[2], $RECEIVABLES, true) ? 'Finance – Receivables' : 'Finance – Ledger';
    }

    return $map[$m[1]] ?? $m[1];
}

// ------------------------------------------------------------------ helpers for controller analysis

function controllerCtx(string $class, Node\Stmt\ClassMethod $node): array
{
    $ctx = ['model' => null, 'vars' => [], 'types' => [], 'assigns' => localAssigns($node), 'depth' => 0, 'class' => $class, 'where' => $class.'@'.$node->name->toString()];
    foreach ($node->params as $p) {
        if ($p->type instanceof Node\Name && $p->var instanceof Expr\Variable) {
            $t = $p->type->toString();
            if (modelMeta($t)) {
                $ctx['vars'][$p->var->name] = $t;
            } else {
                $ctx['types'][$p->var->name] = $t;
            }
        }
    }

    return $ctx;
}

/** Collect the method node + same-class helper methods it calls (depth 2). */
function relatedNodes(string $class, Node\Stmt\ClassMethod $node, int $depth = 2): array
{
    $nodes = [$node];
    if ($depth === 0) {
        return $nodes;
    }
    foreach (nf()->findInstanceOf($node->stmts ?? [], Expr\MethodCall::class) as $mc) {
        if ($mc->var instanceof Expr\Variable && $mc->var->name === 'this' && $mc->name instanceof Node\Identifier) {
            $m = $mc->name->toString();
            if (in_array($m, ['applySearch', 'applyFilters', 'applySort', 'applyDateRange', 'perPage', 'paginated', 'noContent', 'message', 'authorize'], true)) {
                continue;
            }
            try {
                $rm = new ReflectionMethod($class, $m);
                if ($rm->getDeclaringClass()->getName() === App\Http\Controllers\Api\V1\ApiController::class) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }
            $n = methodNode($class, $m);
            if ($n && $n !== $node) {
                foreach (relatedNodes($class, $n, $depth - 1) as $x) {
                    $nodes[] = $x;
                }
            }
        }
    }

    return $nodes;
}

function varClass(Expr $v, array $ctx, array $assigns): ?string
{
    if ($v instanceof Expr\Variable && is_string($v->name)) {
        if (isset($ctx['vars'][$v->name])) {
            return $ctx['vars'][$v->name];
        }
        $a = $assigns[$v->name] ?? null;
        if (! $a) {
            return null;
        }
        $v = $a;
    }
    if ($v instanceof Expr\BinaryOp\Coalesce || $v instanceof Expr\Ternary) {
        $v = $v instanceof Expr\Ternary ? ($v->if ?? $v->cond) : $v->left;
    }
    if (($v instanceof Expr\MethodCall || $v instanceof Expr\NullsafeMethodCall) && $v->name instanceof Node\Identifier) {
        if ($m = chainModel($v)) {
            return $m;
        }
        $svc = serviceClass($v->var, $ctx);
        if ($svc && method_exists($svc, $v->name->toString())) {
            $t = (new ReflectionMethod($svc, $v->name->toString()))->getReturnType();
            if ($t instanceof ReflectionNamedType && ! $t->isBuiltin()) {
                return $t->getName();
            }
        }
    }
    if ($v instanceof Expr\StaticCall) {
        return chainModel($v);
    }

    return null;
}

function policySlugs(string $ability, ?string $target): array
{
    static $cache = [];
    $k = $ability.'|'.$target;
    if (isset($cache[$k])) {
        return $cache[$k];
    }
    $slugs = [];
    try {
        $policy = $target ? Illuminate\Support\Facades\Gate::getPolicyFor($target) : null;
        if ($policy && method_exists($policy, $ability)) {
            $cls = get_class($policy);
            $todo = [$ability];
            $seen = [];
            while ($todo) {
                $m = array_shift($todo);
                if (isset($seen[$m]) || ! ($n = methodNode($cls, $m))) {
                    continue;
                }
                $seen[$m] = true;
                foreach (nf()->findInstanceOf($n->stmts ?? [], Node\Scalar\String_::class) as $str) {
                    if (preg_match('/^[a-z][a-z_-]*(\.[a-z_-]+)+$/', $str->value)) {
                        $slugs[] = $str->value;
                    }
                }
                foreach (nf()->findInstanceOf($n->stmts ?? [], Expr\MethodCall::class) as $mc) {
                    if ($mc->var instanceof Expr\Variable && $mc->var->name === 'this' && $mc->name instanceof Node\Identifier && method_exists($cls, $mc->name->toString())) {
                        $todo[] = $mc->name->toString();
                    }
                }
            }
        }
    } catch (Throwable) {
    }

    return $cache[$k] = array_values(array_unique($slugs));
}

function wrapData(array $inner): array
{
    return ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => $inner]];
}

function paginatedOf(array $item): array
{
    return ['type' => 'object', 'required' => ['data', 'meta'], 'properties' => [
        'data' => ['type' => 'array', 'items' => $item],
        'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
    ]];
}

/** Classify a returned expression into response descriptors. */
function classifyReturn(?Expr $e, array $ctx, int $depth = 0): array
{
    if ($e === null || $depth > 5) {
        return [];
    }
    $class = $ctx['class'];
    // variable → its assignment
    if ($e instanceof Expr\Variable && is_string($e->name) && isset($ctx['assigns'][$e->name])) {
        $a = $ctx['assigns'][$e->name];
        unset($ctx['assigns'][$e->name]);

        return classifyReturn($a, $ctx, $depth + 1);
    }
    if ($e instanceof Expr\Ternary) {
        return array_merge(classifyReturn($e->if ?? $e->cond, $ctx, $depth + 1), classifyReturn($e->else, $ctx, $depth + 1));
    }
    if ($e instanceof Expr\MethodCall && $e->name instanceof Node\Identifier) {
        $m = $e->name->toString();
        $isThis = $e->var instanceof Expr\Variable && $e->var->name === 'this';
        if ($isThis && $m === 'paginated') {
            $res = classOf($e->args[1]->value ?? null);

            return [['status' => 200, 'schema' => paginatedOf($res ? resourceRef($res) : ['type' => 'object']), 'paginated' => true]];
        }
        if ($isThis && $m === 'noContent') {
            return [['status' => 204]];
        }
        if ($isThis && $m === 'message') {
            $schema = ['type' => 'object', 'required' => ['message'], 'properties' => ['message' => ['type' => 'string']]];
            if (isset($e->args[1])) {
                $x = infer($e->args[1]->value, $ctx, null);
                if (isset($x['properties'])) {
                    $schema['properties'] += $x['properties'];
                }
            }
            $status = isset($e->args[2]) && $e->args[2]->value instanceof Node\Scalar\Int_ ? $e->args[2]->value->value : 200;

            return [['status' => $status, 'schema' => $schema]];
        }
        if ($m === 'setStatusCode') {
            $inner = classifyReturn($e->var, $ctx, $depth + 1);
            $st = $e->args[0]->value instanceof Node\Scalar\Int_ ? $e->args[0]->value->value : 200;

            return array_map(fn ($d) => ['status' => $st] + $d, $inner);
        }
        if ($m === 'response' && ! $isThis) {
            return classifyReturn($e->var, $ctx, $depth + 1);
        }
        if ($m === 'additional') {
            $inner = classifyReturn($e->var, $ctx, $depth + 1);
            $x = infer($e->args[0]->value ?? null, $ctx, null);
            foreach ($inner as &$d) {
                if (isset($d['schema']['properties'], $x['properties'])) {
                    $d['schema']['properties'] += $x['properties'];
                }
            }

            return $inner;
        }
        if ($m === 'json' && $e->var instanceof Expr\FuncCall && $e->var->name instanceof Node\Name && $e->var->name->toString() === 'response') {
            $status = isset($e->args[1]) && $e->args[1]->value instanceof Node\Scalar\Int_ ? $e->args[1]->value->value : 200;
            if (! isset($e->args[0]) || ($e->args[0]->value instanceof Expr\ConstFetch && strtolower($e->args[0]->value->name->toString()) === 'null')) {
                return [['status' => $status === 200 ? 204 : $status]];
            }

            return [['status' => $status, 'schema' => infer($e->args[0]->value, $ctx, null) ?: ['type' => 'object', 'additionalProperties' => true], 'adhoc' => true]];
        }
        if ($m === 'noContent' && $e->var instanceof Expr\FuncCall) {
            return [['status' => 204]];
        }
        if (! $isThis) {
            // fluent call on a resource, e.g. (new EmployeeResource(..))->withSensitive(..)
            $inner = classifyReturn($e->var, $ctx, $depth + 1);
            if ($inner) {
                return $inner;
            }
            // delegated to another controller/service returning a response
            $svc = serviceClass($e->var, $ctx);
            if ($svc && ($n = methodNode($svc, $m))) {
                $c2 = controllerCtx($svc, $n);
                $out = [];
                foreach (ownReturns($n) as $r) {
                    $out = array_merge($out, classifyReturn($r->expr, $c2, $depth + 1));
                }

                return $out;
            }
        }
        if ($isThis) {
            $n = methodNode($class, $m);
            if ($n) {
                $c2 = controllerCtx($class, $n);
                // bind closures/args passed to the helper (e.g. $this->range($request, fn () => ...))
                foreach ($n->params as $i => $p) {
                    if (isset($e->args[$i]) && $p->var instanceof Expr\Variable && ($e->args[$i]->value instanceof Expr\Closure || $e->args[$i]->value instanceof Expr\ArrowFunction)) {
                        $c2['assigns'][$p->var->name] = $e->args[$i]->value;
                    }
                }
                $c2['types'] += $ctx['types'];
                $c2['vars'] += $ctx['vars'];
                $out = [];
                foreach (ownReturns($n) as $r) {
                    $out = array_merge($out, classifyReturn($r->expr, $c2, $depth + 1));
                }

                return $out;
            }
        }
    }
    if ($e instanceof Expr\New_ && $e->class instanceof Node\Name && isResource($e->class->toString())) {
        return [['status' => 200, 'schema' => wrapData(resourceRef($e->class->toString()))]];
    }
    if ($e instanceof Expr\StaticCall && $e->class instanceof Node\Name && $e->name instanceof Node\Identifier && isResource($e->class->toString())) {
        $res = $e->class->toString();
        if ($e->name->toString() === 'make') {
            return [['status' => 200, 'schema' => wrapData(resourceRef($res))]];
        }
        if ($e->name->toString() === 'collection') {
            $printer = new PhpParser\PrettyPrinter\Standard;
            $src = isset($e->args[0]) ? $printer->prettyPrintExpr($e->args[0]->value) : '';
            if (str_contains($src, 'paginate(') || (isset($e->args[0]) && $e->args[0]->value instanceof Expr\Variable && isset($ctx['assigns'][$e->args[0]->value->name]) && str_contains($printer->prettyPrintExpr($ctx['assigns'][$e->args[0]->value->name]), 'paginate('))) {
                return [['status' => 200, 'schema' => paginatedOf(resourceRef($res)), 'paginated' => true, 'laravelMeta' => true]];
            }

            return [['status' => 200, 'schema' => wrapData(['type' => 'array', 'items' => resourceRef($res)])]];
        }
    }

    return [];
}

function queryFromNodes(array $nodes, array $ctx): array
{
    $params = [];
    $flags = ['paginated' => false];
    $printer = new PhpParser\PrettyPrinter\Standard;
    foreach ($nodes as $node) {
        $assigns = localAssigns($node);
        foreach (nf()->findInstanceOf($node->stmts ?? [], Expr\MethodCall::class) as $mc) {
            if (! $mc->name instanceof Node\Identifier) {
                continue;
            }
            $m = $mc->name->toString();
            $isThis = $mc->var instanceof Expr\Variable && $mc->var->name === 'this';
            $list = function ($arg) {
                $out = [];
                if ($arg && $arg->value instanceof Expr\Array_) {
                    foreach ($arg->value->items as $it) {
                        if ($it && $it->value instanceof Node\Scalar\String_) {
                            $out[] = $it->value->value;
                        }
                    }
                }

                return $out;
            };
            $modelOfQuery = function ($arg) use ($assigns) {
                $v = $arg?->value;
                if ($v instanceof Expr\Variable && isset($assigns[$v->name])) {
                    $v = $assigns[$v->name];
                }

                return $v instanceof Expr ? chainModel($v) : null;
            };
            if ($isThis && $m === 'applySearch') {
                $cols = $list($mc->args[2] ?? null);
                $params['search'] = ['$ref' => '#/components/parameters/Search', '_desc' => $cols];
            } elseif ($isThis && $m === 'applyFilters') {
                $model = $modelOfQuery($mc->args[0] ?? null);
                foreach ($list($mc->args[2] ?? null) as $col) {
                    $sch = $model ? columnSchema($model, $col) : null;
                    $sch = $sch ? stripNull($sch) : ['type' => 'string'];
                    if (isset($sch['$ref']) && (str_contains($sch['$ref'], 'Money') || str_contains($sch['$ref'], 'Decimal'))) {
                        $sch = ['type' => 'string'];
                    }
                    if (($sch['type'] ?? null) === 'boolean') {
                        $sch = ['type' => 'string', 'enum' => ['true', 'false']];
                    }
                    $params["filter[$col]"] = ['name' => "filter[$col]", 'in' => 'query', 'required' => false,
                        'description' => "Exact-match filter on `$col`. Comma-separate values (or repeat `filter[$col][]`) to match any of several.",
                        'schema' => $sch];
                }
            } elseif ($isThis && $m === 'applySort') {
                $cols = $list($mc->args[2] ?? null);
                $default = isset($mc->args[3]) && $mc->args[3]->value instanceof Node\Scalar\String_ ? $mc->args[3]->value->value : '-id';
                $enum = [];
                foreach ($cols as $c) {
                    $enum[] = $c;
                    $enum[] = '-'.$c;
                }
                $params['sort'] = ['name' => 'sort', 'in' => 'query', 'required' => false,
                    'description' => 'Sort column; prefix with `-` for descending. Unknown columns fall back to the default (`'.$default.'`). The primary key is always appended as a tie-breaker.',
                    'schema' => ['type' => 'string', 'enum' => $enum, 'default' => $default]];
            } elseif ($isThis && $m === 'applyDateRange') {
                $col = strArg($mc->args[2] ?? null) ?? 'date';
                $params['from'] = ['name' => 'from', 'in' => 'query', 'required' => false, 'description' => "Inclusive lower bound on `$col` (UTC day start).", 'schema' => ['type' => 'string', 'format' => 'date']];
                $params['to'] = ['name' => 'to', 'in' => 'query', 'required' => false, 'description' => "Inclusive upper bound on `$col` (UTC day end).", 'schema' => ['type' => 'string', 'format' => 'date']];
            } elseif ($isThis && $m === 'perPage') {
                $flags['paginated'] = true;
            } elseif ($mc->var instanceof Expr\Variable && $mc->var->name === 'request' && in_array($m, ['input', 'query', 'integer', 'boolean', 'string', 'str', 'date', 'filled', 'has', 'get', 'float', 'enum', 'array', 'collect'], true)) {
                $name = strArg($mc->args[0] ?? null);
                if (! $name || in_array($name, ['page', 'per_page', 'search', 'sort', 'filter', 'from', 'to'], true) || str_contains($name, '.')) {
                    continue;
                }
                $sch = match ($m) {
                    'integer' => ['type' => 'integer'],
                    'boolean' => ['type' => 'boolean'],
                    'float' => ['type' => 'number'],
                    'date' => ['type' => 'string', 'format' => 'date'],
                    'array', 'collect' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'enum' => isEnum(classOf($mc->args[1]->value ?? null)) ? enumRef(classOf($mc->args[1]->value)) : ['type' => 'string'],
                    default => nameHeuristic($name) ?: ['type' => 'string'],
                };
                if (isset($sch['$ref']) && str_contains($sch['$ref'], 'Decimal')) {
                    $sch = ['type' => 'string'];
                }
                $sch = stripNull($sch);
                $params[$name] ??= ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => $sch];
            }
        }
    }

    return [$params, $flags];
}

function inlineValidations(array $nodes, string $httpMethod): array
{
    $rules = [];
    foreach ($nodes as $node) {
        $assigns = localAssigns($node);
        foreach (nf()->findInstanceOf($node->stmts ?? [], Expr\MethodCall::class) as $mc) {
            if ($mc->name instanceof Node\Identifier && $mc->name->toString() === 'validate' && isset($mc->args[0]) && $mc->args[0]->value instanceof Expr\Array_) {
                $rules += staticRules($mc->args[0]->value, $httpMethod, $assigns);
            }
        }
        foreach (nf()->findInstanceOf($node->stmts ?? [], Expr\StaticCall::class) as $sc) {
            if ($sc->class instanceof Node\Name && str_ends_with($sc->class->toString(), 'Validator') && $sc->name instanceof Node\Identifier && $sc->name->toString() === 'make'
                && isset($sc->args[1]) && $sc->args[1]->value instanceof Expr\Array_) {
                $rules += staticRules($sc->args[1]->value, $httpMethod, $assigns);
            }
        }
    }

    return $rules;
}

/** Text of method + helpers + injected app classes (depth 2), to detect 409/410 etc. */
function reachableSource(string $class, array $nodes): string
{
    static $fileCache = [];
    $printer = new PhpParser\PrettyPrinter\Standard;
    $src = '';
    $classes = [];
    foreach ($nodes as $n) {
        $src .= $printer->prettyPrint($n->stmts ?? []);
        foreach ($n->params as $p) {
            if ($p->type instanceof Node\Name) {
                $classes[] = $p->type->toString();
            }
        }
        foreach (nf()->find($n->stmts ?? [], fn ($x) => $x instanceof Node\Name) as $nm) {
            $classes[] = $nm->toString();
        }
    }
    $seen = [];
    $queue = array_map(fn ($c) => [$c, 0], array_unique($classes));
    while ($queue) {
        [$c, $d] = array_shift($queue);
        if (isset($seen[$c]) || ! str_starts_with($c, 'App\\') || str_starts_with($c, 'App\\Models') || str_starts_with($c, 'App\\Http\\Resources') || ! class_exists($c)) {
            continue;
        }
        $seen[$c] = true;
        $f = (new ReflectionClass($c))->getFileName();
        $fileCache[$f] ??= file_get_contents($f);
        $src .= $fileCache[$f];
        if ($d < 2 && (str_starts_with($c, 'App\\Actions') || str_starts_with($c, 'App\\Services'))) {
            if (preg_match_all('/^use (App\\\\(?:Actions|Services|Exceptions)\\\\[\w\\\\]+);/m', $fileCache[$f], $um)) {
                foreach ($um[1] as $u) {
                    $queue[] = [$u, $d + 1];
                }
            }
        }
    }

    return $src;
}

function humanSummary(string $name, string $action, string $httpMethod): string
{
    $parts = explode('.', preg_replace('/^api\.v1\./', '', $name));
    $verb = array_pop($parts);
    $nounRaw = $parts ? end($parts) : $verb;
    $noun = str_replace(['-', '_'], ' ', $nounRaw);
    $single = Str::singular($noun);
    $platform = ($parts[0] ?? '') === 'platform' ? ' (platform)' : '';

    return match ($verb) {
        'index' => 'List '.$noun.$platform,
        'store' => 'Create '.$single.$platform,
        'show' => 'Get '.$single.$platform,
        'update' => 'Update '.$single.$platform,
        'destroy' => 'Delete '.$single.$platform,
        default => ucfirst(str_replace(['-', '_'], ' ', $verb)).($parts ? ' – '.$single : '').$platform,
    };
}

function opId(string $name): string
{
    $n = preg_replace('/^api\.v1\./', '', $name);

    return lcfirst(str_replace(' ', '', ucwords(preg_replace('/[^A-Za-z0-9]+/', ' ', $n))));
}

// ------------------------------------------------------------------ build operations
$paths = [];
$opIds = [];
$opCount = 0;
$permissionsAll = [];
$opsMissingResponse = [];

foreach ($routes as $route) {
    $uri = $route->uri();
    $path = '/'.preg_replace('#^api/v1/?#', '', $uri);
    $path = $path === '/' ? '/' : rtrim($path, '/');
    $methods = array_values(array_diff($route->methods(), ['HEAD']));
    $actionName = $route->getActionName();
    [$controller, $method] = str_contains($actionName, '@') ? explode('@', $actionName) : [$actionName, '__invoke'];
    $mw = $route->gatherMiddleware();
    $perms = [];
    $throttles = [];
    $tenant = null;
    $authed = false;
    $guest = false;
    $platform = false;
    $signed = false;
    $pwConfirm = false;
    foreach ($mw as $m) {
        if (! is_string($m)) {
            continue;
        }
        if (str_starts_with($m, 'permission:')) {
            foreach (explode('|', substr($m, 11)) as $p) {
                $perms[] = $p;
            }
        } elseif (str_starts_with($m, 'throttle:')) {
            $throttles[] = substr($m, 9);
        } elseif ($m === 'tenant' || $m === 'tenant:optional') {
            $tenant = $m === 'tenant' ? 'required' : 'optional';
        } elseif (str_starts_with($m, 'auth')) {
            $authed = true;
        } elseif (str_starts_with($m, 'guest')) {
            $guest = true;
        } elseif ($m === 'platform') {
            $platform = true;
        } elseif ($m === 'signed') {
            $signed = true;
        } elseif ($m === 'password.confirm') {
            $pwConfirm = true;
        }
    }
    $perms = array_values(array_unique($perms));
    foreach ($perms as $p) {
        $permissionsAll[$p] = true;
    }
    $tag = tagFor($controller, $uri);
    $node = methodNode($controller, $method);
    $nodes = $node ? relatedNodes($controller, $node) : [];
    $ctx = $node ? controllerCtx($controller, $node) : ['model' => null, 'vars' => [], 'types' => [], 'assigns' => [], 'depth' => 0, 'class' => $controller, 'where' => $actionName];
    $rm = null;
    try {
        $rm = new ReflectionMethod($controller, $method);
    } catch (Throwable) {
    }
    $doc = $rm ? docSummary($rm->getDocComment() ?: null) : null;

    // path parameters
    $pathParams = [];
    preg_match_all('/\{(\w+)\??\}/', $uri, $pm);
    foreach ($pm[1] as $pn) {
        $schema = ['type' => 'string'];
        $desc = null;
        $where = $route->wheres[$pn] ?? null;
        $paramModel = null;
        if ($rm) {
            foreach ($rm->getParameters() as $rp) {
                if ($rp->getName() === $pn && $rp->getType() instanceof ReflectionNamedType && ! $rp->getType()->isBuiltin() && modelMeta($rp->getType()->getName())) {
                    $paramModel = $rp->getType()->getName();
                }
            }
        }
        if ($paramModel) {
            $meta = modelMeta($paramModel);
            $schema = $meta['keyType'] === 'int' ? ['type' => 'integer', 'minimum' => 1] : ['type' => 'string'];
            $desc = 'ID of the '.Str::headline(class_basename($paramModel)).($tenant ? ' (scoped to the active company; other companies\' IDs return 404).' : '.');
        }
        if ($where === '[0-9]+') {
            $schema = ['type' => 'integer', 'minimum' => 1];
        } elseif ($where && str_contains($where, '[\da-fA-F]{8}')) {
            $schema = ['type' => 'string', 'format' => 'uuid'];
        } elseif ($where) {
            $schema['pattern'] = '^'.$where.'$';
        }
        if (! $desc && $pn === 'key' && str_contains($uri, 'reports/')) {
            $desc = 'Report key from `GET /reports` (e.g. `finance.ar_aging`).';
        }
        $p = ['name' => $pn, 'in' => 'path', 'required' => true, 'schema' => $schema];
        if ($desc) {
            $p['description'] = $desc;
        }
        $pathParams[] = $p;
    }

    // policy abilities
    $policies = [];
    $policySlugs = [];
    foreach ($nodes as $n) {
        foreach (nf()->findInstanceOf($n->stmts ?? [], Expr\StaticCall::class) as $sc) {
            if ($sc->class instanceof Node\Name && str_ends_with($sc->class->toString(), 'Gate') && $sc->name instanceof Node\Identifier && in_array($sc->name->toString(), ['authorize', 'allows', 'denies'], true)) {
                $ab = strArg($sc->args[0] ?? null);
                $target = isset($sc->args[1]) ? (classOf($sc->args[1]->value) ?? varClass($sc->args[1]->value, $ctx, localAssigns($n))) : null;
                if ($ab) {
                    $policies[] = $ab.($target ? ' on '.class_basename($target) : '');
                    foreach (policySlugs($ab, $target) as $slug) {
                        $policySlugs[] = $slug;
                    }
                }
            }
        }
    }
    $policies = array_values(array_unique($policies));
    $policySlugs = array_values(array_unique($policySlugs));

    // request rules
    $formRequests = [];
    if ($rm) {
        foreach ($rm->getParameters() as $rp) {
            $t = $rp->getType();
            if ($t instanceof ReflectionNamedType && ! $t->isBuiltin() && is_subclass_of($t->getName(), Illuminate\Foundation\Http\FormRequest::class)) {
                $formRequests[] = $t->getName();
            }
        }
    }

    $src = $nodes ? reachableSource($controller, $nodes) : '';

    foreach ($methods as $hm) {
        $opCount++;
        $lower = strtolower($hm);
        $rules = [];
        $ruleSrc = [];
        foreach ($formRequests as $fr) {
            [$r, $how] = formRequestRules($fr, $hm, $route);
            $rules += $r;
            $ruleSrc[] = class_basename($fr).($how === 'runtime' ? '' : " [$how]");
            if ($how !== 'runtime') {
                $diag['rules'][] = "$hm $path: ".class_basename($fr)." → $how";
            }
        }
        $rules += inlineValidations($nodes, $hm);

        $op = [];
        $op['tags'] = [$tag];
        $op['summary'] = $doc && strlen($doc) <= 90 ? rtrim($doc, '.') : humanSummary($route->getName() ?? $uri, $method, $hm);
        $id = opId($route->getName() ?? ($lower.' '.$uri));
        if (count($methods) > 1 && $hm === 'PATCH') {
            $id .= 'Patch';
        }
        while (isset($opIds[$id])) {
            $id .= 'X';
        }
        $opIds[$id] = true;
        $op['operationId'] = $id;
        $descParts = [];
        if ($doc && strlen($doc) > 90) {
            $descParts[] = $doc;
        }
        if ($perms) {
            $descParts[] = '**Requires permission:** '.implode(' or ', array_map(fn ($p) => "`$p`", $perms)).'.';
        }
        if ($policySlugs) {
            $descParts[] = '**Policy requires:** '.implode(' / ', array_map(fn ($p) => "`$p`", $policySlugs)).' (permission slugs referenced by the policy abilities; some abilities additionally allow the record owner / self-service).';
        }
        if ($policies) {
            $descParts[] = '**Policy checks:** '.implode(', ', array_map(fn ($p) => "`$p`", $policies)).'.';
        }
        if ($platform) {
            $descParts[] = 'Platform administrators only — other users receive **404** so the panel\'s existence is not disclosed. No `X-Company-Id` is needed.';
        }
        if ($pwConfirm) {
            $descParts[] = 'Requires a recent password confirmation (`POST /auth/confirm-password`); otherwise **423**.';
        }
        if ($signed) {
            $descParts[] = 'Must be called through the signed URL that was e-mailed/generated (valid `signature` and `expires` query params); invalid signatures return **403**.';
        }
        if (count($methods) > 1) {
            $descParts[] = 'Registered for both PUT and PATCH (same handler; partial updates are accepted — fields use `sometimes`).';
        }
        $tht = array_values(array_diff($throttles, ['api']));
        if ($tht) {
            $descParts[] = 'Additional rate limit: `throttle:'.implode('`, `throttle:', $tht).'`.';
        }
        $descParts[] = 'Handler: `'.class_basename($controller).'@'.$method.'`'.($ruleSrc ? '; validated by `'.implode('`, `', $ruleSrc).'`' : '').'.';
        $op['description'] = implode("\n\n", $descParts);
        $op['x-permissions'] = array_values(array_unique(array_merge($perms, $policySlugs)));
        if ($policies) {
            $op['x-policy'] = $policies;
        }
        $op['x-throttle'] = $throttles ?: ['api'];

        // security
        $mutating = ! in_array($hm, ['GET', 'HEAD', 'OPTIONS'], true);
        if (! $authed) {
            $op['security'] = $mutating ? [['xsrfToken' => []]] : [new stdClass];
        } else {
            $op['security'] = $mutating ? [['cookieAuth' => [], 'xsrfToken' => []]] : [['cookieAuth' => []]];
        }

        // parameters
        $params = [];
        if ($tenant === 'required') {
            $params[] = ['$ref' => '#/components/parameters/CompanyHeader'];
        } elseif ($tenant === 'optional') {
            $params[] = ['$ref' => '#/components/parameters/CompanyHeaderOptional'];
        }
        foreach ($pathParams as $pp) {
            $params[] = $pp;
        }
        [$qp, $flags] = queryFromNodes($nodes, $ctx);
        $isGetLike = in_array($hm, ['GET', 'DELETE'], true);
        $responses = [];
        foreach ($nodes ? ownReturns($node) : [] as $ret) {
            foreach (classifyReturn($ret->expr, $ctx) as $d) {
                $responses[] = $d;
            }
        }
        $paginated = $flags['paginated'] || (bool) array_filter($responses, fn ($d) => $d['paginated'] ?? false);
        if ($hm === 'GET') {
            if ($paginated) {
                $params[] = ['$ref' => '#/components/parameters/Page'];
                $params[] = ['$ref' => '#/components/parameters/PerPage'];
            }
            if (isset($qp['search'])) {
                $params[] = ['$ref' => '#/components/parameters/Search'];
                if ($qp['search']['_desc']) {
                    $op['description'] .= "\n\n`search` matches (case-insensitive, substring): ".implode(', ', array_map(fn ($c) => "`$c`", $qp['search']['_desc'])).'.';
                }
                unset($qp['search']);
            }
            foreach ($qp as $k => $p) {
                $params[] = $p;
            }
        }
        $body = null;
        $multipart = false;
        if ($rules) {
            [$schema, $multipart] = rulesToSchema($rules);
            if ($isGetLike) {
                $have = array_column(array_filter($params, fn ($p) => isset($p['name'])), 'name');
                $haveRefs = array_column(array_filter($params, fn ($p) => isset($p['$ref'])), '$ref');
                foreach ($schema['properties'] ?? [] as $name => $ps) {
                    if ($name === 'page' && in_array('#/components/parameters/Page', $haveRefs, true) || $name === 'per_page' && in_array('#/components/parameters/PerPage', $haveRefs, true)) {
                        continue;
                    }
                    if ($name === 'search' && in_array('#/components/parameters/Search', $haveRefs, true)) {
                        continue;
                    }
                    $isObj = ($ps['type'] ?? null) === 'object' || (is_array($ps['type'] ?? null) && in_array('object', $ps['type'], true));
                    $isArr = ($ps['type'] ?? null) === 'array' || (is_array($ps['type'] ?? null) && in_array('array', $ps['type'], true));
                    $pname = $isArr ? $name.'[]' : $name;
                    $param = ['name' => $pname, 'in' => 'query', 'required' => in_array($name, $schema['required'] ?? [], true), 'schema' => $ps];
                    if ($isObj) {
                        $param['style'] = 'deepObject';
                        $param['explode'] = true;
                        $param['name'] = $name;
                    }
                    if (isset($ps['description'])) {
                        $param['description'] = $ps['description'];
                    }
                    // replace a weaker inferred param of same name
                    $params = array_values(array_filter($params, fn ($p) => ($p['name'] ?? null) !== $name && ($p['name'] ?? null) !== $pname));
                    $params[] = $param;
                }
            } else {
                $ct = $multipart ? 'multipart/form-data' : 'application/json';
                $content = [$ct => ['schema' => $schema]];
                if ($multipart) {
                    $content['multipart/form-data']['encoding'] = new stdClass;
                }
                $body = ['required' => ! empty($schema['required']), 'content' => $content];
                if ($multipart && in_array($hm, ['PUT', 'PATCH'], true)) {
                    $body['description'] = 'PHP does not parse multipart bodies on PUT/PATCH: send `POST` with `_method=PUT|PATCH` when uploading files.';
                }
            }
        }
        if ($params) {
            $op['parameters'] = $params;
        }
        if ($body) {
            $op['requestBody'] = $body;
        }

        // responses
        $resp = [];
        $retType = $rm && $rm->getReturnType() instanceof ReflectionNamedType ? $rm->getReturnType()->getName() : ($rm && $rm->getReturnType() ? (string) $rm->getReturnType() : '');
        $fileCts = null;
        if (preg_match('/StreamedResponse|BinaryFileResponse|^Symfony\\\\Component\\\\HttpFoundation\\\\Response$|^Illuminate\\\\Http\\\\Response$/', $retType)) {
            if (str_contains($path, 'pdf')) {
                $fileCts = ['application/pdf'];
            } elseif (str_contains($controller, 'PayrollRunController')) {
                $fileCts = ['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            } elseif (str_contains($controller, 'Reports\\')) {
                $fileCts = ['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/pdf'];
            } else {
                $fileCts = ['application/octet-stream'];
            }
        }
        foreach ($responses as $d) {
            $st = (string) $d['status'];
            if ($d['status'] === 204) {
                $resp[$st] = ['description' => 'No content.'];

                continue;
            }
            if (! isset($resp[$st])) {
                $resp[$st] = ['description' => match ($d['status']) { 201 => 'Created.', 202 => 'Accepted — processing asynchronously.', default => 'OK.' },
                    'content' => ['application/json' => ['schema' => $d['schema']]]];
                if ($d['adhoc'] ?? false) {
                    $resp[$st]['x-schema-source'] = 'inferred-from-code';
                }
            } elseif (($resp[$st]['content']['application/json']['schema'] ?? null) != $d['schema'] && isset($resp[$st]['content'])) {
                $cur = $resp[$st]['content']['application/json']['schema'];
                $alts = isset($cur['oneOf']) ? $cur['oneOf'] : [$cur];
                if (! in_array($d['schema'], $alts)) {
                    $alts[] = $d['schema'];
                }
                $resp[$st]['content']['application/json']['schema'] = ['oneOf' => $alts];
            }
        }
        if ($fileCts) {
            $content = [];
            foreach ($fileCts as $ct) {
                $content[$ct] = ['schema' => ['type' => 'string', 'format' => 'binary', 'contentMediaType' => $ct]];
            }
            $resp['200'] = ['description' => 'File download (`Content-Disposition: attachment`).'.($fileCts === ['application/octet-stream'] ? ' The actual `Content-Type` is the stored file\'s MIME type.' : ''),
                'headers' => ['Content-Disposition' => ['description' => 'attachment; filename="…"', 'schema' => ['type' => 'string']]],
                'content' => $content] + ($resp['200'] ?? []);
            $resp['200']['content'] = $content;
        }
        if (! $resp) {
            $opsMissingResponse[] = "$hm $path ($actionName)";
            $resp['200'] = ['description' => 'OK.', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => true]]]];
        }
        // errors
        if ($authed) {
            $resp['401'] = ['$ref' => '#/components/responses/Unauthenticated'];
        }
        if ($perms || $tenant === 'required' || $policies || $signed) {
            $resp['403'] = ['$ref' => '#/components/responses/Forbidden'];
        }
        if ($pathParams || $platform || str_contains($src, 'abort(404') || str_contains($src, ', 404')) {
            $resp['404'] = ['$ref' => '#/components/responses/NotFound'];
        }
        if (preg_match('/\b409\b|ConflictHttpException|FinanceException|InsufficientStockException|RecordStockMovementAction/', $src)) {
            $resp['409'] = ['$ref' => '#/components/responses/Conflict'];
        }
        if (preg_match('/abort(_if|_unless)?\([^;]*\b410\b/', $src)) {
            $resp['410'] = ['$ref' => '#/components/responses/Gone'];
        }
        if ($mutating) {
            $resp['419'] = ['$ref' => '#/components/responses/CsrfMismatch'];
        }
        if ($pwConfirm) {
            $resp['423'] = ['$ref' => '#/components/responses/PasswordConfirmationRequired'];
        }
        if ($rules || $body || str_contains($src, 'ValidationException') || str_contains($src, 'TenantMismatch')) {
            $resp['422'] = ['$ref' => '#/components/responses/Validation'];
        }
        $resp['429'] = ['$ref' => '#/components/responses/Throttled'];
        ksort($resp, SORT_STRING);
        $op['responses'] = $resp;

        $paths[$path][$lower] = $op;
    }
}

// ------------------------------------------------------------------ build resource components
$resourceSchemas = [];
do {
    $pending = array_keys(array_filter($GLOBALS['RESOURCES'], fn ($v) => $v === false));
    foreach ($pending as $cls) {
        $GLOBALS['RESOURCES'][$cls] = buildResource($cls);
    }
} while ($pending);

// names
function componentNames(array $classes, string $suffixStrip): array
{
    $byShort = [];
    foreach ($classes as $c) {
        $short = preg_replace('/'.$suffixStrip.'$/', '', class_basename($c));
        $byShort[$short][] = $c;
    }
    $names = [];
    foreach ($byShort as $short => $list) {
        foreach ($list as $c) {
            if (count($list) === 1) {
                $names[$c] = $short;
            } else {
                preg_match('/\\\\(?:Enums|Resources|Models)\\\\(\w+)\\\\/', $c, $m);
                $names[$c] = ($m[1] ?? 'X').$short;
            }
        }
    }

    return $names;
}

// register every enum in app/Enums too
foreach (glob(BASE.'/app/Enums/*/*.php') as $f) {
    $cls = str_replace([BASE.'/app/', '/', '.php'], ['App\\', '\\', ''], $f);
    if (isEnum($cls)) {
        enumRef($cls);
    }
}
// enums referenced while building resources were added already
$enumNames = componentNames(array_keys($GLOBALS['ENUMS']), '');
$resNames = componentNames(array_keys($GLOBALS['RESOURCES']), 'Resource');

$schemas = [];
// base schemas
$schemas['Money'] = ['type' => 'string', 'pattern' => '^-?\d+\.\d{4}$', 'description' => 'Monetary amount as a DECIMAL(18,4) string, e.g. `"1250.5000"`. Never a float. The currency is given by the owning document/company (`currency`).', 'examples' => ['1250.5000']];
$schemas['Decimal'] = ['type' => 'string', 'pattern' => '^-?\d+(\.\d+)?$', 'description' => 'Exact decimal as a string (quantities, rates, hours, coordinates, aggregates). Never a float.', 'examples' => ['12.5000']];
$schemas['Pagination'] = ['type' => 'object', 'required' => ['page', 'per_page', 'total', 'last_page'], 'properties' => [
    'page' => ['type' => 'integer', 'minimum' => 1], 'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
    'total' => ['type' => 'integer', 'minimum' => 0], 'last_page' => ['type' => 'integer', 'minimum' => 1]]];
$schemas['PaginationMeta'] = ['type' => 'object', 'required' => ['pagination'], 'properties' => ['pagination' => ['$ref' => '#/components/schemas/Pagination']],
    'description' => 'List metadata. (Laravel paginator `links`/extra meta keys may also be present on a few endpoints that return a raw paginated collection; clients should rely on `meta.pagination`.)', 'additionalProperties' => true];
$schemas['Error'] = ['type' => 'object', 'required' => ['message', 'code'], 'properties' => [
    'message' => ['type' => 'string', 'description' => 'Human-readable, safe to display.'],
    'code' => ['type' => 'string', 'description' => 'Stable machine code (see the error code table in the API description).', 'examples' => ['auth.forbidden']],
    'details' => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Optional structured context (e.g. `inventory.insufficient_stock` includes product_id, sku, warehouse_id, location_id, available, requested).'],
    'debug' => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Only when APP_DEBUG=true on 500 responses. Never present in production.'],
]];
$schemas['ValidationError'] = ['allOf' => [['$ref' => '#/components/schemas/Error'], ['type' => 'object', 'required' => ['errors'], 'properties' => [
    'code' => ['type' => 'string', 'enum' => ['validation.failed', 'tenant.mismatch', 'finance.unbalanced']],
    'errors' => ['type' => 'object', 'description' => 'Field path (dot notation, e.g. `lines.0.quantity`) → list of messages. Absent for `tenant.mismatch`/`finance.unbalanced`.', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
]]]];
$schemas['UserRef'] = ['type' => 'object', 'required' => ['id', 'name'], 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']], 'description' => 'Minimal user reference.'];
$schemas['UserSummary'] = ['type' => 'object', 'required' => ['id', 'name'], 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'avatar_url' => ['type' => ['string', 'null'], 'format' => 'uri']]];
$schemas['Message'] = ['type' => 'object', 'required' => ['message'], 'properties' => ['message' => ['type' => 'string']]];

foreach ($GLOBALS['ENUMS'] as $cls => $_) {
    $vals = array_map(fn ($c) => $c->value, $cls::cases());
    $schemas[$enumNames[$cls]] = ['type' => is_int($vals[0] ?? '') ? 'integer' : 'string', 'enum' => $vals, 'x-enum-class' => $cls];
}
foreach ($GLOBALS['RESOURCES'] as $cls => $s) {
    unset($s['x-model']);
    $s['x-resource-class'] = $cls;
    $schemas[$resNames[$cls]] = $s;
}

// ------------------------------------------------------------------ assemble document
$sessionCookie = config('session.cookie');
$total = $routeCount;
$methodsBreakdown = [];
foreach ($routes as $r) {
    $k = implode('|', array_diff($r->methods(), ['HEAD']));
    $methodsBreakdown[$k] = ($methodsBreakdown[$k] ?? 0) + 1;
}
ksort($methodsBreakdown);
$breakdown = implode(', ', array_map(fn ($k, $v) => "$k: $v", array_keys($methodsBreakdown), $methodsBreakdown));
$pathCount = count($paths);

$description = <<<MD
REST API of the Enterprise ERP (Laravel 13, Sanctum SPA authentication). Every route registered under `api/` is documented here:
**{$total} routes** (`php artisan route:list --path=api --except-vendor`; {$breakdown}) → **{$opCount} operations** on **{$pathCount} paths**
(routes registered for `PUT|PATCH` are documented as two operations sharing one handler).

Schemas are derived from the source: request bodies from `app/Http/Requests/**` (and inline `\$request->validate()` calls), responses from
`app/Http/Resources/**`, enums from `app/Enums/**`, column types from the migrations. Responses marked `x-schema-source: inferred-from-code` are
ad-hoc JSON (reports, dashboards, summaries) whose shape was inferred from the controller/service code and may be less precise.

## Authentication (Sanctum SPA cookies)
1. `GET /sanctum/csrf-cookie` (web route, outside `/api/v1`) — sets the `XSRF-TOKEN` cookie.
2. `POST /auth/login` with header `X-XSRF-TOKEN: <decoded XSRF-TOKEN cookie>` → session cookie (`{$sessionCookie}`; name comes from `SESSION_COOKIE`, Laravel's default would be `laravel_session`). If the user has 2FA enabled the response asks for `POST /auth/2fa/challenge`.
3. `GET /auth/session` → user, company memberships, `current_company`, `permissions` (slugs for the active company) and branches.
4. Every subsequent request sends the session cookie (`withCredentials`), and every **mutating** request (POST/PUT/PATCH/DELETE) also sends `X-XSRF-TOKEN`; a missing/stale token yields **419** `auth.csrf`.
5. `POST /auth/logout` ends the session. Password change/reset invalidates all other sessions.

Sensitive operations protected by `password.confirm` need `POST /auth/confirm-password` within the last 15 minutes, otherwise **423**.

## Tenancy (`X-Company-Id`)
All module routes (everything except `/health*`, `/auth/*` and `/platform/*`) run inside a company context. Send the active company's id in the
`X-Company-Id` header (file downloads opened in a new tab may use the `company_id` query parameter instead). The server verifies an active
membership and otherwise answers **403** `tenant.forbidden`. Record ids belonging to another company always return **404** (existence is never
disclosed); referencing another company's id inside a request body returns **422** `tenant.mismatch`. Users with branch restrictions only see
their branches' records.

## Authorization
Coarse RBAC via `permission:<slug>` route middleware (any one of the listed slugs suffices) plus
fine-grained Policies (`x-policy`). `x-permissions` on each operation lists the slugs from both the route middleware and the policy abilities it calls; the description of each operation separates the two. The permission slugs available to the current user are in `GET /auth/session` → `permissions`.

## Errors
Every error uses the envelope `{ "message": string, "code": string, "errors"?: {field: [messages]}, "details"?: object }`.

| HTTP | code | Meaning |
|---|---|---|
| 401 | `auth.unauthenticated` | No/expired session. |
| 403 | `auth.forbidden` | Missing permission or policy denied. |
| 403 | `tenant.forbidden` | No `X-Company-Id` or no active membership in that company. |
| 404 | `not_found` | Unknown route/record, other company's record, or platform route for non-platform users. |
| 409 | `conflict` | Business-state conflict (e.g. approving a non-pending record). |
| 409 | `finance.period_closed`, `finance.no_period`, `finance.immutable` | Ledger refusals. |
| 409 | `inventory.insufficient_stock` | Outbound movement exceeds stock (`details` has the figures). |
| 410 | `http.410` | Expired export file. |
| 419 | `auth.csrf` | CSRF token missing or session expired. |
| 422 | `validation.failed` | Validation errors in `errors`. |
| 422 | `tenant.mismatch` | A referenced id belongs to another company. |
| 422 | `finance.unbalanced` | Journal entry debits ≠ credits. |
| 423 | `http.423` | Password confirmation required. |
| 429 | `throttled` | Rate limit hit (`Retry-After` header). |
| 500 | `server.error` | Unexpected error (no stack traces unless APP_DEBUG). |

## Lists: pagination, search, sorting, filtering
* `page` (≥1), `per_page` (default 15, max 100).
* `search` — case-insensitive substring match on the allow-listed columns named in each operation.
* `sort` — one column from the per-operation allow-list, `-` prefix for descending (e.g. `sort=-created_at`).
* `filter[<column>]=value` — exact match; `filter[status]=draft,approved` or `filter[status][]=draft&filter[status][]=approved` matches any; booleans as `true`/`false`.
* `from` / `to` — inclusive `YYYY-MM-DD` date range on the list's main date column.
* Response: `{ "data": [...], "meta": { "pagination": { "page", "per_page", "total", "last_page" } } }`.
* Single resources: `{ "data": {...} }`. Create returns **201**, delete returns **204** with an empty body.

## Money, numbers & dates
* Money is a `DECIMAL(18,4)` **string** (`Money`, e.g. `"1250.5000"`), never a float; currency is the ISO-4217 code on the document or company.
  Quantities/rates/hours are exact decimal strings too (`Decimal`). Clients should send decimals as strings.
* Timestamps are ISO-8601 UTC (`2026-10-15T09:30:00.000000Z`); plain dates are `YYYY-MM-DD`. Companies carry a `timezone` for display only.

## Rate limits
* `api`: 240 requests/minute per user (per IP when anonymous) — applies to every route.
* `login`: 5/minute per email+IP and 30/minute per IP (`POST /auth/login`).
* `auth`: 10/minute per IP (forgot/reset password, confirm-password, e-mail verification).
* `two-factor`: 5/minute per pending 2FA session (`/auth/2fa/challenge`, `/auth/2fa/confirm`).
* `exports`: 10/minute per user (report/data exports). `search`: 60/minute.
Each operation lists its limiters in `x-throttle`.
MD;

$doc = [
    'openapi' => '3.1.0',
    'info' => [
        'title' => 'Enterprise ERP API',
        'version' => '1.0.0',
        'description' => $description,
        'contact' => ['name' => 'ERP Engineering'],
        'license' => ['name' => 'Proprietary', 'identifier' => 'LicenseRef-Proprietary'],
    ],
    'servers' => [['url' => 'http://localhost:8000/api/v1', 'description' => 'Local development']],
    'security' => [['cookieAuth' => []]],
    'tags' => array_map(fn ($k, $v) => ['name' => $k, 'description' => $v], array_keys($TAGS), $TAGS),
    'paths' => $paths,
    'components' => [
        'securitySchemes' => [
            'cookieAuth' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => $sessionCookie,
                'description' => "Laravel session cookie issued by `POST /auth/login` (Sanctum SPA stateful auth). Cookie name = `config('session.cookie')`, currently `{$sessionCookie}` (Laravel's default is `laravel_session`; set via `SESSION_COOKIE`). HttpOnly, SameSite=Lax, Secure in production."],
            'xsrfToken' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-XSRF-TOKEN',
                'description' => 'URL-decoded value of the `XSRF-TOKEN` cookie set by `GET /sanctum/csrf-cookie`. Required on every state-changing request.'],
        ],
        'parameters' => [
            'CompanyHeader' => ['name' => 'X-Company-Id', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1],
                'description' => 'Active company (tenant) id. Must be a company the user is an active member of, else 403 `tenant.forbidden`. For downloads the `company_id` query parameter is accepted instead.'],
            'CompanyHeaderOptional' => ['name' => 'X-Company-Id', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1],
                'description' => 'Optional active company id; when omitted the default membership is used (or none).'],
            'Page' => ['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1], 'description' => '1-based page number.'],
            'PerPage' => ['name' => 'per_page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => (int) config('erp.pagination.default', 15)], 'description' => 'Page size (clamped to 1–100).'],
            'Search' => ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'maxLength' => 200], 'description' => 'Case-insensitive substring search across the endpoint\'s searchable columns.'],
            'Sort' => ['name' => 'sort', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'examples' => ['-created_at']], 'description' => 'Sort column from the endpoint allow-list; `-` prefix = descending.'],
        ],
        'schemas' => $schemas,
        'responses' => [
            'Unauthenticated' => errResp('Not authenticated.', 'auth.unauthenticated', 'Unauthenticated.'),
            'Forbidden' => errResp('Missing permission, policy denial, or no access to the company in `X-Company-Id`.', ['auth.forbidden', 'tenant.forbidden'], "You don't have permission to perform this action."),
            'NotFound' => errResp('Record not found (including records of other companies).', 'not_found', 'The requested record was not found.'),
            'Conflict' => errResp('The record is not in a state that allows this action.', ['conflict', 'finance.period_closed', 'finance.no_period', 'finance.immutable', 'inventory.insufficient_stock'], 'Only pending leave requests can be approved.'),
            'Gone' => errResp('The resource has expired.', 'http.410', 'This export has expired. Please run it again.'),
            'CsrfMismatch' => errResp('CSRF token missing/invalid or session expired.', 'auth.csrf', 'Your session has expired. Please refresh and try again.'),
            'PasswordConfirmationRequired' => errResp('Recent password confirmation required.', 'http.423', 'Password confirmation required.'),
            'Validation' => ['description' => 'Validation failed.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError'],
                'example' => ['message' => 'Please correct the highlighted fields.', 'code' => 'validation.failed', 'errors' => ['name' => ['The name field is required.']]]]]],
            'Throttled' => ['description' => 'Too many requests.', 'headers' => [
                'Retry-After' => ['schema' => ['type' => 'integer'], 'description' => 'Seconds to wait.'],
                'X-RateLimit-Limit' => ['schema' => ['type' => 'integer']],
                'X-RateLimit-Remaining' => ['schema' => ['type' => 'integer']]],
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error'], 'example' => ['message' => 'Too many attempts. Please wait a moment and try again.', 'code' => 'throttled']]]],
        ],
    ],
];

function errResp(string $desc, $codes, string $msg): array
{
    $codes = (array) $codes;

    return ['description' => $desc, 'content' => ['application/json' => [
        'schema' => ['allOf' => [['$ref' => '#/components/schemas/Error'], ['type' => 'object', 'properties' => ['code' => ['type' => 'string', 'enum' => $codes]]]]],
        'example' => ['message' => $msg, 'code' => $codes[0]],
    ]]];
}

// resolve placeholder refs
$json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
$json = preg_replace_callback('#"\#/components/schemas/__ENUM__([^"]+)"#', fn ($m) => '"#/components/schemas/'.$enumNames[(str_replace('\\\\', '\\', $m[1]))].'"', $json);
$json = preg_replace_callback('#"\#/components/schemas/__RES__([^"]+)"#', fn ($m) => '"#/components/schemas/'.$resNames[str_replace('\\\\', '\\', $m[1])].'"', $json);
$doc = json_decode($json, false);

@mkdir(dirname($OUT), 0777, true);
$yaml = Yaml::dump($doc, 64, 2, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_NUMERIC_KEY_AS_STRING);
file_put_contents($OUT, "# Generated from the route table, FormRequests, API Resources and Enums of backend/ (Laravel 13).\n".$yaml);

$unknown = array_count_values($GLOBALS['UNKNOWN']);
file_put_contents(sys_get_temp_dir().'/openapi-diag.json', json_encode([
    'routes' => $routeCount, 'operations' => $opCount, 'paths' => $pathCount,
    'rules' => $diag['rules'], 'missingResponse' => $opsMissingResponse, 'unknownFields' => array_keys($unknown),
    'resources' => count($GLOBALS['RESOURCES']), 'enums' => count($GLOBALS['ENUMS']), 'permissions' => array_keys($permissionsAll),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "routes=$routeCount operations=$opCount paths=$pathCount resources=".count($GLOBALS['RESOURCES']).' enums='.count($GLOBALS['ENUMS'])."\n";
