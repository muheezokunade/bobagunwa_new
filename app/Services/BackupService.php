<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\BackupRun;
use App\Services\Finance\LedgerQuery;
use App\Support\IntegrityTriggers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Encrypted, verifiable database backups.
 *
 * Cryptography (libsodium, no custom primitives):
 *   - A random 32-byte file key encrypts the dump with
 *     crypto_secretstream_xchacha20poly1305 (authenticated, chunked; reordering,
 *     modification and truncation are detected via the FINAL tag).
 *   - The file key is sealed to the custodian's X25519 public key with
 *     crypto_box_seal. The server stores only the public key, so it can create
 *     backups but can never decrypt them.
 *   - The plaintext header (format, time, environment, database) is bound to
 *     every chunk as additional data.
 *
 * File layout: "BOBABK01" | u32 header length | header JSON | u16 sealed-key
 * length | sealed key | 24-byte stream header | repeated (u32 length | chunk).
 * Each decrypted chunk starts with a type byte: "S" = SQL, "M" = manifest
 * (the final chunk).
 */
class BackupService
{
    private const MAGIC = 'BOBABK01';

    private const CHUNK = 65536;

    public const DELIMITER = ';;';

    public function __construct(private LedgerQuery $ledger)
    {
    }

    /** @return array{public: string, keypair: string} base64 keys */
    public static function generateKeypair(): array
    {
        $keypair = sodium_crypto_box_keypair();

        return ['public' => base64_encode(sodium_crypto_box_publickey($keypair)), 'keypair' => base64_encode($keypair)];
    }

