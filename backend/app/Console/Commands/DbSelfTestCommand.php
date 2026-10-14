<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * End-to-end round trip against the REAL configured database using temporary
 * tables, so it is safe to run anywhere: create → read → update → delete,
 * foreign-key enforcement, and transaction rollback.
 */
class DbSelfTestCommand extends Command
{
    protected $signature = 'db:selftest';

    protected $description = 'Round-trip CRUD, foreign key and transaction checks against the configured database';

    public function handle(): int
    {
        $suffix = Str::lower(Str::random(6));
        $parent = "_selftest_parent_{$suffix}";
        $child = "_selftest_child_{$suffix}";
        $failed = false;

        try {
            Schema::create($parent, function ($t) {
                $t->id();
                $t->string('name', 50);
                $t->decimal('amount', 18, 4);
                $t->timestamps();
            });
            Schema::create($child, function ($t) use ($parent) {
                $t->id();
                $t->foreignId('parent_id')->constrained($parent)->cascadeOnDelete();
                $t->string('note', 50);
            });
            $this->pass('1. Schema: tables created with foreign key');

            $id = DB::table($parent)->insertGetId(['name' => 'alpha', 'amount' => '1234.5678', 'created_at' => now(), 'updated_at' => now()]);
            $this->pass("2. Create: inserted row #{$id}");

            $row = DB::table($parent)->find($id);
            $this->check($row?->name === 'alpha' && (string) $row->amount === '1234.5678', '3. Read: row read back with exact decimal (1234.5678)');

            DB::table($parent)->where('id', $id)->update(['name' => 'beta']);
            $this->check(DB::table($parent)->where('id', $id)->value('name') === 'beta', '4. Update: value changed');

            $fkEnforced = false;
            try {
                DB::table($child)->insert(['parent_id' => 999999, 'note' => 'orphan']);
            } catch (QueryException) {
                $fkEnforced = true;
            }
            $this->check($fkEnforced, '5. Foreign key: orphan insert rejected');

            DB::table($child)->insert(['parent_id' => $id, 'note' => 'ok']);
            try {
                DB::transaction(function () use ($parent, $id): void {
                    DB::table($parent)->where('id', $id)->update(['name' => 'should-rollback']);
                    throw new \RuntimeException('force rollback');
                });
            } catch (\RuntimeException) {
            }
            $this->check(DB::table($parent)->where('id', $id)->value('name') === 'beta', '6. Transaction: rollback restored previous value');

            DB::table($parent)->where('id', $id)->delete();
            $this->check(DB::table($child)->count() === 0, '7. Delete: cascade removed child rows');

            $mode = DB::selectOne('SELECT @@sql_mode AS m')->m ?? '';
            $this->check(str_contains($mode, 'STRICT_TRANS_TABLES'), '8. Strict SQL mode active ('.Str::limit($mode, 60).')');
        } catch (Throwable $e) {
            $failed = true;
            $this->components->error('Self-test aborted: '.$e->getMessage());
        } finally {
            Schema::dropIfExists($child);
            Schema::dropIfExists($parent);
        }

        $failed = $failed || $this->failures > 0;
        $failed ? $this->components->error("{$this->failures} check(s) failed.") : $this->components->info('All database self-tests passed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private int $failures = 0;

    private function pass(string $label): void
    {
        $this->components->twoColumnDetail($label, '<fg=green>PASS</>');
    }

    private function check(bool $ok, string $label): void
    {
        if (! $ok) {
            $this->failures++;
        }
        $this->components->twoColumnDetail($label, $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>');
    }
}
