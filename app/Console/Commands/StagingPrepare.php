<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountService;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prepares SYNTHETIC officer accounts for staging acceptance, one per staff
 * role, at undeliverable `@staging-synthetic.invalid` addresses. No password
 * is ever set or known: each account gets a single-use activation (or reset)
 * link, written to a private file (mode 600) for tests/browser/staging-acceptance.cjs,
 * which sets random passwords in memory only. Refuses production; `boba:preflight`
 * fails in production if any such account exists.
 */
class StagingPrepare extends Command
{
    public const DOMAIN = 'staging-synthetic.invalid';

    public const ROLES = ['system_admin', 'membership_initiator', 'membership_officer', 'secretariat', 'treasurer', 'payment_verifier', 'executive'];

    protected $signature = 'boba:staging:prepare {--links-file= : Private JSON file to write the one-time links to (mode 600)} {--confirm-synthetic : Confirm this environment holds synthetic data only}';

    protected $description = 'Create or refresh synthetic staging officer accounts and issue single-use links (never in production)';

    public function handle(AccountService $accounts): int
    {
        if (! app()->environment(['staging', 'local', 'testing'])) {
            $this->error('Refused: synthetic staging accounts may only be prepared in the staging or local environment.');

            return self::FAILURE;
        }
        if (! $this->option('confirm-synthetic')) {
            $this->error('Refused: pass --confirm-synthetic to confirm this database holds synthetic data only.');

            return self::FAILURE;
        }
        $file = (string) $this->option('links-file');
        if ($file === '') {
            $this->error('Pass --links-file=<private path>; links are not printed so they do not end up in terminal logs.');

            return self::FAILURE;
        }

        $links = [];
        foreach (self::ROLES as $key) {
            $role = Role::where('key', $key)->firstOrFail();
            $email = str_replace('_', '-', $key).'@'.self::DOMAIN;
            $user = DB::transaction(function () use ($email, $role) {
                $user = User::where('email', $email)->first();
                if (! $user) {
                    $user = User::create(['name' => 'Synthetic staging '.$role->name, 'email' => $email, 'status' => 'pending_activation']);
                    $user->roles()->attach($role->id, ['reason' => 'Synthetic staging acceptance account']);
                    Audit::record('access.staging_synthetic_officer', "Synthetic staging officer created ({$role->key})", $user, ['role' => $role->key]);
                }

                return $user;
            });
            if (! in_array($user->status, ['pending_activation', 'active'], true)) {
                $this->warn("{$email} is {$user->status}; skipped.");

                continue;
            }
            $purpose = $user->status === 'pending_activation' ? 'activation' : 'password_reset';
            $link = $accounts->issue($user, $purpose, 'cli', null);
            $links[$key] = ['email' => $email, 'purpose' => $purpose, 'url' => $link['url'], 'expires_at' => $link['record']->expires_at->toIso8601String()];
        }

        $old = umask(0077);
        file_put_contents($file, json_encode(['environment' => app()->environment(), 'app_url' => config('app.url'), 'created_at' => now()->toIso8601String(), 'officers' => $links], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        umask($old);
        chmod($file, 0600);
        $this->info(count($links)." synthetic officer link(s) written to {$file} (mode 600). Copy it to the computer that runs the acceptance script, then delete it here.");

        return self::SUCCESS;
    }
}
