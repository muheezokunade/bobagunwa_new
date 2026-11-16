<?php

namespace App\Console\Commands;

use App\Services\IntegrityMonitor;
use Illuminate\Console\Command;

/**
 * Scheduled daily (routes/console.php). Records the run, raises or clears
 * integrity alerts and exits non-zero on failure so cron mail (if any) shows it.
 */
class LedgerVerify extends Command
{
    protected $signature = 'boba:ledger:verify {--trigger=schedule : schedule|manual|restore}';

    protected $description = 'Check integrity triggers and ledger invariants; raise or clear integrity alerts';

    public function handle(IntegrityMonitor $monitor): int
    {
        $trigger = in_array($this->option('trigger'), ['schedule', 'manual', 'restore'], true) ? $this->option('trigger') : 'schedule';
        $run = $monitor->run($trigger);
        foreach ($run->missing_triggers ?? [] as $name) {
            $this->error("Missing trigger: {$name}");
        }
        foreach ($run->exception_counts ?? [] as $label => $n) {
            $this->error("{$label}: {$n}");
        }
        if ($run->status === 'error') {
            $this->error('The integrity check could not complete: '.$run->error);
        }
        if ($run->status !== 'passed') {
            return self::FAILURE;
        }
        $this->info("Ledger verified: {$run->triggers_installed} triggers installed, no invariant exceptions.");

        return self::SUCCESS;
    }
}
