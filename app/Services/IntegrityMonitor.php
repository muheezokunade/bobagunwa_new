<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\AlertNotification;
use App\Models\IntegrityCheckRun;
use App\Models\SystemAlert;
use App\Models\User;
use App\Services\Finance\LedgerQuery;
use App\Support\Audit;
use App\Support\IntegrityTriggers;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Ledger-integrity monitoring and alerting with no paid services.
 *
 * - Every check run is stored (append-only history).
 * - A failure raises (or re-raises) exactly one unresolved alert per condition.
 * - Alerts are shown on every administration page to officers holding
 *   `integrity.alerts` until resolved; first display per officer is recorded.
 * - An alert can be acknowledged with a note, but only resolved after a later
 *   check has passed — a live failure cannot be dismissed.
 * - Email is used only if the officer-verified mail channel is enabled; the
 *   message contains no amounts, names, references or check details.
 * - "Overdue" alerts catch a scheduler that has stopped running.
 */
class IntegrityMonitor
{
    public const OVERDUE_HOURS = 36;

    public const REMINDER_HOURS = 24;

    public function __construct(private LedgerQuery $ledger) {}

    public function run(string $trigger, ?User $requestedBy = null): IntegrityCheckRun
    {
        $started = now();
        $missing = [];
        $counts = [];
        $installed = 0;
        $error = null;
        try {
            $names = collect(DB::select('SELECT TRIGGER_NAME AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'))->pluck('n')->all();
            $installed = count($names);
            $missing = array_values(array_diff(IntegrityTriggers::names(), $names));
            $counts = array_map('count', array_filter($this->ledger->exceptions()));
        } catch (Throwable $e) {
            $error = mb_substr(class_basename($e).': '.$e->getMessage(), 0, 250);
        }
        $status = $error ? 'error' : (($missing || $counts) ? 'failed' : 'passed');

        $run = IntegrityCheckRun::create([
            'trigger' => $trigger, 'status' => $status, 'triggers_installed' => $installed,
            'missing_triggers' => $missing ?: null, 'exception_counts' => $counts ?: null, 'error' => $error,
            'requested_by' => $requestedBy?->id, 'started_at' => $started, 'finished_at' => now(),
        ]);

        if ($status === 'failed') {
            $parts = array_merge(array_map(fn ($t) => "missing trigger {$t}", $missing), array_keys($counts));
            sort($parts);
            $summary = collect($counts)->map(fn ($n, $label) => "{$label}: {$n}")->values()
                ->merge($missing ? [count($missing).' integrity trigger(s) missing'] : [])->implode('; ');
            $this->raise($missing ? 'integrity_triggers_missing' : 'ledger_reconciliation', 'critical', hash('sha256', 'ledger|'.implode('|', $parts)),
                $missing ? 'Financial integrity safeguards are missing' : 'Ledger reconciliation check failed',
                mb_substr($summary, 0, 500), ['exception_counts' => $counts, 'missing_triggers' => $missing, 'run_id' => $run->id]);
        } elseif ($status === 'error') {
            $this->raise('integrity_check_error', 'critical', hash('sha256', 'check-error'), 'The integrity check could not run',
                'The scheduled ledger integrity check failed to complete. See the check history for the technical error.', ['run_id' => $run->id]);
        } else {
            SystemAlert::whereIn('status', ['open', 'acknowledged'])->whereNull('condition_cleared_at')
                ->whereIn('type', ['ledger_reconciliation', 'integrity_triggers_missing', 'integrity_check_error', 'integrity_check_overdue'])
                ->update(['condition_cleared_at' => now()]);
        }
        Audit::record('integrity.check_'.$status, "Integrity check {$status} ({$trigger})", $run, ['missing_triggers' => count($missing), 'exception_checks' => count($counts)], $requestedBy?->id);

        return $run;
    }

    /** Raise an "overdue" alert when no check has completed within the expected period. */
    public function checkOverdue(): ?SystemAlert
    {
        // Runs that ended in an error do not count as completed checks.
        $last = IntegrityCheckRun::whereIn('status', ['passed', 'failed'])->max('started_at');
        $installedAt = User::min('created_at');
        $reference = $last ? Carbon::parse($last) : ($installedAt ? Carbon::parse($installedAt) : null);
        if ($reference === null || $reference->gt(now()->subHours(self::OVERDUE_HOURS))) {
            return null;
        }

        return $this->raise('integrity_check_overdue', 'warning', hash('sha256', 'check-overdue'), 'The integrity check has not run on schedule',
            'No ledger integrity check has completed in the last '.self::OVERDUE_HOURS.' hours. The server cron job may have stopped.', ['last_completed_run' => $last]);
    }

