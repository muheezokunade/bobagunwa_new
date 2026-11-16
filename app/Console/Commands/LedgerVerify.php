<?php

namespace App\Console\Commands;

use App\Services\Finance\LedgerQuery;
use App\Support\Audit;
use App\Support\IntegrityTriggers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LedgerVerify extends Command
{
    protected $signature = 'boba:ledger:verify';

    protected $description = 'Check that integrity triggers are installed and no ledger invariant is violated';

    public function handle(LedgerQuery $ledger): int
    {
        $installed = collect(DB::select('SELECT TRIGGER_NAME AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'))->pluck('n')->all();
        $missing = array_diff(IntegrityTriggers::names(), $installed);
        $exceptions = array_filter($ledger->exceptions());
        foreach ($missing as $name) {
            $this->error("Missing trigger: {$name}");
        }
        foreach ($exceptions as $label => $rows) {
            $this->error("{$label}: ".count($rows));
        }
        if ($missing || $exceptions) {
            Audit::record('ledger.verification_failed', 'Ledger verification found problems', null, ['missing_triggers' => array_values($missing), 'exceptions' => array_map('count', $exceptions)]);

            return self::FAILURE;
        }
        $this->info('Ledger verified: '.count($installed).' triggers installed, no invariant exceptions.');

        return self::SUCCESS;
    }
}
