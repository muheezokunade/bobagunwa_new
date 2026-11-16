<?php

namespace Tests\Feature\Concurrency;

use App\Models\Assessment;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\Payment;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\Finance\AssessmentService;
use App\Services\Finance\LedgerQuery;
use App\Services\Finance\PaymentService;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Real concurrency: each contender is a separate OS process (pcntl_fork) with
 * its own MariaDB connection, released at the same instant. Data is committed,
 * so these tests run outside the per-test transaction wrapper.
 */
class ConcurrencyTest extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for concurrency tests');
        }
        if (! self::$migrated) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            self::$migrated = true;
        }
    }

    protected function tearDown(): void
    {
        // Committed data must not leak into the transactional tests that follow.
        Artisan::call('migrate:fresh', ['--force' => true]);
        self::$migrated = true;
        parent::tearDown();
    }

    /**
     * Run closures in parallel processes; returns 'ok' or the error message per contender.
     *
     * @param  list<Closure>  $contenders
     * @return list<string>
     */
    private function race(array $contenders): array
    {
        DB::disconnect();
        $start = microtime(true) + 0.4;
        $files = [];
        $pids = [];
        foreach ($contenders as $i => $work) {
            $files[$i] = tempnam(sys_get_temp_dir(), 'race');
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::purge();
                $result = 'ok';
                try {
                    DB::connection()->getPdo();
                    while (microtime(true) < $start) {
                        usleep(200);
                    }
                    $work();
                } catch (Throwable $e) {
                    $result = get_class($e).': '.$e->getMessage();
                }
                file_put_contents($files[$i], $result);
                posix_kill(getmypid(), SIGKILL);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect();

        return array_map(function ($f) {
            $r = (string) file_get_contents($f);
            unlink($f);

            return $r;
        }, $files);
    }

    public function test_two_requests_cannot_exhaust_the_same_payment_twice(): void
    {
        for ($round = 0; $round < 8; $round++) {
            $member = $this->member();
            $assessment1 = $this->assess([$member], '50000');
            $assessment2 = $this->assess([$member], '50000');
            $o1 = $this->obligationOf($member, $assessment1);
            $o2 = $this->obligationOf($member, $assessment2);
            $payment = $this->verifiedPayment($member, '20000');
            $treasurer = $this->officer('treasurer');

            $results = $this->race([
                fn () => app(PaymentService::class)->allocate(Payment::find($payment->id), [$o1->id => 1_500_000], User::find($treasurer->id), $this->key()),
                fn () => app(PaymentService::class)->allocate(Payment::find($payment->id), [$o2->id => 1_500_000], User::find($treasurer->id), $this->key()),
            ]);
            $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'ok')), 'Exactly one allocation may succeed: '.json_encode($results));
            $this->assertLessThanOrEqual(2_000_000, (int) DB::table('payment_allocations')->where('payment_id', $payment->id)->where('status', 'active')->sum('amount_kobo'));
        }
        $this->assertEmpty(array_filter(app(LedgerQuery::class)->exceptions()));
    }

    public function test_direct_sql_racers_cannot_over_allocate_even_under_repeatable_read(): void
    {
        foreach (['READ COMMITTED', 'REPEATABLE READ'] as $level) {
            $member = $this->member();
            $assessment = $this->assess([$member], '50000');
            $o = $this->obligationOf($member, $assessment);
            $payment = $this->verifiedPayment($member, '20000');
            $officer = $this->officer('treasurer')->id;
            $insert = function () use ($payment, $o, $member, $officer, $level) {
                DB::statement("SET SESSION TRANSACTION ISOLATION LEVEL {$level}");
                DB::insert('INSERT INTO payment_allocations (payment_id, member_obligation_id, member_id, amount_kobo, allocated_by) VALUES (?, ?, ?, ?, ?)', [$payment->id, $o->id, $member->id, 1_500_000, $officer]);
            };
            $results = $this->race([$insert, $insert, $insert]);
            $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'ok')), "{$level}: ".json_encode($results));
            $this->assertSame(1_500_000, (int) DB::table('payment_allocations')->where('payment_id', $payment->id)->sum('amount_kobo'));
        }
    }

    public function test_duplicate_payment_submitted_concurrently_is_recorded_once(): void
    {
        $member = $this->member();
        $treasurer = $this->officer('treasurer');
        $key = $this->key();
        $data = ['member_id' => $member->id, 'amount' => '20000', 'method' => 'bank_transfer', 'received_on' => now()->toDateString(), 'external_reference' => 'RACE-'.uniqid()];
        $submit = fn () => app(PaymentService::class)->record($data, User::find($treasurer->id), $key);
        $results = $this->race([$submit, $submit, $submit]);
        $this->assertSame(['ok', 'ok', 'ok'], $results, 'Replays must return the original payment, not fail');
        $this->assertSame(1, Payment::where('member_id', $member->id)->count());

        // Same bank reference, different idempotency keys: still only one payment.
        $data['external_reference'] = 'RACE2-'.uniqid();
        $results = $this->race([
            fn () => app(PaymentService::class)->record($data, User::find($treasurer->id), $this->key()),
            fn () => app(PaymentService::class)->record($data + ['confirm_possible_duplicate' => true], User::find($treasurer->id), $this->key()),
        ]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'ok')), json_encode($results));
        $this->assertSame(1, Payment::where('external_reference', $data['external_reference'])->count());
    }

    public function test_concurrent_approval_creates_exactly_one_member_and_number(): void
    {
        [$application] = app(ApplicationService::class)->submit(['first_name' => 'Race', 'last_name' => 'Applicant', 'email' => 'race-'.uniqid().'@example.test', 'phone' => '0809'.random_int(1000000, 9999999), 'date_of_birth' => '1981-01-01', 'ijebu_town' => 'Ijebu Ode', 'city' => 'Lagos', 'occupation' => 'Synthetic', 'reason' => 'Synthetic concurrency test application.']);
        $officer = $this->officer('membership_officer');
        app(ApplicationService::class)->transition($application, 'under_review', $officer, 'Start', null);
        $before = Member::count();
        $approve = fn () => app(ApplicationService::class)->approve(MembershipApplication::find($application->id), User::find($officer->id), 'Approve', null);
        $results = $this->race([$approve, $approve, $approve]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'ok')), json_encode($results));
        $this->assertSame($before + 1, Member::count());
        $this->assertSame(1, Member::where('membership_application_id', $application->id)->count());
    }

    public function test_concurrent_number_issuance_never_duplicates(): void
    {
        $before = DB::table('membership_numbers')->count();
        $make = function () {
            for ($i = 0; $i < 15; $i++) {
                $this->member();
            }
        };
        $results = $this->race([$make, $make, $make]);
        $this->assertSame(['ok', 'ok', 'ok'], $results);
        $this->assertSame($before + 45, DB::table('membership_numbers')->count());
        $this->assertSame(DB::table('members')->count(), DB::table('members')->distinct()->count('membership_number'));
    }

    public function test_concurrent_assessment_posting_creates_one_set_of_obligations(): void
    {
        $member = $this->member();
        $treasurer = $this->officer('treasurer');
        $draft = app(AssessmentService::class)->createDraft(['fee_category_id' => $this->category()->id, 'title' => 'Race', 'amount' => '1000', 'effective_on' => now()->toDateString(), 'target_mode' => 'selected', 'member_ids' => [$member->id]], $treasurer);
        $post = fn () => app(AssessmentService::class)->post(Assessment::find($draft->id), User::find($treasurer->id), $this->key(), 1);
        $results = $this->race([$post, $post]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'ok')), json_encode($results));
        $this->assertSame(1, DB::table('member_obligations')->where('assessment_id', $draft->id)->count());
    }
}