    public function raise(string $type, string $severity, string $fingerprint, string $title, string $summary, array $details): SystemAlert
    {
        $alert = null;
        $isNew = false;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                [$alert, $isNew] = DB::transaction(function () use ($type, $severity, $fingerprint, $title, $summary, $details) {
                    $existing = SystemAlert::where('open_fingerprint', $fingerprint)->lockForUpdate()->first();
                    if ($existing) {
                        $existing->update(['occurrences' => $existing->occurrences + 1, 'last_detected_at' => now(), 'condition_cleared_at' => null, 'summary' => $summary, 'details' => $details]);

                        return [$existing, false];
                    }

                    return [SystemAlert::create([
                        'type' => $type, 'severity' => $severity, 'fingerprint' => $fingerprint, 'status' => 'open',
                        'title' => $title, 'summary' => $summary, 'details' => $details, 'occurrences' => 1,
                        'first_detected_at' => now(), 'last_detected_at' => now(),
                    ]), true];
                });
                break;
            } catch (UniqueConstraintViolationException) {
                continue; // a concurrent run created it; retry as an update
            }
        }
        if ($isNew) {
            Audit::record('alert.raised', "Alert raised: {$title}", $alert, ['severity' => $severity, 'type' => $type]);
            $this->notify($alert);
        } elseif (! AlertNotification::where('system_alert_id', $alert->id)->where('attempted_at', '>', now()->subHours(self::REMINDER_HOURS))->exists()) {
            $this->notify($alert); // daily reminder while unresolved
        }

        return $alert;
    }

    /** @return Collection<int, User> */
    public function recipients(): Collection
    {
        return User::with('member')->where('status', 'active')
            ->whereHas('roles.permissions', fn ($q) => $q->where('key', 'integrity.alerts'))
            ->get()->filter(fn (User $u) => $u->isActive())->values();
    }

    public function emailChannelReady(): bool
    {
        $verified = Settings::get('alerts.email_verified');

        return Settings::bool('alerts.email_enabled') && is_array($verified) && ! empty($verified['verified_at'])
            && ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /** Records an honest delivery status for every recipient. */
    public function notify(SystemAlert $alert): void
    {
        $ready = $this->emailChannelReady();
        foreach ($this->recipients() as $user) {
            if (! $ready) {
                AlertNotification::create(['system_alert_id' => $alert->id, 'user_id' => $user->id, 'channel' => 'email', 'status' => 'not_configured', 'attempted_at' => now()]);

                continue;
            }
            try {
                Mail::raw(self::emailBody($alert), fn ($m) => $m->to($user->email, $user->name)->subject('Bobagunwa: integrity alert #'.$alert->id.' needs attention'));
                AlertNotification::create(['system_alert_id' => $alert->id, 'user_id' => $user->id, 'channel' => 'email', 'status' => 'accepted_by_mail_server', 'attempted_at' => now()]);
            } catch (Throwable $e) {
                AlertNotification::create(['system_alert_id' => $alert->id, 'user_id' => $user->id, 'channel' => 'email', 'status' => 'failed', 'error' => mb_substr(class_basename($e).': '.$e->getMessage(), 0, 250), 'attempted_at' => now()]);
            }
        }
    }

    /** Deliberately generic: no amounts, names, references or check details. */
    public static function emailBody(SystemAlert $alert): string
    {
        return "An automated integrity check on the Bobagunwa platform needs attention (alert #{$alert->id}, severity {$alert->severity}).\n\n"
            ."For security, no details are included in this email. Sign in to the administration area and open Integrity alerts to review it.\n\n"
            ."Do not try to correct financial records until the cause is understood. Follow the escalation procedure in the administrator guide.\n";
    }

    /**
     * Alerts to show this officer now; records first exposure per officer.
     *
     * @return Collection<int, SystemAlert>
     */
    public function bannerFor(User $user): Collection
    {
        if (! $user->hasPermission('integrity.alerts')) {
            return collect();
        }
        Cache::remember('integrity:overdue-check', 600, function () {
            $this->checkOverdue();

            return true;
        });
        $alerts = SystemAlert::whereIn('status', ['open', 'acknowledged'])->orderByRaw("FIELD(severity, 'critical', 'warning')")->orderBy('id')->get();
        foreach ($alerts as $alert) {
            DB::table('alert_receipts')->insertOrIgnore(['system_alert_id' => $alert->id, 'user_id' => $user->id, 'first_seen_at' => now()]);
        }

        return $alerts;
    }

    public function acknowledge(SystemAlert $alert, User $user, string $note): void
    {
        $this->authorise($user);
        if (mb_strlen(trim($note)) < 10) {
            throw new DomainRuleException('Describe what you are doing about this alert (at least 10 characters).');
        }
        DB::transaction(function () use ($alert, $user, $note) {
            $locked = SystemAlert::lockForUpdate()->findOrFail($alert->id);
            if ($locked->status !== 'open') {
                throw new DomainRuleException('Only an open alert can be acknowledged.');
            }
            $locked->update(['status' => 'acknowledged', 'acknowledged_by' => $user->id, 'acknowledged_at' => now(), 'acknowledgement_note' => trim($note)]);
            Audit::record('alert.acknowledged', "Alert #{$locked->id} acknowledged", $locked, ['note' => $note]);
        });
    }

    public function resolve(SystemAlert $alert, User $user, string $note): void
    {
        $this->authorise($user);
        if (mb_strlen(trim($note)) < 10) {
            throw new DomainRuleException('Record how the cause was investigated and corrected (at least 10 characters).');
        }
        DB::transaction(function () use ($alert, $user, $note) {
            $locked = SystemAlert::lockForUpdate()->findOrFail($alert->id);
            if ($locked->status === 'resolved') {
                throw new DomainRuleException('This alert is already resolved.');
            }
            if ($locked->condition_cleared_at === null) {
                throw new DomainRuleException('This alert cannot be resolved while the condition is still present. Run the integrity check again after correcting the cause; it must pass first.');
            }
            $locked->update(['status' => 'resolved', 'resolved_by' => $user->id, 'resolved_at' => now(), 'resolution_note' => trim($note)]);
            Audit::record('alert.resolved', "Alert #{$locked->id} resolved", $locked, ['note' => $note]);
        });
    }

    /** Sends a one-time code to the officer to prove the mail channel works end to end. */
    public function sendChannelTest(User $user): void
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            throw new DomainRuleException('No outgoing mail server is configured (MAIL_MAILER is “'.config('mail.default').'”). Alerts are shown in the administration area only.');
        }
        $code = (string) random_int(100000, 999999);
        Cache::put('alerts:email-test:'.$user->id, hash('sha256', $code), now()->addMinutes(30));
        try {
            Mail::raw("Your Bobagunwa alert-email verification code is {$code}. It expires in 30 minutes.", fn ($m) => $m->to($user->email, $user->name)->subject('Bobagunwa: verify alert emails'));
        } catch (Throwable $e) {
            Audit::record('alerts.email_test_failed', 'Alert email channel test failed', null, ['error' => mb_substr($e->getMessage(), 0, 200)]);
            throw new DomainRuleException('The mail server refused or could not be reached: '.mb_substr($e->getMessage(), 0, 160));
        }
        Audit::record('alerts.email_test_sent', 'Alert email verification code sent');
    }

    public function confirmChannelTest(User $user, string $code): void
    {
        $expected = Cache::pull('alerts:email-test:'.$user->id);
        if (! $expected || ! hash_equals($expected, hash('sha256', trim($code)))) {
            throw new DomainRuleException('That code is not correct or has expired. Send a new test email.');
        }
        Settings::set('alerts.email_verified', ['verified_at' => now()->toIso8601String(), 'verified_by' => $user->id, 'mailer' => config('mail.default'), 'host' => config('mail.mailers.smtp.host')], $user->id);
        Audit::record('alerts.email_verified', 'Alert email channel verified by an officer receiving a code');
    }

    private function authorise(User $user): void
    {
        if (! $user->hasPermission('integrity.alerts')) {
            throw new DomainRuleException('You do not have permission to manage integrity alerts.');
        }
    }
}
