<?php

namespace App\Services\Finance;

use App\Exceptions\DomainRuleException;
use App\Models\Assessment;
use App\Models\FeeCategory;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Member;
use App\Models\MemberObligation;
use App\Models\Payment;
use App\Models\User;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Idempotency;
use App\Support\Money;
use App\Support\Settings;
use App\Support\Tokens;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Guarded import of approved historical records.
 *
 *  - "finance_transactions": verified historical obligations and payments,
 *    each traceable to a source reference.
 *  - "opening_balances": approved amounts owed at a cut-off date, labelled as
 *    opening balances. Never converted into fictional payments; members in
 *    credit must be supported by real historical payment records.
 *
 * Every import needs a documented source approval reference. When the
 * two-officer rule is on, the officer who commits must differ from the one
 * who uploaded the preview.
 */
class FinanceImportService
{
    public const TRANSACTION_COLUMNS = [
        'record_type' => ['type'],
        'membership_number' => ['member_number', 'membership_no'],
        'date' => ['transaction_date'],
        'amount_ngn' => ['amount', 'amount_naira'],
        'category_code' => ['category'],
        'external_reference' => ['reference', 'source_reference'],
        'description' => ['narrative', 'details'],
    ];

    public const OPENING_COLUMNS = [
        'membership_number' => ['member_number', 'membership_no'],
        'amount_ngn' => ['amount', 'amount_naira', 'balance'],
        'description' => ['narrative', 'details'],
    ];

    public function template(string $kind): string
    {
        return $kind === 'opening_balances'
            ? "membership_number,amount_ngn,description\nBOB-000001,15000.00,Approved balance owed at cut-off\n"
            : "record_type,membership_number,date,amount_ngn,category_code,external_reference,description\nobligation,BOB-000001,2025-01-31,10000.00,DUES-2025,MINUTES-2025-01,Annual dues 2025\npayment,BOB-000001,2025-02-15,10000.00,,BANK-TRF-0001,Transfer per bank statement\n";
    }

    /** @param array<string, mixed> $options */
    public function preview(string $kind, UploadedFile $file, array $options, User $officer): ImportBatch
    {
        Ledger::authorise($officer, 'finance.import', 'import historical financial records');
        if (! in_array($kind, ['finance_transactions', 'opening_balances'], true)) {
            throw new DomainRuleException('Unknown import type.');
        }
        $approval = trim((string) ($options['source_approval_reference'] ?? ''));
        if (mb_strlen($approval) < 5) {
            throw new DomainRuleException('Record the approved source document reference (for example the minute or signed schedule reference).');
        }
        $cutoff = null;
        $openingCategory = null;
        if ($kind === 'opening_balances') {
            $cutoff = Csv::date((string) ($options['cutoff_date'] ?? ''));
            if (! $cutoff || $cutoff->isFuture()) {
                throw new DomainRuleException('Enter the approved cut-off date (not in the future).');
            }
            $openingCategory = FeeCategory::where('id', $options['fee_category_id'] ?? 0)->where('is_active', true)->first();
            if (! $openingCategory) {
                throw new DomainRuleException('Choose the active category used to label opening balances.');
            }
        }
        $sha = hash_file('sha256', $file->getRealPath());
        if (ImportBatch::where('kind', $kind)->where('status', 'committed')->where('file_sha256', $sha)->exists()) {
            throw new DomainRuleException('This exact file has already been imported. No records were changed.');
        }
        $columns = $kind === 'opening_balances' ? self::OPENING_COLUMNS : self::TRANSACTION_COLUMNS;
        $parsed = Csv::read($file->getRealPath(), $columns, (int) config('boba.uploads.import_max_rows'));
        if (! $parsed['rows']) {
            throw new DomainRuleException('The file contains no rows.');
        }
        $evaluated = $kind === 'opening_balances' ? $this->evaluateOpening($parsed['rows']) : $this->evaluateTransactions($parsed['rows']);
        $valid = array_filter($evaluated, fn ($r) => $r['status'] === 'valid');
        $totals = [
            'obligations_kobo' => array_sum(array_map(fn ($r) => $r['normalized']['record_type'] === 'obligation' ? $r['normalized']['amount_kobo'] : 0, $valid)),
            'payments_kobo' => array_sum(array_map(fn ($r) => $r['normalized']['record_type'] === 'payment' ? $r['normalized']['amount_kobo'] : 0, $valid)),
            'members' => count(array_unique(array_map(fn ($r) => $r['normalized']['member_id'], $valid))),
        ];

        return DB::transaction(function () use ($kind, $file, $sha, $evaluated, $totals, $approval, $cutoff, $openingCategory, $officer) {
            $counts = array_count_values(array_column($evaluated, 'status')) + ['valid' => 0, 'invalid' => 0, 'duplicate' => 0];
            $batch = ImportBatch::create([
                'reference' => $this->reference(), 'kind' => $kind, 'status' => 'previewed',
                'original_filename' => mb_substr($file->getClientOriginalName(), 0, 190), 'file_sha256' => $sha,
                'row_count' => count($evaluated), 'valid_count' => $counts['valid'], 'invalid_count' => $counts['invalid'], 'duplicate_count' => $counts['duplicate'],
                'summary' => $totals + ['fee_category_id' => $openingCategory?->id],
                'cutoff_date' => $cutoff?->toDateString(), 'source_approval_reference' => $approval, 'created_by' => $officer->id,
            ]);
            foreach (array_chunk($evaluated, 200) as $chunk) {
                ImportRow::insert(array_map(fn ($r) => ['import_batch_id' => $batch->id, 'row_number' => $r['row_number'], 'raw' => json_encode($r['raw']), 'normalized' => json_encode($r['normalized']), 'status' => $r['status'], 'messages' => json_encode($r['messages'])], $chunk));
            }
            Audit::record('finance_import.previewed', "Financial import {$batch->reference} previewed ({$kind}): {$batch->valid_count}/{$batch->row_count} valid", $batch, $totals + ['source' => $approval]);

            return $batch;
        });
    }

