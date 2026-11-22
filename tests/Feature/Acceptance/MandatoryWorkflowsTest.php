<?php

namespace Tests\Feature\Acceptance;

use App\Exceptions\DomainRuleException;
use App\Models\Assessment;
use App\Models\AuditEvent;
use App\Models\FeeCategory;
use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\MemberObligation;
use App\Models\MembershipApplication;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\AccountService;
use App\Services\BackupService;
use App\Services\Finance\LedgerQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The seven mandatory operational acceptance workflows, executed together,
 * in order, against one committed MariaDB database through the application's
 * real routes, middleware, permissions and services. Synthetic data only.
 * Writes machine-readable evidence to storage/logs/acceptance-evidence.json.
 */
class MandatoryWorkflowsTest extends TestCase
{
    private array $evidence = [];

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::statement('DROP DATABASE IF EXISTS bobagunwa_acceptance_restore');
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    private function record(int $n, string $name, array $facts): void
    {
        $this->evidence[] = ['workflow' => $n, 'name' => $name, 'result' => 'pass'] + $facts;
    }

    public function test_all_seven_mandatory_workflows_pass_together(): void
    {
        $secretariat = $this->officer('secretariat');
        $membershipOfficer = $this->officer('membership_officer');
        $treasurer = $this->officer('treasurer');
        $verifier = $this->officer('payment_verifier');
        $systemAdmin = $this->officer('system_admin');

        // ---- Workflow 1: existing-member import --------------------------
        $csv = "full_name,email,phone,date_of_birth,joined_on,ijebu_town\nSynthetic Existing One,existing1@example.test,08061110001,1980-04-02,2012-05-01,Ijebu Ode\nSynthetic Existing Two,existing2@example.test,08061110002,12/11/1982,2013-06-01,Ijebu Igbo\n";
        $this->post(route('admin.imports.members.preview'), ['file' => UploadedFile::fake()->createWithContent('existing.csv', $csv)])->assertRedirect(route('login'));
        $this->actingAs($treasurer)->post(route('admin.imports.members.preview'), ['file' => UploadedFile::fake()->createWithContent('existing.csv', $csv)])->assertForbidden();
        $this->actingAs($secretariat)->post(route('admin.imports.members.preview'), ['file' => UploadedFile::fake()->createWithContent('existing.csv', $csv)])->assertRedirect();
        $batch = ImportBatch::where('kind', 'members')->latest('id')->first();
        $this->assertSame([2, 2, 0, 0], [$batch->row_count, $batch->valid_count, $batch->invalid_count, $batch->duplicate_count]);
        $this->actingAs($secretariat)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($secretariat)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1'])->assertSessionHas('status', fn ($s) => str_contains($s, 'already completed'));
        $this->actingAs($secretariat)->post(route('admin.imports.members.preview'), ['file' => UploadedFile::fake()->createWithContent('existing-again.csv', $csv."\n")])->assertRedirect();
        $recheck = ImportBatch::where('kind', 'members')->latest('id')->first();
        $this->assertSame(2, $recheck->duplicate_count, 'Re-uploaded rows are detected as duplicates of the register');
        $imported = Member::where('import_batch_id', $batch->id)->get();
        $this->assertCount(2, $imported);
        $this->assertSame(0, User::whereIn('member_id', $imported->pluck('id'))->count());
        $this->post(route('logout'));
        $this->post(route('login.attempt'), ['email' => 'existing1@example.test', 'password' => 'anything-at-all-123'])->assertSessionHasErrors('email');
        $this->record(1, 'Existing-member import', ['batch' => $batch->reference, 'members_created' => 2, 'login_accounts_created' => 0, 'replay' => 'returned original receipt', 'duplicate_detection' => '2 of 2 rows flagged on re-upload', 'anonymous' => 'redirected to sign-in', 'unauthorised_role' => 403]);

        // ---- Workflow 2: membership application and approval ------------
        $form = ['first_name' => 'Synthetic', 'last_name' => 'Applicant', 'email' => 'applicant@example.test', 'phone' => '08072220001', 'date_of_birth' => '1981-09-09', 'ijebu_town' => 'Ago Iwoye', 'city' => 'Lagos', 'country' => 'Nigeria', 'other_names' => 'Acceptance', 'occupation' => 'Synthetic accountant', 'profile_summary' => 'Synthetic acceptance-test application for workflow two.', 'declaration' => '1', 'privacy' => '1', 'form_stamp' => Crypt::encryptString((string) (time() - 20))] + $this->applicationDocuments();
        $this->post(route('membership.apply.store'), array_diff_key($form, ['declaration' => 1]))->assertSessionHasErrors('declaration');
        $this->post(route('membership.apply.store'), $form)->assertRedirect(route('membership.receipt'));
        $receipt = session('application_receipt');
        $this->post(route('membership.apply.store'), $form)->assertSessionHasErrors('domain');
        $this->post(route('membership.status.lookup'), ['reference' => $receipt['reference'], 'code' => $receipt['token']])->assertSee('Received');
        $this->post(route('membership.status.lookup'), ['reference' => $receipt['reference'], 'code' => 'guess'])->assertSee('could not find');
        $application = MembershipApplication::where('reference', $receipt['reference'])->sole();
        $this->assertSame(0, Member::where('membership_application_id', $application->id)->count());
        $this->post(route('admin.applications.decide', $application), ['decision' => 'under_review', 'private_note' => 'x'])->assertRedirect(route('login'));
        $this->actingAs($systemAdmin)->post(route('admin.applications.decide', $application), ['decision' => 'under_review', 'private_note' => 'x'])->assertForbidden();
        $this->actingAs($membershipOfficer)->post(route('admin.applications.decide', $application), ['decision' => 'under_review', 'private_note' => 'Documents reviewed'])->assertSessionHasNoErrors();
        $approval = $this->actingAs($membershipOfficer)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'Meets the confirmed criteria']);
        $approval->assertSessionHas('one_time_link');
        $activationUrl = session('one_time_link')['url'];
        $this->actingAs($membershipOfficer)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'Duplicate'])->assertSessionHasErrors('domain');
        $newMember = Member::where('membership_application_id', $application->id)->sole();
        $this->assertSame(1, Member::where('membership_number', $newMember->membership_number)->count());
        $this->record(2, 'Membership application and approval', ['application' => $application->reference, 'member' => $newMember->membership_number, 'members_for_application' => 1, 'missing_declaration' => 'rejected', 'duplicate_application' => 'rejected', 'wrong_tracking_code' => 'not found', 'system_admin_decision' => 403, 'repeat_approval' => 'rejected']);

        // ---- Workflow 3: account activation ----------------------------
        $this->post(route('logout'));
        $token = basename(parse_url($activationUrl, PHP_URL_PATH));
        $password = 'acceptance passphrase for workflow three';
        $this->post(route('login.attempt'), ['email' => 'applicant@example.test', 'password' => $password])->assertSessionHasErrors('email');
        $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
        $this->post(route('account.activate.store'), ['token' => $token, 'password' => $password, 'password_confirmation' => $password])->assertRedirect(route('portal.dashboard'));
        $this->get(route('portal.dashboard'))->assertOk()->assertSee($newMember->membership_number);
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->post(route('logout'));
        $this->post(route('account.activate.store'), ['token' => $token, 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('domain');
        $expired = app(AccountService::class)->issue($imported[0]->user ?? app(AccountService::class)->provisionMemberAccount($imported[0]), 'activation', 'admin', $membershipOfficer->id);
        $expired['record']->update(['expires_at' => now()->subMinute()]);
        $this->post(route('account.activate.store'), ['token' => $expired['token'], 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('domain');
        $this->post(route('login.attempt'), ['email' => 'applicant@example.test', 'password' => $password])->assertRedirect(route('portal.dashboard'));
        $memberUser = $newMember->fresh()->user;
        $this->record(3, 'Account activation', ['login_before_activation' => 'denied', 'activation' => 'succeeded', 'portal' => 200, 'admin_area_for_member' => 403, 'replayed_token' => 'rejected', 'expired_token' => 'rejected']);

        // ---- Workflow 4: financial assessment of ₦50,000 -----------------
        $this->actingAs($treasurer)->post(route('admin.finance.categories.store'), ['code' => 'ACC-DUES', 'name' => 'Acceptance dues (synthetic)', 'kind' => 'dues'])->assertSessionHasNoErrors();
        $categoryId = FeeCategory::where('code', 'ACC-DUES')->value('id');
        $this->actingAs($membershipOfficer)->post(route('admin.finance.assessments.store'), ['fee_category_id' => $categoryId, 'title' => 'x', 'amount' => '50000', 'effective_on' => now()->subDays(20)->toDateString(), 'target_mode' => 'selected', 'member_ids' => [$newMember->id]])->assertForbidden();
        $this->actingAs($treasurer)->post(route('admin.finance.assessments.store'), ['fee_category_id' => $categoryId, 'title' => 'Synthetic acceptance assessment', 'amount' => '50,000', 'effective_on' => now()->subDays(20)->toDateString(), 'target_mode' => 'selected', 'member_ids' => [$newMember->id]])->assertSessionHasNoErrors();
        $assessment = Assessment::where('title', 'Synthetic acceptance assessment')->sole();
        $this->actingAs($treasurer)->get(route('admin.finance.assessments.show', $assessment))->assertOk()->assertSee('₦50,000.00');
        $key = $this->key();
        $this->actingAs($treasurer)->post(route('admin.finance.assessments.post', $assessment), ['idempotency_key' => $key, 'expected_targets' => 1, 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('admin.finance.assessments.post', $assessment), ['idempotency_key' => $key, 'expected_targets' => 1, 'confirm' => '1'])->assertSessionHas('status', fn ($s) => str_contains($s, 'already been posted'));
        $this->actingAs($treasurer)->post(route('admin.finance.assessments.post', $assessment), ['idempotency_key' => $this->key(), 'expected_targets' => 1, 'confirm' => '1'])->assertSessionHasErrors('domain');
        $obligation = MemberObligation::where('assessment_id', $assessment->id)->sole();
        $this->assertSame(5_000_000, $obligation->amount_kobo);
        $this->record(4, 'Financial assessment (₦50,000 synthetic fixture)', ['assessment' => $assessment->reference, 'obligations' => 1, 'amount_kobo' => 5_000_000, 'same_key_replay' => 'returned original', 'second_post' => 'rejected', 'membership_officer' => 403]);

        // ---- Workflow 5: manual ₦20,000 bank transfer --------------------
        $this->actingAs($memberUser)->post(route('admin.finance.payments.store'), ['member_id' => $newMember->id, 'amount' => '20000', 'method' => 'bank_transfer', 'received_on' => now()->subDays(5)->toDateString(), 'idempotency_key' => $this->key()])->assertForbidden();
        $payKey = $this->key();
        $paymentData = ['member_id' => $newMember->id, 'amount' => '20,000.00', 'method' => 'bank_transfer', 'received_on' => now()->subDays(5)->toDateString(), 'external_reference' => 'ACC-TRF-0001', 'idempotency_key' => $payKey];
        $this->actingAs($treasurer)->post(route('admin.finance.payments.store'), $paymentData)->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('admin.finance.payments.store'), $paymentData)->assertSessionHas('status', fn ($s) => str_contains($s, 'already recorded'));
        $payment = Payment::where('external_reference', 'ACC-TRF-0001')->sole();
        $this->assertSame('pending', $payment->status);
        $this->actingAs($treasurer)->post(route('admin.finance.payments.verify', $payment), ['confirm' => '1'])->assertForbidden();
        $this->actingAs($verifier)->post(route('admin.finance.payments.verify', $payment), ['confirm' => '1', 'note' => 'Matched synthetic bank statement line'])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('admin.finance.payments.allocate', $payment), ['amounts' => [$obligation->id => '20000.01'], 'idempotency_key' => $this->key()])->assertSessionHasErrors('domain');
        $allocKey = $this->key();
        $this->actingAs($treasurer)->post(route('admin.finance.payments.allocate', $payment), ['amounts' => [$obligation->id => '20000'], 'idempotency_key' => $allocKey])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('admin.finance.payments.allocate', $payment), ['amounts' => [$obligation->id => '20000'], 'idempotency_key' => $allocKey])->assertSessionHas('status', fn ($s) => str_contains($s, 'already recorded'));
        $this->actingAs($treasurer)->post(route('admin.finance.payments.allocate', $payment), ['amounts' => [$obligation->id => '1'], 'idempotency_key' => $this->key()])->assertSessionHasErrors('domain');
        $this->assertSame(1, Payment::where('member_id', $newMember->id)->count());
        $this->assertSame(1, PaymentAllocation::where('payment_id', $payment->id)->count());
        $this->record(5, 'Manual ₦20,000 bank-transfer payment', ['payment' => $payment->reference, 'status' => $payment->fresh()->status, 'member_recording' => 403, 'treasurer_self_verify' => 403, 'over_allocation' => 'rejected', 'duplicate_submission' => 'returned original', 'repeat_allocation' => 'rejected']);

        // ---- Workflow 6: statement reconciliation -----------------------
        $ledger = app(LedgerQuery::class);
        $summary = $ledger->memberSummary($newMember->id);
        $statement = $ledger->statement($newMember->id, CarbonImmutable::now()->subYear(), CarbonImmutable::now());
        $db = DB::selectOne("SELECT (SELECT SUM(amount_kobo) FROM member_obligations WHERE member_id = ?) o, (SELECT SUM(amount_kobo) FROM payment_allocations WHERE member_id = ? AND status = 'active') a", [$newMember->id, $newMember->id]);
        $this->assertSame([5_000_000, 2_000_000, 3_000_000], [$summary['effective_obligations'], $summary['allocated'], $summary['outstanding']]);
        $this->assertSame([5_000_000, 2_000_000], [(int) $db->o, (int) $db->a]);
        $this->assertSame(3_000_000, $statement['closing']);
        $this->assertSame(3_000_000, $statement['as_at']['outstanding']);
        $this->actingAs($memberUser)->get(route('portal.statement'))->assertOk()->assertSee('₦50,000.00')->assertSee('₦20,000.00')->assertSee('₦30,000.00');
        $this->actingAs($memberUser)->get(route('portal.finances'))->assertOk()->assertSee('₦30,000.00');
        $balances = $ledger->memberBalances()->firstWhere('member_id', $newMember->id);
        $this->assertSame(3_000_000, $balances['outstanding']);
        $this->actingAs($treasurer)->get(route('admin.finance.reports.index'))->assertOk()->assertSee('₦30,000.00');
        $csv = $this->actingAs($treasurer)->get(route('admin.finance.reports.export', 'balances'))->streamedContent();
        $this->assertStringContainsString($newMember->membership_number, $csv);
        $this->assertStringContainsString('50000.00,0.00,20000.00,30000.00', $csv);
        $this->assertEmpty(array_filter($ledger->exceptions()));
        $this->record(6, 'Statement reconciliation', ['assessed' => '₦50,000.00', 'allocated' => '₦20,000.00', 'outstanding' => '₦30,000.00', 'member_statement' => 'matches', 'treasury_report_and_csv' => 'matches', 'raw_mariadb_totals' => 'matches', 'ledger_exceptions' => 0]);

        // ---- Workflow 7: encrypted backup and isolated restore ----------
        $keys = BackupService::generateKeypair();
        $dir = storage_path('app/private/acceptance-backup');
        File::ensureDirectoryExists($dir, 0700);
        $run = app(BackupService::class)->create($systemAdmin->id, $keys['public'], $dir);
        $path = $dir.'/'.$run->file_name;
        DB::statement('DROP DATABASE IF EXISTS bobagunwa_acceptance_restore');
        DB::statement('CREATE DATABASE bobagunwa_acceptance_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            app(BackupService::class)->restoreAndVerify($path, BackupService::generateKeypair()['keypair'], 'bobagunwa_acceptance_restore');
            $this->fail('Wrong key accepted');
        } catch (DomainRuleException) {
        }
        $tampered = $dir.'/tampered.bobabk';
        $bytes = file_get_contents($path);
        file_put_contents($tampered, substr_replace($bytes, chr(ord($bytes[200]) ^ 0xFF), 200, 1));
        try {
            app(BackupService::class)->restoreAndVerify($tampered, $keys['keypair'], 'bobagunwa_acceptance_restore');
            $this->fail('Tampered backup accepted');
        } catch (DomainRuleException) {
        }
        $report = app(BackupService::class)->restoreAndVerify($path, $keys['keypair'], 'bobagunwa_acceptance_restore');
        $this->assertTrue($report['ok'], json_encode($report['problems']));
        $restored = DB::selectOne("SELECT (SELECT COUNT(*) FROM bobagunwa_acceptance_restore.members) m, (SELECT SUM(amount_kobo) FROM bobagunwa_acceptance_restore.member_obligations WHERE member_id = ?) o, (SELECT SUM(amount_kobo) FROM bobagunwa_acceptance_restore.payment_allocations WHERE member_id = ? AND status = 'active') a, (SELECT COUNT(*) FROM bobagunwa_acceptance_restore.audit_events) e, (SELECT COUNT(*) FROM bobagunwa_acceptance_restore.users WHERE member_id IN (SELECT id FROM bobagunwa_acceptance_restore.members WHERE origin = 'import')) ia", [$newMember->id, $newMember->id]);
        $this->assertSame(Member::count(), (int) $restored->m);
        $this->assertSame(3_000_000, (int) $restored->o - (int) $restored->a);
        $this->assertSame(AuditEvent::count(), (int) $restored->e);
        File::deleteDirectory($dir);
        $this->record(7, 'Encrypted backup and isolated restoration', ['cipher' => 'libsodium sealed box + XChaCha20-Poly1305 secretstream', 'backup_bytes' => $run->size_bytes, 'backup_sha256' => $run->sha256, 'tables' => $report['tables'], 'rows' => $report['rows'], 'triggers_restored' => $report['triggers'], 'foreign_keys_checked' => $report['foreign_key_constraints_checked'], 'orphaned_rows' => $report['orphaned_rows'], 'restored_members' => (int) $restored->m, 'restored_outstanding_kobo' => (int) $restored->o - (int) $restored->a, 'restored_audit_events' => (int) $restored->e, 'wrong_key' => 'rejected', 'tampered' => 'rejected']);

        file_put_contents(storage_path('logs/acceptance-evidence.json'), json_encode([
            'executed_at' => now()->toIso8601String(), 'database' => DB::selectOne('SELECT VERSION() v')->v, 'php' => PHP_VERSION,
            'laravel' => app()->version(), 'data' => 'synthetic only', 'workflows' => $this->evidence,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertCount(7, $this->evidence);
    }
}
