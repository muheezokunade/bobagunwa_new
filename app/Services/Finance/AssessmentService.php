<?php

namespace App\Services\Finance;

use App\Exceptions\DomainRuleException;
use App\Models\Assessment;
use App\Models\FeeCategory;
use App\Models\Member;
use App\Models\MemberObligation;
use App\Models\User;
use App\Support\Audit;
use App\Support\Idempotency;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dues and levy assessments: draft → preview → post exactly once.
 * Posting creates one immutable obligation per targeted active member.
 */
class AssessmentService
{
    /** @param array<string, mixed> $data */
    public function createDraft(array $data, User $officer): Assessment
    {
        Ledger::authorise($officer, 'finance.assessments.manage', 'create assessments');
        $category = FeeCategory::where('id', $data['fee_category_id'])->where('is_active', true)->first();
        if (! $category) {
            throw new DomainRuleException('Choose an active dues or levy category.');
        }
        $amount = Money::parse($data['amount']);
        $mode = $data['target_mode'] === 'selected' ? 'selected' : 'all_active';
        $ids = $mode === 'selected' ? array_values(array_unique(array_map('intval', (array) ($data['member_ids'] ?? [])))) : null;
        if ($mode === 'selected' && ! $ids) {
            throw new DomainRuleException('Select at least one member.');
        }

        $assessment = Assessment::create([
            'reference' => Ledger::reference('ASM'),
            'fee_category_id' => $category->id,
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'amount_kobo' => $amount,
            'due_on' => $data['due_on'] ?? null,
            'effective_on' => $data['effective_on'],
            'period_label' => $data['period_label'] ?? null,
            'origin' => 'native',
            'status' => 'draft',
            'target_mode' => $mode,
            'target_member_ids' => $ids,
            'created_by' => $officer->id,
        ]);
        Audit::record('assessment.drafted', "Assessment {$assessment->reference} drafted: {$assessment->title}", $assessment, ['amount_kobo' => $amount]);

        return $assessment;
    }

    /**
     * @return array{targets: Collection<int, Member>, excluded: Collection<int, Member>, total_kobo: int, possible_duplicates: Collection<int, string>}
     */
    public function preview(Assessment $assessment): array
    {
        $query = Member::query()->select(['id', 'membership_number', 'full_name', 'status']);
        if ($assessment->target_mode === 'selected') {
            $query->whereIn('id', $assessment->target_member_ids ?? []);
        }
        $members = $query->orderBy('membership_number')->get();
        $targets = $members->where('status', 'active')->values();
        $excluded = $members->where('status', '!=', 'active')->values();

        $duplicates = collect();
        if ($assessment->period_label) {
            $duplicates = MemberObligation::query()
                ->join('assessments', 'assessments.id', '=', 'member_obligations.assessment_id')
                ->join('members', 'members.id', '=', 'member_obligations.member_id')
                ->where('assessments.fee_category_id', $assessment->fee_category_id)
                ->where('assessments.period_label', $assessment->period_label)
                ->where('assessments.id', '!=', $assessment->id)
                ->whereIn('member_obligations.member_id', $targets->pluck('id'))
                ->pluck('members.membership_number');
        }

        return ['targets' => $targets, 'excluded' => $excluded, 'total_kobo' => $targets->count() * $assessment->amount_kobo, 'possible_duplicates' => $duplicates];
    }

    /** @return array{0: Assessment, 1: bool} [assessment, replayed] */
    public function post(Assessment $assessment, User $officer, ?string $idempotencyKey, int $expectedTargetCount): array
    {
        Ledger::authorise($officer, 'finance.assessments.manage', 'post assessments');

        return Ledger::guard(fn () => Idempotency::run('assessment.post', $idempotencyKey, ['assessment' => $assessment->id], function () use ($assessment, $officer, $expectedTargetCount) {
            $locked = Assessment::lockForUpdate()->findOrFail($assessment->id);
            if ($locked->status !== 'draft') {
                throw new DomainRuleException('This assessment has already been '.$locked->status.'.');
            }
            $targets = $this->preview($locked)['targets'];
            if ($targets->isEmpty()) {
                throw new DomainRuleException('No active members match this assessment.');
            }
            if ($targets->count() !== $expectedTargetCount) {
                throw new DomainRuleException('The member list changed since you reviewed the preview. Review it again before posting.');
            }
            $locked->update([
                'status' => 'posted', 'posted_by' => $officer->id, 'posted_at' => now(),
                'target_count' => $targets->count(), 'total_kobo' => $targets->count() * $locked->amount_kobo,
            ]);
            foreach ($targets->chunk(250) as $chunk) {
                MemberObligation::insert($chunk->map(fn ($m) => [
                    'assessment_id' => $locked->id, 'member_id' => $m->id, 'amount_kobo' => $locked->amount_kobo,
                    'effective_on' => $locked->effective_on->toDateString(), 'created_by' => $officer->id, 'created_at' => now(),
                ])->values()->all());
            }
            Audit::record('assessment.posted', "Assessment {$locked->reference} posted to {$targets->count()} members (".Money::format($locked->total_kobo).')', $locked, ['target_count' => $targets->count(), 'total_kobo' => $locked->total_kobo]);

            return $locked->fresh();
        }, Assessment::class));
    }

    public function discard(Assessment $assessment, User $officer): void
    {
        Ledger::authorise($officer, 'finance.assessments.manage', 'discard assessments');
        Ledger::guard(fn () => DB::transaction(function () use ($assessment) {
            $locked = Assessment::lockForUpdate()->findOrFail($assessment->id);
            if ($locked->status !== 'draft') {
                throw new DomainRuleException('Only draft assessments can be discarded.');
            }
            $locked->update(['status' => 'discarded']);
            Audit::record('assessment.discarded', "Draft assessment {$locked->reference} discarded", $locked);
        }));
    }
}