    /** @param list<array<string, string>> $rows */
    private function evaluateTransactions(array $rows): array
    {
        $members = Member::whereIn('membership_number', array_map(fn ($r) => strtoupper($r['membership_number']), $rows))->pluck('id', 'membership_number');
        $categories = FeeCategory::where('is_active', true)->pluck('id', 'code');
        $existingRefs = Payment::whereIn('external_reference', array_filter(array_map(fn ($r) => strtoupper(trim($r['external_reference'])), $rows)))->pluck('external_reference')->flip();
        $seenRefs = [];
        $seenObligations = [];
        $out = [];
        foreach ($rows as $i => $row) {
            $errors = [];
            $dupes = [];
            $type = strtolower(trim($row['record_type']));
            $number = strtoupper(trim($row['membership_number']));
            $ref = strtoupper(trim($row['external_reference']));
            $n = ['record_type' => $type, 'membership_number' => $number, 'member_id' => $members[$number] ?? null, 'external_reference' => $ref ?: null, 'description' => trim($row['description']) ?: null, 'amount_kobo' => 0, 'date' => null, 'category_id' => null];
            if (! in_array($type, ['obligation', 'payment'], true)) {
                $errors[] = 'record_type must be “obligation” or “payment”.';
            }
            if (! $n['member_id']) {
                $errors[] = "Membership number “{$number}” is not in the register.";
            }
            try {
                $n['amount_kobo'] = Money::parse($row['amount_ngn']);
            } catch (DomainRuleException $e) {
                $errors[] = 'Amount: '.$e->getMessage();
            }
            try {
                $date = Csv::date($row['date']);
                if (! $date) {
                    $errors[] = 'Date is required.';
                } elseif ($date->isFuture()) {
                    $errors[] = 'Date cannot be in the future.';
                } else {
                    $n['date'] = $date->toDateString();
                }
            } catch (DomainRuleException $e) {
                $errors[] = 'Date: '.$e->getMessage();
            }
            if ($type === 'obligation') {
                $n['category_id'] = $categories[strtoupper(trim($row['category_code']))] ?? null;
                if (! $n['category_id']) {
                    $errors[] = 'category_code must match an active category code.';
                }
                $key = $n['member_id'].'|'.$n['category_id'].'|'.$n['date'];
                if (isset($seenObligations[$key])) {
                    $dupes[] = "Same member, category and date as row {$seenObligations[$key]}.";
                }
                $seenObligations[$key] = $i + 2;
            }
            if ($type === 'payment') {
                if (! $ref) {
                    $errors[] = 'Payments need the source reference (bank or receipt reference).';
                } elseif (isset($existingRefs[$ref])) {
                    $dupes[] = "Reference {$ref} is already recorded.";
                } elseif (isset($seenRefs[$ref])) {
                    $dupes[] = "Reference {$ref} repeats row {$seenRefs[$ref]}.";
                }
                $seenRefs[$ref] = $i + 2;
            }
            $out[] = ['row_number' => $i + 2, 'raw' => $row, 'normalized' => $n, 'status' => $errors ? 'invalid' : ($dupes ? 'duplicate' : 'valid'), 'messages' => array_merge($errors, $dupes)];
        }

        return $out;
    }

    /** @param list<array<string, string>> $rows */
    private function evaluateOpening(array $rows): array
    {
        $members = Member::whereIn('membership_number', array_map(fn ($r) => strtoupper($r['membership_number']), $rows))->pluck('id', 'membership_number');
        $seen = [];
        $out = [];
        foreach ($rows as $i => $row) {
            $errors = [];
            $number = strtoupper(trim($row['membership_number']));
            $n = ['record_type' => 'obligation', 'membership_number' => $number, 'member_id' => $members[$number] ?? null, 'description' => trim($row['description']) ?: null, 'amount_kobo' => 0];
            if (! $n['member_id']) {
                $errors[] = "Membership number “{$number}” is not in the register.";
            }
            if (str_starts_with(trim($row['amount_ngn']), '-')) {
                $errors[] = 'Credit balances cannot be imported as opening balances. Import the supporting historical payment records instead.';
            } else {
                try {
                    $n['amount_kobo'] = Money::parse($row['amount_ngn']);
                } catch (DomainRuleException $e) {
                    $errors[] = 'Amount: '.$e->getMessage();
                }
            }
            $dupe = isset($seen[$number]) ? ["Member repeats row {$seen[$number]}."] : [];
            $seen[$number] = $i + 2;
            $out[] = ['row_number' => $i + 2, 'raw' => $row, 'normalized' => $n, 'status' => $errors ? 'invalid' : ($dupe ? 'duplicate' : 'valid'), 'messages' => array_merge($errors, $dupe)];
        }

        return $out;
    }

