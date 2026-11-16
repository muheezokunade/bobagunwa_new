<?php

namespace App\Console\Commands;

use App\Support\IntegrityTriggers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Hosting compatibility check. Run on the Namecheap server over SSH before and
 * after deployment: `php artisan boba:preflight`. Read-only except for a
 * temporary table/trigger that is created and dropped immediately.
 */
class Preflight extends Command
{
    protected $signature = 'boba:preflight {--json : Machine-readable output}';

    protected $description = 'Verify that this server can run the Bobagunwa platform safely';

    private array $results = [];

    public function handle(): int
    {
        $this->check('PHP version ≥ 8.3', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION.' ('.PHP_SAPI.'; web requests must also use 8.3 — confirm in cPanel MultiPHP Manager)');
        foreach (['pdo_mysql', 'sodium', 'gd', 'intl', 'mbstring', 'fileinfo', 'openssl', 'ctype', 'tokenizer', 'xml', 'curl'] as $ext) {
            $this->check("Extension {$ext}", extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing');
        }
        $this->check('libsodium secretstream + sealed boxes', function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push') && function_exists('sodium_crypto_box_seal'), 'required for encrypted backups');
        $this->check('GD JPEG/PNG support', function_exists('imagejpeg') && function_exists('imagepng'), 'required to re-encode uploaded images');
        $this->check('Memory limit ≥ 128M', $this->bytes(ini_get('memory_limit')) >= 128 * 1048576 || ini_get('memory_limit') === '-1', ini_get('memory_limit'));
        $this->check('Upload size ≥ 10M', $this->bytes(ini_get('upload_max_filesize')) >= 10 * 1048576, 'upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size'), false);
        $this->check('APP_KEY set', (string) config('app.key') !== '', config('app.key') ? 'set' : 'missing — run php artisan key:generate');
        $this->check('Debug disabled outside local', ! config('app.debug') || app()->environment('local'), 'APP_DEBUG='.(config('app.debug') ? 'true' : 'false').', APP_ENV='.app()->environment());
        $this->check('Secure session cookies', (bool) config('session.secure') || app()->environment('local'), 'SESSION_SECURE_COOKIE='.(config('session.secure') ? 'true' : 'false'), false);

        try {
            $version = DB::selectOne('SELECT VERSION() AS v')->v;
            $this->check('Database connection', true, $version);
            $this->check('MariaDB ≥ 10.6 (11.4 expected)', str_contains($version, 'MariaDB') && version_compare(explode('-', $version)[0], '10.6', '>='), $version);
            $this->check('InnoDB default engine', strtolower((string) DB::selectOne('SELECT @@default_storage_engine AS e')->e) === 'innodb', DB::selectOne('SELECT @@default_storage_engine AS e')->e);
            $binlog = DB::selectOne("SELECT @@log_bin AS b")->b;
            $this->check('Binary logging (trigger privilege implications)', true, $binlog ? 'ON — trigger creation may need log_bin_trust_function_creators; see docs' : 'OFF');
            $probe = 'boba_preflight_'.bin2hex(random_bytes(3));
            DB::statement("CREATE TABLE {$probe} (id INT PRIMARY KEY) ENGINE=InnoDB");
            try {
                DB::unprepared("CREATE TRIGGER {$probe}_t BEFORE DELETE ON {$probe} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'preflight'");
                $this->check('CREATE TRIGGER privilege', true, 'trigger created and dropped');
            } catch (Throwable $e) {
                $this->check('CREATE TRIGGER privilege', false, mb_substr($e->getMessage(), 0, 160));
            } finally {
                DB::statement("DROP TABLE IF EXISTS {$probe}");
            }
            $installed = collect(DB::select('SELECT TRIGGER_NAME AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'))->pluck('n')->all();
            if ($installed) {
                $missing = array_diff(IntegrityTriggers::names(), $installed);
                $this->check('Ledger integrity triggers installed', ! $missing, $missing ? 'missing: '.implode(', ', $missing) : count($installed).' installed');
            }
        } catch (Throwable $e) {
            $this->check('Database connection', false, mb_substr($e->getMessage(), 0, 200));
        }

        foreach ([storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), storage_path('app/private'), base_path('bootstrap/cache'), public_path('media'), config('boba.backup.path')] as $dir) {
            @mkdir($dir, 0750, true);
            $this->check('Writable: '.$dir, is_dir($dir) && is_writable($dir), is_writable($dir) ? 'ok' : 'not writable');
        }
        $this->check('Private storage outside webroot', ! str_starts_with(realpath(storage_path()) ?: storage_path(), realpath(public_path()) ?: public_path()), storage_path());
        $this->check('Backup path outside webroot', ! str_starts_with((string) config('boba.backup.path'), public_path()), (string) config('boba.backup.path'));
        $this->check('Backup public key configured', (string) config('boba.backup.public_key') !== '', config('boba.backup.public_key') ? 'set' : 'not set — backups disabled', false);
        $link = storage_path('framework/preflight-link-'.bin2hex(random_bytes(2)));
        $symlinks = @symlink(storage_path('logs'), $link);
        @unlink($link);
        $this->check('Symbolic links (informational)', true, $symlinks ? 'supported' : 'not supported — use the directory-switch release method', false);
        $this->check('Built assets present', is_file(public_path('build/manifest.json')), 'public/build/manifest.json');
        $this->check('exec() available (informational)', true, function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true) ? 'available (not required)' : 'disabled (not required)', false);

        if ($this->option('json')) {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Check', 'Result', 'Detail'], array_map(fn ($r) => [$r['check'], $r['ok'] ? 'PASS' : ($r['required'] ? 'FAIL' : 'WARN'), $r['detail']], $this->results));
        }
        $failed = array_filter($this->results, fn ($r) => ! $r['ok'] && $r['required']);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $name, bool $ok, string $detail, bool $required = true): void
    {
        $this->results[] = ['check' => $name, 'ok' => $ok, 'required' => $required, 'detail' => $detail];
    }

    private function bytes(string|false $value): int
    {
        $value = trim((string) $value);
        $unit = strtolower(substr($value, -1));
        $n = (int) $value;

        return match ($unit) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }
}
