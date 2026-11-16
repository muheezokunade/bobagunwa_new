<?php

namespace Tests\Feature\Monitoring;

use App\Models\AlertNotification;
use App\Models\Assessment;
use App\Models\IntegrityCheckRun;
use App\Models\SystemAlert;
use App\Services\IntegrityMonitor;
use App\Support\IntegrityTriggers;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Uses committed data (dropping a trigger is DDL and cannot run inside the
 * per-test transaction), so the schema is rebuilt around each test.
 */
class IntegrityAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    private function breakReconciliation(): void
    {
        // A posted assessment whose total disagrees with its obligations: a real
        // condition the reconciliation check detects (triggers do not prevent it).
        $t = $this->officer('treasurer');
        $a = Assessment::create(['reference' => 'ASM-BROKEN', 'fee_category_id' => $this->category()->id, 'title' => 'Broken', 'amount_kobo' => 100, 'effective_on' => now()->toDateString(), 'status' => 'draft', 'target_mode' => 'all_active', 'created_by' => $t->id]);
        $a->update(['status' => 'posted', 'posted_by' => $t->id, 'posted_at' => now(), 'total_kobo' => 999]);
    }

    public function test_passing_check_records_history_and_raises_nothing(): void
    {
        $this->assertSame(0, Artisan::call('boba:ledger:verify'));
        $run = IntegrityCheckRun::sole();
        $this->assertSame(['passed', 'schedule'], [$run->status, $run->trigger]);
        $this->assertSame(count(IntegrityTriggers::names()), $run->triggers_installed);
        $this->assertSame(0, SystemAlert::count());
    }

    public function test_missing_trigger_raises_one_critical_alert_and_cannot_be_resolved_until_fixed(): void
    {
        $treasurer = $this->officer('treasurer');
        $membership = $this->officer('membership_officer');
        $sysadmin = $this->officer('system_admin');
        DB::unprepared('DROP TRIGGER boba_payment_allocations_bi');

        $this->assertSame(1, Artisan::call('boba:ledger:verify'));
        Artisan::call('boba:ledger:verify');
        $alert = SystemAlert::sole();
        $this->assertSame(['integrity_triggers_missing', 'critical', 'open', 2], [$alert->type, $alert->severity, $alert->status, $alert->occurrences]);
        $this->assertStringNotContainsString('₦', $alert->summary);

        // Shown to authorised officers on every admin page; recorded on first view.
        $this->actingAs($treasurer)->get(route('admin.dashboard'))->assertOk()->assertSee('unresolved integrity alert')->assertSee('#'.$alert->id);
        $this->assertDatabaseHas('alert_receipts', ['system_alert_id' => $alert->id, 'user_id' => $treasurer->id]);
        // Not shown to officers without the permission.
        $this->actingAs($membership)->get(route('admin.dashboard'))->assertOk()->assertDontSee('unresolved integrity alert');
        $this->actingAs($membership)->get(route('admin.alerts.index'))->assertForbidden();
        // A system administrator sees the alert but still has no treasury access.
        $this->actingAs($sysadmin)->get(route('admin.alerts.show', $alert))->assertOk();
        $this->actingAs($sysadmin)->get(route('admin.finance.index'))->assertForbidden();

        // Email channel not configured: recorded honestly per recipient.
        $this->assertSame(2, AlertNotification::where('system_alert_id', $alert->id)->where('status', 'not_configured')->count(), 'Treasurer and system administrator hold integrity.alerts; the membership officer does not');

        // Cannot resolve while the condition is present; acknowledgement needs a note.
        $this->actingAs($treasurer)->post(route('admin.alerts.resolve', $alert), ['note' => 'Trying to dismiss it'])->assertSessionHasErrors('domain');
        $this->actingAs($treasurer)->post(route('admin.alerts.acknowledge', $alert), ['note' => 'short'])->assertSessionHasErrors('domain');
        $this->actingAs($treasurer)->post(route('admin.alerts.acknowledge', $alert), ['note' => 'Maintainer investigating missing trigger'])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->get(route('admin.dashboard'))->assertSee('acknowledged, still unresolved');

        // Fix, re-run, then resolve.
        DB::unprepared(IntegrityTriggers::definitions()['boba_payment_allocations_bi']);
        $this->actingAs($treasurer)->post(route('admin.alerts.check'))->assertSessionHas('status', 'Integrity check passed.');
        $this->assertNotNull($alert->fresh()->condition_cleared_at);
        $this->actingAs($treasurer)->post(route('admin.alerts.resolve', $alert), ['note' => 'Trigger reinstalled by maintainer after migration error'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $alert->fresh()->status);
        $this->actingAs($treasurer)->get(route('admin.dashboard'))->assertDontSee('unresolved integrity alert');
        $this->assertDatabaseHas('audit_events', ['action' => 'alert.resolved', 'subject_id' => $alert->id]);

        // Alerts and check history are append-only.
        $this->expectException(QueryException::class);
        DB::table('system_alerts')->delete();
    }

    public function test_reconciliation_failure_and_recurrence_after_resolution_raises_a_new_alert(): void
    {
        $this->officer('treasurer');
        $this->breakReconciliation();
        Artisan::call('boba:ledger:verify');
        $alert = SystemAlert::sole();
        $this->assertSame('ledger_reconciliation', $alert->type);
        $this->assertStringContainsString('Posted assessment totals that differ from their obligations: 1', $alert->summary);
        $this->assertSame('failed', IntegrityCheckRun::latest('id')->first()->status);
    }

    public function test_overdue_and_errored_checks_raise_alerts(): void
    {
        $treasurer = $this->officer('treasurer');
        DB::table('users')->update(['created_at' => now()->subDays(3)]);
        $this->assertNotNull(app(IntegrityMonitor::class)->checkOverdue());
        $this->assertSame('integrity_check_overdue', SystemAlert::sole()->type);

        // A passing run clears it (resolution still requires a note).
        Artisan::call('boba:ledger:verify');
        $this->assertNotNull(SystemAlert::sole()->condition_cleared_at);
        $this->assertNull(app(IntegrityMonitor::class)->checkOverdue());

        // An errored check is recorded and raises a critical alert.
        DB::statement('RENAME TABLE payment_allocations TO payment_allocations_tmp');
        try {
            $this->assertSame(1, Artisan::call('boba:ledger:verify'));
        } finally {
            DB::statement('RENAME TABLE payment_allocations_tmp TO payment_allocations');
        }
        $this->assertSame('error', IntegrityCheckRun::latest('id')->first()->status);
        $this->assertSame(1, SystemAlert::where('type', 'integrity_check_error')->count());
    }

    public function test_verified_email_channel_records_acceptance_without_sensitive_content(): void
    {
        $treasurer = $this->officer('treasurer');
        $admin = $this->officer(['system_admin']);
        config(['mail.default' => 'smtp']);
        Mail::fake();
        // Verification: a code is sent and must be entered.
        $this->actingAs($admin)->post(route('admin.alerts.email.test'))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.alerts.email.verify'), ['code' => '000000'])->assertSessionHasErrors('domain');
        // The code itself is only ever stored hashed; plant a known one to prove confirmation works.
        Cache::put('alerts:email-test:'.$admin->id, hash('sha256', '123456'), now()->addMinutes(5));
        $this->actingAs($admin)->post(route('admin.alerts.email.verify'), ['code' => '123456'])->assertSessionHasNoErrors();
        $this->assertNotEmpty(Settings::get('alerts.email_verified')['verified_at']);
        $this->assertFalse(app(IntegrityMonitor::class)->emailChannelReady(), 'Verification alone does not enable sending');
        Settings::set('alerts.email_enabled', true, $admin->id);
        $this->assertTrue(app(IntegrityMonitor::class)->emailChannelReady());

        $this->breakReconciliation();
        Artisan::call('boba:ledger:verify');
        $alert = SystemAlert::sole();
        $statuses = AlertNotification::where('system_alert_id', $alert->id)->pluck('status')->unique()->values()->all();
        $this->assertSame(['accepted_by_mail_server'], $statuses);
        $body = IntegrityMonitor::emailBody($alert);
        $this->assertStringNotContainsString('₦', $body);
        $this->assertStringNotContainsString('ASM-', $body);
        $this->assertStringNotContainsString($alert->summary, $body);
    }

    public function test_failed_email_delivery_is_recorded_as_failed(): void
    {
        $this->officer('treasurer');
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        Settings::set('alerts.email_verified', ['verified_at' => now()->toIso8601String()], null);
        Settings::set('alerts.email_enabled', true, null);
        $this->breakReconciliation();
        Artisan::call('boba:ledger:verify');
        $notifications = AlertNotification::all();
        $this->assertGreaterThanOrEqual(1, $notifications->count());
        $this->assertSame(['failed'], $notifications->pluck('status')->unique()->values()->all());
        $this->assertTrue($notifications->every(fn ($n) => $n->error !== null));
        // The alert itself still exists and is shown in the administration area.
        $this->assertSame('open', SystemAlert::sole()->status);
    }

    public function test_channel_test_refuses_when_no_mail_server_is_configured(): void
    {
        $admin = $this->officer('system_admin');
        config(['mail.default' => 'log']);
        $this->actingAs($admin)->post(route('admin.alerts.email.test'))->assertSessionHasErrors('domain');
        $this->actingAs($this->officer('treasurer'))->post(route('admin.alerts.email.test'))->assertForbidden();
    }
}
