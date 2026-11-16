<?php

namespace Tests\Feature\Backup;

use App\Exceptions\DomainRuleException;
use App\Services\BackupService;
use App\Services\Finance\LedgerQuery;
use App\Services\Finance\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Encrypted backup and isolated restore. Uses committed data (no wrapping
 * transaction) and a separate restore database.
 */
class BackupRestoreTest extends TestCase
{
    private const TARGET = 'bobagunwa_restore_test';

    private string $dir;

    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->dir = storage_path('app/private/backup-tests-'.uniqid());
        File::ensureDirectoryExists($this->dir, 0700);
        $this->keys = BackupService::generateKeypair();
        $this->resetTarget();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        DB::statement('DROP DATABASE IF EXISTS '.self::TARGET);
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    private function resetTarget(): void
    {
        DB::statement('DROP DATABASE IF EXISTS '.self::TARGET);
        DB::statement('CREATE DATABASE '.self::TARGET.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function seedLedger(): void
    {
        $m = $this->member(['full_name' => 'Ọlá Àdìsá Synthetic']);
        $a = $this->assess([$m, $this->member()], '50000');
        $p = $this->verifiedPayment($m, '20000');
        app(PaymentService::class)->allocate($p, [$this->obligationOf($m, $a)->id => 2_000_000], $this->officer('treasurer'), $this->key());
    }

    private function backup(): string
    {
        $run = app(BackupService::class)->create(null, $this->keys['public'], $this->dir);

        return $this->dir.'/'.$run->file_name;
    }

    public function test_encrypted_backup_restores_into_isolated_database_with_integrity(): void
    {
        $this->seedLedger();
        $path = $this->backup();
        $raw = file_get_contents($path);
        $this->assertStringStartsWith('BOBABK01', $raw);
        $this->assertStringNotContainsString('Synthetic', $raw, 'Plaintext must not appear in the backup file');
        $this->assertStringNotContainsString('INSERT INTO', $raw);
        $this->assertSame([], glob($this->dir.'/*.sql'), 'No plaintext dump is left behind');

        $report = app(BackupService::class)->restoreAndVerify($path, $this->keys['keypair'], self::TARGET);
        $this->assertTrue($report['ok'], json_encode($report['problems']));
        $this->assertSame(0, $report['orphaned_rows']);
        $this->assertGreaterThanOrEqual(30, $report['triggers']);
        $this->assertSame(app(LedgerQuery::class)->organisationTotals(), $report['finance']);
        $this->assertSame(3_000_000 + 5_000_000, $report['finance']['outstanding']);
        $this->assertSame('Ọlá Àdìsá Synthetic', DB::connection('mariadb')->table(self::TARGET.'.members')->where('full_name', 'like', 'Ọlá%')->value('full_name'));
        $this->assertSame([], glob(storage_path('app/private/restore-tmp').'/*.sql'), 'Temporary plaintext is removed');

        // Triggers are live in the restored copy.
        $this->expectException(QueryException::class);
        DB::statement('DELETE FROM '.self::TARGET.'.payments');
    }

    public function test_wrong_key_is_rejected(): void
    {
        $this->seedLedger();
        $path = $this->backup();
        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('does not match');
        app(BackupService::class)->restoreAndVerify($path, BackupService::generateKeypair()['keypair'], self::TARGET);
    }

    public function test_tampered_truncated_and_header_modified_backups_are_rejected_before_any_restore(): void
    {
        $this->seedLedger();
        $path = $this->backup();
        $original = file_get_contents($path);
        $cases = [
            'ciphertext byte flipped' => substr_replace($original, chr(ord($original[strlen($original) - 40]) ^ 1), -40, 1),
            'truncated' => substr($original, 0, (int) (strlen($original) * 0.6)),
            'final block removed' => substr($original, 0, strrpos($original, pack('N', 0)) ?: strlen($original) - 300),
            'header modified' => str_replace('"app_env":"testing"', '"app_env":"producti"', $original),
            'not a backup' => 'hello world',
        ];
        foreach ($cases as $label => $content) {
            file_put_contents($path, $content);
            try {
                app(BackupService::class)->restoreAndVerify($path, $this->keys['keypair'], self::TARGET);
                $this->fail("Tampered backup accepted: {$label}");
            } catch (DomainRuleException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
            $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [self::TARGET])->n, "Nothing may be restored from: {$label}");
        }
    }

    public function test_restore_refuses_live_database_and_non_empty_targets(): void
    {
        $path = $this->backup();
        try {
            app(BackupService::class)->restoreAndVerify($path, $this->keys['keypair'], config('database.connections.mariadb.database'));
            $this->fail('Restoring over the live database must be refused');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('isolated', $e->getMessage());
        }
        app(BackupService::class)->restoreAndVerify($path, $this->keys['keypair'], self::TARGET);
        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('not empty');
        app(BackupService::class)->restoreAndVerify($path, $this->keys['keypair'], self::TARGET);
    }

    public function test_backup_requires_a_configured_custodian_public_key(): void
    {
        $this->expectException(DomainRuleException::class);
        app(BackupService::class)->create(null, '');
    }

    public function test_restore_command_reports_verification(): void
    {
        $this->seedLedger();
        $path = $this->backup();
        $keyFile = $this->dir.'/custodian.key';
        file_put_contents($keyFile, $this->keys['keypair']);
        $exit = Artisan::call('boba:backup:restore', ['file' => $path, '--key-file' => $keyFile, '--target' => self::TARGET, '--confirm-isolated' => true]);
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('Restore verified', $output);
        $this->assertDatabaseHas('backup_runs', ['kind' => 'restore_verification', 'status' => 'succeeded']);
    }
}
