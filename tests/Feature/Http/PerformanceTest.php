<?php

namespace Tests\Feature\Http;

use App\Models\Member;
use App\Services\Finance\AssessmentService;
use App\Services\Finance\LedgerQuery;
use App\Support\Normalize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Representative volumes: N members, two posted assessments each, a verified
 * payment and allocation for half of them, pending payments, applications,
 * notices and events. Measures time, query count and memory for the heaviest
 * pages and fails on N+1 query growth. Local MariaDB measurements; shared
 * hosting will be slower and is not load-tested (see docs/19-test-evidence.md).
 */
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public static function volumes(): array
    {
        return [[100], [500], [1000]];
    }

    private function seedVolume(int $n): array
    {
        $now = now();
        $officer = $this->officer('treasurer');
        $verifier = $this->officer('payment_verifier');
        $numbers = [];
        $members = [];
        for ($i = 1; $i <= $n; $i++) {
            $number = sprintf('PERF-%06d', $i);
            $numbers[] = ['number' => $number, 'source' => 'provided', 'issued_at' => $now];
            $members[] = ['membership_number' => $number, 'full_name' => "Perf Synthetic {$i}", 'name_normalized' => Normalize::name("Perf Synthetic {$i}"), 'email' => "perf{$i}@example.test", 'email_normalized' => "perf{$i}@example.test", 'status' => 'active', 'origin' => 'import', 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($numbers, 500) as $c) {
            DB::table('membership_numbers')->insert($c);
        }
        foreach (array_chunk($members, 500) as $c) {
            DB::table('members')->insert($c);
        }
        $service = app(AssessmentService::class);
        foreach (['50000', '10000'] as $amount) {
            $draft = $service->createDraft(['fee_category_id' => $this->category()->id, 'title' => "Perf {$amount}", 'amount' => $amount, 'effective_on' => now()->subMonth()->toDateString(), 'target_mode' => 'all_active'], $officer);
            $service->post($draft, $officer, $this->key(), $n);
        }
        $obligations = DB::table('member_obligations')->where('amount_kobo', 5_000_000)->pluck('id', 'member_id');
        $payments = [];
        foreach (Member::pluck('id') as $k => $memberId) {
            $payments[] = ['reference' => "PERF-PAY-{$memberId}", 'member_id' => $memberId, 'amount_kobo' => 2_000_000, 'method' => 'bank_transfer', 'received_on' => now()->subDays(3)->toDateString(), 'status' => 'pending', 'requires_distinct_verifier' => 1, 'idempotency_key' => "perf-{$memberId}-key-000000", 'recorded_by' => $officer->id, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($payments, 500) as $c) {
            DB::table('payments')->insert($c);
        }
        // Verify and allocate for half the members (set-based, through triggers).
        DB::statement("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE MOD(member_id, 2) = 0", [$verifier->id]);
        // Rows are computed first: MariaDB refuses INSERT ... SELECT FROM payments here because the
        // allocation trigger locks payment rows (error 1442) — a safe refusal, documented.
        $rows = DB::table('payments as p')->join('member_obligations as o', fn ($j) => $j->on('o.member_id', '=', 'p.member_id')->where('o.amount_kobo', 5_000_000))
            ->where('p.status', 'verified')->get(['p.id as payment_id', 'o.id as obligation_id', 'p.member_id'])
            ->map(fn ($r) => ['payment_id' => $r->payment_id, 'member_obligation_id' => $r->obligation_id, 'member_id' => $r->member_id, 'amount_kobo' => 1_500_000, 'allocated_by' => $officer->id])->all();
        foreach (array_chunk($rows, 250) as $c) {
            DB::table('payment_allocations')->insert($c);
        }

        return [$officer, Member::first()];
    }

    #[DataProvider('volumes')]
    public function test_heavy_pages_stay_bounded_at_volume(int $n): void
    {
        [$treasurer, $member] = $this->seedVolume($n);
        $admin = $this->officer(['treasurer', 'secretariat', 'membership_officer', 'executive']);
        $portalMember = $this->member([], true);
        $this->assertEmpty(array_filter(app(LedgerQuery::class)->exceptions()));

        $pages = [
            'admin.members.index' => [route('admin.members.index'), $admin],
            'admin.finance.index' => [route('admin.finance.index'), $admin],
            'admin.finance.reports.index (all balances)' => [route('admin.finance.reports.index'), $admin],
            'admin.finance.payments.index' => [route('admin.finance.payments.index'), $admin],
            'admin.finance.members.show' => [route('admin.finance.members.show', $member), $admin],
            'admin.dashboard' => [route('admin.dashboard'), $admin],
            'portal.dashboard' => [route('portal.dashboard'), $portalMember->user],
            'portal.statement' => [route('portal.statement'), $portalMember->user],
        ];
        $lines = [];
        foreach ($pages as $label => [$url, $user]) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $start = microtime(true);
            $this->actingAs($user)->get($url)->assertOk();
            $ms = (microtime(true) - $start) * 1000;
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            $lines[] = sprintf('%-45s %6.0f ms %4d queries', $label, $ms, $queries);
            $this->assertLessThan(60, $queries, "{$label} issues {$queries} queries at {$n} members: possible N+1");
            $this->assertLessThan(5000, $ms, "{$label} took {$ms} ms at {$n} members");
        }
        $csvStart = microtime(true);
        $csv = $this->actingAs($admin)->get(route('admin.finance.reports.export', 'balances'))->streamedContent();
        $lines[] = sprintf('%-45s %6.0f ms %4d rows', 'balances CSV export', (microtime(true) - $csvStart) * 1000, substr_count($csv, "\n") - 1);
        fwrite(STDERR, "\n[evidence] {$n} members, ".(2 * $n).' obligations, '.$n.' payments ('.intdiv($n, 2).' verified+allocated), peak memory '.round(memory_get_peak_usage(true) / 1048576, 1)." MB\n  ".implode("\n  ", $lines)."\n");
    }
}
