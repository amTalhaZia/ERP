<?php

namespace App\Console\Commands\Documents;

use App\Models\Company;
use App\Models\Documents\Document;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Archives documents whose `retention_until` date has passed. Archiving only
 * flags the document (is_archived + archived_at, audited); nothing is
 * hard-deleted — purging files is a deliberate manual/legal decision.
 */
class ApplyDocumentRetention extends Command
{
    protected $signature = 'documents:apply-retention
        {--company= : Only process this company id}
        {--dry-run : Report what would be archived without changing anything}';

    protected $description = 'Archive documents past their retention date (no deletion)';

    public function handle(TenantContext $tenant): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = today()->toDateString();
        $total = 0;

        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id');

        try {
            foreach ($companies->lazyById(100) as $company) {
                $tenant->set($company, null);

                $expired = Document::query()
                    ->where('is_archived', false)
                    ->whereNotNull('retention_until')
                    ->whereDate('retention_until', '<', $today);

                $count = 0;

                if ($dryRun) {
                    $count = $expired->count();
                } else {
                    $expired->chunkById(200, function ($documents) use (&$count): void {
                        foreach ($documents as $document) {
                            $document->forceFill(['is_archived' => true, 'archived_at' => now()])->save();
                            $count++;
                        }
                    });
                }

                $total += $count;

                if ($count > 0) {
                    $this->components->twoColumnDetail($company->name, ($dryRun ? 'would archive ' : 'archived ').$count);
                }
            }
        } finally {
            $tenant->clear();
        }

        $this->components->info(($dryRun ? 'Dry run: ' : '')."{$total} document(s) past retention ".($dryRun ? 'would be archived.' : 'archived.'));

        return self::SUCCESS;
    }
}
