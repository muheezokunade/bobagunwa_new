<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupKeygen extends Command
{
    protected $signature = 'boba:backup:keygen';

    protected $description = 'Generate a backup custodian key pair (run on a trusted computer, not the server)';

    public function handle(): int
    {
        $keys = BackupService::generateKeypair();
        $this->warn('Store the PRIVATE key pair offline with the approved backup custodian. Never put it on the server or in Git.');
        $this->line('BOBA_BACKUP_PUBLIC_KEY='.$keys['public']);
        $this->line('PRIVATE KEY PAIR (custodian only): '.$keys['keypair']);

        return self::SUCCESS;
    }
}