    /** @return array{batch: ImportBatch, replayed: bool} */
    public function commit(ImportBatch $batch, User $officer): array
    {
        Ledger::authorise($officer, 'finance.import', 'import historical financial records');
        Settings::requireApproval('approval.historical_finance', 'Importing historical financial records');

        return Ledger::guard(fn () => DB::transaction(function () use ($batch, $officer) {
            $locked = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            if (! in_array($locked->kind, ['finance_transactions', 'opening_balances'], true)) {
                throw new DomainRuleException('This is not a financial import.');
            }
            if ($locked->status === 'committed') {
                return ['batch' => $locked, 'replayed' => true];
            }
            if ($locked->status !== 'previewed') {
                throw new DomainRuleException('This import was cancelled.');
            }
            if ($locked->invalid_count + $locked->duplicate_count > 0) {
                throw new DomainRuleException('Correct every invalid or duplicate row and upload the file again.');
            }
            if (Settings::bool('finance.require_distinct_verifier') && $locked->created_by === $officer->id) {
                throw new DomainRuleException('A different officer must confirm a financial import that you uploaded.');
            }
            $rows = $locked->rows()->get();
            $groups = [];
            foreach ($rows as $row) {
                $n = $row->normalized;
                if ($n['record_type'] === 'obligation') {
                    $categoryId = $locked->kind === 'opening_balances' ? $locked->summary['fee_category_id'] : $n['category_id'];
                    $date = $locked->kind === 'opening_balances' ? $locked->cutoff_date->toDateString() : $n['date'];
                    $groups["{$categoryId}|{$date}"][] = $row;
                } else {
                    $payment = Payment::create([
                        'reference' => Ledger::reference('PAY'), 'member_id' => $n['member_id'], 'amount_kobo' => $n['amount_kobo'], 'currency' => 'NGN',
                        'method' => 'other', 'received_on' => $n['date'], 'external_reference' => $n['external_reference'], 'narrative' => $n['description'],
                        'origin' => 'historical', 'status' => 'verified', 'requires_distinct_verifier' => false, 'import_batch_id' => $locked->id,
                        'idempotency_key' => Idempotency::newKey(), 'recorded_by' => $locked->created_by, 'verified_by' => $officer->id, 'verified_at' => now(),
                        'verification_note' => "Historical import {$locked->reference}; source {$locked->source_approval_reference}",
                    ]);
                    $row->update(['result_type' => 'Payment', 'result_id' => $payment->id]);
                }
            }
            foreach ($groups as $key => $groupRows) {
                [$categoryId, $date] = explode('|', $key);
                $origin = $locked->kind === 'opening_balances' ? 'opening_balance' : 'historical';
                $assessment = Assessment::create([
                    'reference' => Ledger::reference('ASM'), 'fee_category_id' => (int) $categoryId,
                    'title' => $origin === 'opening_balance' ? "Opening balance at {$date}" : "Historical obligations {$date}",
                    'description' => "Imported from {$locked->reference}; source {$locked->source_approval_reference}",
                    'amount_kobo' => max(array_map(fn ($r) => $r->normalized['amount_kobo'], $groupRows)),
                    'effective_on' => $date, 'origin' => $origin, 'status' => 'draft', 'target_mode' => 'selected',
                    'target_member_ids' => array_map(fn ($r) => $r->normalized['member_id'], $groupRows),
                    'import_batch_id' => $locked->id, 'created_by' => $locked->created_by,
                ]);
                $assessment->update(['status' => 'posted', 'posted_by' => $officer->id, 'posted_at' => now(), 'target_count' => count($groupRows), 'total_kobo' => array_sum(array_map(fn ($r) => $r->normalized['amount_kobo'], $groupRows))]);
                foreach ($groupRows as $row) {
                    $obligation = MemberObligation::create(['assessment_id' => $assessment->id, 'member_id' => $row->normalized['member_id'], 'amount_kobo' => $row->normalized['amount_kobo'], 'effective_on' => $date, 'created_by' => $officer->id]);
                    $row->update(['result_type' => 'MemberObligation', 'result_id' => $obligation->id]);
                }
            }
            $locked->update(['status' => 'committed', 'committed_by' => $officer->id, 'committed_at' => now()]);
            Audit::record('finance_import.committed', "Financial import {$locked->reference} committed ({$rows->count()} rows)", $locked, $locked->summary + ['source' => $locked->source_approval_reference]);

            return ['batch' => $locked->fresh(), 'replayed' => false];
        }));
    }

    private function reference(): string
    {
        do {
            $ref = Tokens::reference('FIN');
        } while (ImportBatch::where('reference', $ref)->exists());

        return $ref;
    }
}