    public function create(?int $userId = null, ?string $publicKeyB64 = null, ?string $directory = null): BackupRun
    {
        $publicKey = base64_decode((string) ($publicKeyB64 ?? config('boba.backup.public_key')), true);
        if (! $publicKey || strlen($publicKey) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new DomainRuleException('Backups are not configured: set BOBA_BACKUP_PUBLIC_KEY to the custodian’s public key (see docs/10-backup-and-recovery.md).');
        }
        $directory ??= config('boba.backup.path');
        File::ensureDirectoryExists($directory, 0700);
        $name = 'bobagunwa-'.now()->utc()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.bobabk';
        $path = rtrim($directory, '/').'/'.$name;
        $database = DB::connection()->getDatabaseName();

        $header = json_encode([
            'format' => self::MAGIC, 'created_at' => now()->utc()->toIso8601String(), 'app_env' => app()->environment(),
            'database' => $database, 'app' => 'bobagunwa',
        ], JSON_UNESCAPED_SLASHES);
        $ad = hash('sha256', $header, true);
        $fileKey = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        $sealed = sodium_crypto_box_seal($fileKey, $publicKey);
        [$state, $streamHeader] = sodium_crypto_secretstream_xchacha20poly1305_init_push($fileKey);

        $out = fopen($path, 'xb');
        if (! $out) {
            throw new RuntimeException('Could not create the backup file.');
        }
        @chmod($path, 0600);
        try {
            fwrite($out, self::MAGIC.pack('N', strlen($header)).$header.pack('n', strlen($sealed)).$sealed.$streamHeader);
            $hash = hash_init('sha256');
            $buffer = '';
            $emit = function (string $sql) use (&$buffer, &$state, $out, $ad, $hash) {
                $sql .= self::DELIMITER."\n";
                hash_update($hash, $sql);
                $buffer .= $sql;
                while (strlen($buffer) >= self::CHUNK) {
                    $this->writeChunk($out, $state, 'S'.substr($buffer, 0, self::CHUNK), $ad, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                    $buffer = substr($buffer, self::CHUNK);
                }
            };
            $manifest = $this->dump($emit);
            if ($buffer !== '') {
                $this->writeChunk($out, $state, 'S'.$buffer, $ad, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
            }
            $manifest['plaintext_sha256'] = hash_final($hash);
            $this->writeChunk($out, $state, 'M'.json_encode($manifest), $ad, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        } catch (Throwable $e) {
            fclose($out);
            @unlink($path);
            BackupRun::create(['kind' => 'backup', 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250), 'created_by' => $userId]);
            throw $e;
        } finally {
            sodium_memzero($fileKey);
        }
        fclose($out);

        return BackupRun::create([
            'kind' => 'backup', 'status' => 'succeeded', 'file_name' => $name, 'sha256' => hash_file('sha256', $path),
            'size_bytes' => filesize($path), 'created_by' => $userId,
            'details' => ['tables' => count($manifest['tables']), 'rows' => array_sum($manifest['tables']), 'triggers' => count($manifest['triggers']), 'migration' => $manifest['migration'], 'finance' => $manifest['finance']],
        ]);
    }

    /**
     * Consistent snapshot dump. Strings are hex-encoded so the SQL stream can
     * be split unambiguously and restored byte-for-byte.
     *
     * @param  callable(string): void  $emit
     */
    private function dump(callable $emit): array
    {
        $pdo = DB::connection()->getPdo();
        // Inside an outer transaction (e.g. a test) the snapshot is already fixed.
        $ownSnapshot = DB::transactionLevel() === 0;
        if ($ownSnapshot) {
            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        }
        try {
            $database = DB::connection()->getDatabaseName();
            $tables = array_column($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM), 0);
            sort($tables);
            $schemaOnly = config('boba.backup.schema_only_tables');
            $emit("SET NAMES utf8mb4");
            $emit("SET FOREIGN_KEY_CHECKS = 0");
            $emit("SET UNIQUE_CHECKS = 0");
            $emit("SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
            $counts = [];
            foreach ($tables as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
                $emit($create);
                $counts[$table] = 0;
                if (in_array($table, $schemaOnly, true)) {
                    continue;
                }
                $columns = DB::select('SELECT COLUMN_NAME AS name, DATA_TYPE AS type, IS_GENERATED AS generated FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$database, $table]);
                $columns = array_values(array_filter($columns, fn ($c) => $c->generated === 'NEVER'));
                $names = array_map(fn ($c) => $c->name, $columns);
                $numeric = array_map(fn ($c) => in_array($c->type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal'], true), $columns);
                $pk = array_column($pdo->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC), 'Column_name');
                $order = $pk ? ' ORDER BY `'.implode('`, `', $pk).'`' : '';
                $select = 'SELECT `'.implode('`, `', $names)."` FROM `{$table}`{$order}";
                $prefix = "INSERT INTO `{$table}` (`".implode('`, `', $names).'`) VALUES ';
                $offset = 0;
                do {
                    $rows = $pdo->query("{$select} LIMIT 500 OFFSET {$offset}")->fetchAll(PDO::FETCH_NUM);
                    if ($rows) {
                        $values = [];
                        foreach ($rows as $row) {
                            $cells = [];
                            foreach ($row as $i => $value) {
                                $cells[] = $value === null ? 'NULL' : ($numeric[$i] && is_numeric($value) ? $value : ($value === '' ? "''" : 'CONVERT(0x'.bin2hex((string) $value).' USING utf8mb4)'));
                            }
                            $values[] = '('.implode(', ', $cells).')';
                        }
                        $emit($prefix.implode(",\n", $values));
                        $counts[$table] += count($rows);
                    }
                    $offset += 500;
                } while (count($rows) === 500);
            }
            $triggers = [];
            foreach ($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $trigger) {
                $definition = $pdo->query("SHOW CREATE TRIGGER `{$trigger['Trigger']}`")->fetch(PDO::FETCH_ASSOC)['SQL Original Statement'];
                $emit(preg_replace('/^CREATE\s+DEFINER\s*=\s*\S+\s+TRIGGER/i', 'CREATE TRIGGER', $definition));
                $triggers[] = $trigger['Trigger'];
            }
            sort($triggers);
            $emit('SET FOREIGN_KEY_CHECKS = 1');
            $emit('SET UNIQUE_CHECKS = 1');

            return [
                'tables' => $counts,
                'triggers' => $triggers,
                'migration' => DB::table('migrations')->orderByDesc('id')->value('migration'),
                'finance' => $this->ledger->organisationTotals(),
                'members' => (int) DB::table('members')->count(),
                'audit_events' => (int) DB::table('audit_events')->count(),
            ];
        } finally {
            if ($ownSnapshot) {
                $pdo->exec('COMMIT');
                $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }
        }
    }

    private function writeChunk($out, string &$state, string $plain, string $ad, int $tag): void
    {
        $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $plain, $ad, $tag);
        fwrite($out, pack('N', strlen($cipher)).$cipher);
    }

    /**
     * Authenticate and decrypt a backup into a private temporary file. Nothing
     * is written to any database until the whole file has been verified.
     *
     * @return array{header: array, manifest: array, sql_path: string}
     */
    public function decrypt(string $path, string $keypairB64, string $tempDir): array
    {
        $keypair = base64_decode(trim($keypairB64), true);
        if (! $keypair || strlen($keypair) !== SODIUM_CRYPTO_BOX_KEYPAIRBYTES) {
            throw new DomainRuleException('The restore key is not a valid custodian key pair.');
        }
        $in = @fopen($path, 'rb');
        if (! $in) {
            throw new DomainRuleException('The backup file cannot be read.');
        }
        $sqlPath = $tempDir.'/restore-'.bin2hex(random_bytes(6)).'.sql';
        $out = null;
        try {
            if (fread($in, 8) !== self::MAGIC) {
                throw new DomainRuleException('This is not a Bobagunwa backup file.');
            }
            $len = unpack('N', (string) fread($in, 4))[1] ?? 0;
            if ($len < 2 || $len > 65536) {
                throw new DomainRuleException('The backup header is damaged.');
            }
            $headerJson = (string) fread($in, $len);
            $header = json_decode($headerJson, true);
            if (! is_array($header)) {
                throw new DomainRuleException('The backup header is damaged.');
            }
            $ad = hash('sha256', $headerJson, true);
            $sealedLen = unpack('n', (string) fread($in, 2))[1] ?? 0;
            $fileKey = sodium_crypto_box_seal_open((string) fread($in, $sealedLen), $keypair);
            if ($fileKey === false) {
                throw new DomainRuleException('The restore key does not match this backup (wrong key or damaged file).');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull((string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $fileKey);
            sodium_memzero($fileKey);
            $out = fopen($sqlPath, 'xb');
            @chmod($sqlPath, 0600);
            $hash = hash_init('sha256');
            $manifest = null;
            while (! feof($in)) {
                $raw = fread($in, 4);
                if ($raw === '' || $raw === false) {
                    break;
                }
                if (strlen($raw) !== 4) {
                    throw new DomainRuleException('The backup is truncated.');
                }
                $clen = unpack('N', $raw)[1];
                $cipher = $clen > 0 && $clen <= self::CHUNK + 1024 ? fread($in, $clen) : '';
                if (strlen((string) $cipher) !== $clen) {
                    throw new DomainRuleException('The backup is truncated.');
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher, $ad);
                if ($result === false) {
                    throw new DomainRuleException('The backup failed authentication: it has been modified or damaged.');
                }
                [$plain, $tag] = $result;
                if ($manifest !== null) {
                    throw new DomainRuleException('The backup contains data after its final block.');
                }
                if ($plain[0] === 'S') {
                    $chunk = substr($plain, 1);
                    hash_update($hash, $chunk);
                    fwrite($out, $chunk);
                } elseif ($plain[0] === 'M' && $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $manifest = json_decode(substr($plain, 1), true);
                } else {
                    throw new DomainRuleException('The backup structure is invalid.');
                }
            }
            if (! is_array($manifest)) {
                throw new DomainRuleException('The backup is truncated: its final verification block is missing.');
            }
            if (! hash_equals((string) ($manifest['plaintext_sha256'] ?? ''), hash_final($hash))) {
                throw new DomainRuleException('The backup content does not match its manifest.');
            }
            fclose($out);
            $out = null;

            return ['header' => $header, 'manifest' => $manifest, 'sql_path' => $sqlPath];
        } catch (Throwable $e) {
            if ($out) {
                fclose($out);
            }
            @unlink($sqlPath);
            throw $e;
        } finally {
            fclose($in);
        }
    }

    /**
     * Restore into a separate, empty database and verify it. Refuses to touch
     * the live application database.
     *
     * @return array<string, mixed> verification report
     */
    public function restoreAndVerify(string $path, string $keypairB64, string $targetDatabase): array
    {
        $live = config('database.connections.mariadb.database');
        if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $targetDatabase) || strcasecmp($targetDatabase, $live) === 0) {
            throw new DomainRuleException('Restores must target a separate, isolated database — never the live database.');
        }
        $tempDir = storage_path('app/private/restore-tmp');
        File::ensureDirectoryExists($tempDir, 0700);
        $decrypted = $this->decrypt($path, $keypairB64, $tempDir);
        $manifest = $decrypted['manifest'];

        config(['database.connections.boba_restore' => array_merge(config('database.connections.mariadb'), ['database' => $targetDatabase])]);
        DB::purge('boba_restore');
        $target = DB::connection('boba_restore');
        try {
            $existing = $target->select("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?", [$targetDatabase])[0]->n;
            if ((int) $existing > 0) {
                throw new DomainRuleException("The target database {$targetDatabase} is not empty. Restore only into a new, empty database.");
            }
            $pdo = $target->getPdo();
            $handle = fopen($decrypted['sql_path'], 'rb');
            $statement = '';
            $executed = 0;
            while (($line = fgets($handle)) !== false) {
                $statement .= $line;
                if (str_ends_with(rtrim($line, "\n"), self::DELIMITER)) {
                    $pdo->exec(substr(rtrim($statement, "\n"), 0, -strlen(self::DELIMITER)));
                    $statement = '';
                    $executed++;
                }
            }
            fclose($handle);
            if (trim($statement) !== '') {
                throw new DomainRuleException('The backup ended with an incomplete statement.');
            }
        } finally {
            @unlink($decrypted['sql_path']);
        }

        $report = $this->verifyRestored($target, $targetDatabase, $manifest);
        $report['statements_executed'] = $executed;
        $report['backup_created_at'] = $decrypted['header']['created_at'] ?? null;
        DB::purge('boba_restore');

        return $report;
    }

    /** @return array<string, mixed> */
    private function verifyRestored($target, string $database, array $manifest): array
    {
        $problems = [];
        foreach ($manifest['tables'] as $table => $expected) {
            $actual = (int) $target->table($table)->count();
            if (! in_array($table, config('boba.backup.schema_only_tables'), true) && $actual !== (int) $expected) {
                $problems[] = "{$table}: expected {$expected} rows, found {$actual}";
            }
        }
        $triggers = collect($target->select('SELECT TRIGGER_NAME AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?', [$database]))->pluck('n')->sort()->values()->all();
        if ($triggers !== $manifest['triggers']) {
            $problems[] = 'Trigger set differs from the backup manifest';
        }
        $missing = array_diff(IntegrityTriggers::names(), $triggers);
        if ($missing) {
            $problems[] = 'Missing integrity triggers: '.implode(', ', $missing);
        }
        // Foreign-key orphan check (FK checks were disabled during bulk load).
        $fks = $target->select('SELECT k.TABLE_NAME AS t, k.CONSTRAINT_NAME AS c, GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION) AS cols, k.REFERENCED_TABLE_NAME AS rt, GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION) AS rcols
            FROM information_schema.KEY_COLUMN_USAGE k WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL GROUP BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME', [$database]);
        $orphans = 0;
        foreach ($fks as $fk) {
            $cols = explode(',', $fk->cols);
            $rcols = explode(',', $fk->rcols);
            $join = implode(' AND ', array_map(fn ($c, $r) => "p.`{$r}` = c.`{$c}`", $cols, $rcols));
            $notNull = implode(' AND ', array_map(fn ($c) => "c.`{$c}` IS NOT NULL", $cols));
            $n = (int) $target->selectOne("SELECT COUNT(*) AS n FROM `{$fk->t}` c LEFT JOIN `{$fk->rt}` p ON {$join} WHERE {$notNull} AND p.`{$rcols[0]}` IS NULL")->n;
            if ($n > 0) {
                $problems[] = "{$n} orphaned rows for {$fk->t}.{$fk->c}";
                $orphans += $n;
            }
        }
        // Financial totals recomputed from the restored ledger.
        $ledger = new LedgerQuery;
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection('boba_restore');
        try {
            $finance = $ledger->organisationTotals();
            $exceptions = array_filter($ledger->exceptions());
        } finally {
            DB::setDefaultConnection($previous);
        }
        foreach ($manifest['finance'] as $key => $value) {
            if (($finance[$key] ?? null) !== $value) {
                $problems[] = "Finance total {$key} differs: backup {$value}, restored ".($finance[$key] ?? 'missing');
            }
        }
        foreach ($exceptions as $label => $rows) {
            $problems[] = "Ledger exception after restore: {$label}";
        }

        return [
            'ok' => $problems === [],
            'problems' => $problems,
            'tables' => count($manifest['tables']),
            'rows' => array_sum($manifest['tables']),
            'triggers' => count($triggers),
            'foreign_key_constraints_checked' => count($fks),
            'orphaned_rows' => $orphans,
            'finance' => $finance,
            'members' => (int) $target->table('members')->count(),
            'audit_events' => (int) $target->table('audit_events')->count(),
        ];
    }
}
