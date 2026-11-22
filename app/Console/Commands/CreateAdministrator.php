<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountService;
use App\Support\Audit;
use App\Support\Normalize;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Secure initial administrator provisioning: no default or known password.
 * Prints a single-use activation link to the terminal of the person running
 * the command (over SSH), who completes activation in the browser.
 */
class CreateAdministrator extends Command
{
    protected $signature = 'boba:admin:create {email} {name} {--role=system_admin : Role key to grant (repeatable as comma list)}';

    protected $description = 'Provision an officer account and print a one-time activation link';

    public function handle(AccountService $accounts): int
    {
        $email = Normalize::email($this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('An account with this email already exists (it may be a member portal login). Officer logins are separate: use a different email address.');

            return self::FAILURE;
        }
        $roleKeys = array_filter(array_map('trim', explode(',', (string) $this->option('role'))));
        $roles = Role::whereIn('key', $roleKeys)->get();
        if ($roles->count() !== count($roleKeys)) {
            $this->error('Unknown role. Available: '.Role::pluck('key')->implode(', '));

            return self::FAILURE;
        }
        $user = DB::transaction(function () use ($email, $roles) {
            $user = User::create(['name' => trim($this->argument('name')), 'email' => $email, 'status' => 'pending_activation']);
            foreach ($roles as $role) {
                $user->roles()->attach($role->id, ['reason' => 'Provisioned from the server console']);
            }
            Audit::record('access.officer_provisioned', "Officer account provisioned from console for {$email}", $user, ['roles' => $roles->pluck('key')->all()]);

            return $user;
        });
        $link = $accounts->issue($user, 'activation', 'cli', null);
        $this->info('Account created. Send this one-time activation link privately to the officer (expires '.$link['record']->expires_at->toDayDateTimeString().' UTC):');
        $this->line($link['url']);

        return self::SUCCESS;
    }
}
