<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupCreate extends Command
{
    protected $signature = 'boba:backup:create {--keep=14 : Number of newest backup files to keep}';

    protected $description = 'Create an encrypted database backup outside the public webroot';

    public function handle(BackupService $backups): int
    {
        $run = $backups->create(null);
        $this->info("Backup created: {$run->file_name} ({$run->size_bytes} bytes, sha256 {$run->sha256})");
        $keep = max(3, (int) $this->option('keep'));
        $files = collect(glob(rtrim(config('boba.backup.path'), '/').'/*.bobabk'))->sortDesc()->values();
        foreach ($files->slice($keep) as $old) {
            @unlink($old);
            $this->line('Removed old backup '.basename($old));
        }

        return self::SUCCESS;
    }
}
