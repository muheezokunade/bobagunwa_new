<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Restore drill into an isolated, empty database, followed by integrity,
 * trigger, foreign-key and financial reconciliation checks.
 */
class BackupRestore extends Command
{
    protected $signature = 'boba:backup:restore {file} {--key-file= : File containing the custodian key pair} {--target= : Empty isolated database name} {--confirm-isolated}';

    protected $description = 'Restore an encrypted backup into an isolated database and verify it';

    public function handle(BackupService $backups): int
    {
        if (! $this->option('confirm-isolated') || ! $this->option('target') || ! $this->option('key-file')) {
            $this->error('Provide --key-file, --target (an empty, separate database) and --confirm-isolated.');

            return self::FAILURE;
        }
        $key = @file_get_contents((string) $this->option('key-file'));
        try {
            $report = $backups->restoreAndVerify($this->argument('file'), (string) $key, (string) $this->option('target'));
        } catch (Throwable $e) {
            BackupRun::create(['kind' => 'restore_verification', 'status' => 'failed', 'file_name' => basename($this->argument('file')), 'error' => mb_substr($e->getMessage(), 0, 250)]);
            $this->error('Restore refused or failed: '.$e->getMessage());

            return self::FAILURE;
        }
        BackupRun::create(['kind' => 'restore_verification', 'status' => $report['ok'] ? 'succeeded' : 'failed', 'file_name' => basename($this->argument('file')), 'details' => $report]);
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $report['ok'] ? $this->info('Restore verified.') : $this->error('Restore completed with verification problems.');

        return $report['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
