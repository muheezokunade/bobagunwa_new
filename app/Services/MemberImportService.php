<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Member;
use App\Models\MembershipNumber;
use App\Models\User;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Normalize;
use App\Support\Settings;
use App\Support\Tokens;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Existing-member import: upload → validated preview → single atomic commit.
 *
 * - Every row is validated and checked for duplicates within the file and
 *   against the register. A preview with any invalid or duplicate row cannot
 *   be committed; the officer corrects the file and uploads it again.
 * - Commit is atomic and idempotent: re-committing returns the original
 *   receipt, and the same file can never be committed twice.
 * - Imported members never receive login credentials. Account activation is a
 *   separate, permissioned step.
 */
class MemberImportService
{
    public const COLUMNS = [
        'full_name' => ['name', 'name_as_provided', 'member_name', 'full_names'],
        'email' => ['email_address', 'e_mail'],
        'phone' => ['phone_number', 'telephone', 'mobile', 'gsm'],
        'date_of_birth' => ['dob', 'birth_date', 'date_birth'],
        'membership_number' => ['member_number', 'membership_no', 'member_no'],
        'joined_on' => ['joined', 'date_joined', 'joined_date'],
        'ijebu_town' => ['town', 'hometown', 'town_of_origin'],
        'city' => ['city_of_residence', 'residence'],
        'occupation' => ['profession'],
        'address' => ['home_address'],
    ];

    public function __construct(private MembershipNumberService $numbers) {}

    public function template(): string
    {
        return implode(',', array_keys(self::COLUMNS))."\n"
            ."Adebayo Example Synthetic,adebayo@example.test,08030000001,1981-03-14,,2015-01-10,Ijebu Ode,Lagos,Engineer,\n";
    }

    public function preview(UploadedFile $file, User $officer): ImportBatch
    {
        $sha = hash_file('sha256', $file->getRealPath());
        $committed = ImportBatch::where('kind', 'members')->where('status', 'committed')->where('file_sha256', $sha)->first();
        if ($committed) {
            throw new DomainRuleException("This exact file was already imported in {$committed->reference}. No records were changed.");
        }
        $parsed = Csv::read($file->getRealPath(), self::COLUMNS, (int) config('boba.uploads.import_max_rows'));
        if (! in_array('full_name', $parsed['headers'], true)) {
            throw new DomainRuleException('The file must have a full_name column. Download the template for the expected headings.');
        }
        if ($parsed['rows'] === []) {
            throw new DomainRuleException('The file contains no member rows.');
        }

        $evaluated = $this->evaluate($parsed['rows']);

        return DB::transaction(function () use ($file, $officer, $sha, $parsed, $evaluated) {
            $counts = array_count_values(array_column($evaluated, 'status')) + ['valid' => 0, 'invalid' => 0, 'duplicate' => 0];
            $batch = ImportBatch::create([
                'reference' => $this->reference(),
                'kind' => 'members',
                'status' => 'previewed',
                'original_filename' => mb_substr($file->getClientOriginalName(), 0, 190),
                'file_sha256' => $sha,
                'row_count' => count($evaluated),
                'valid_count' => $counts['valid'],
                'invalid_count' => $counts['invalid'],
                'duplicate_count' => $counts['duplicate'],
                'summary' => ['unknown_columns' => $parsed['unknown'], 'columns' => $parsed['headers']],
                'created_by' => $officer->id,
            ]);
            foreach (array_chunk($evaluated, 200) as $chunk) {
                ImportRow::insert(array_map(fn ($r) => [
                    'import_batch_id' => $batch->id,
                    'row_number' => $r['row_number'],
                    'raw' => json_encode($r['raw']),
                    'normalized' => json_encode($r['normalized']),
                    'status' => $r['status'],
                    'messages' => json_encode($r['messages']),
                ], $chunk));
            }
            Audit::record('member_import.previewed', "Member import {$batch->reference} previewed: {$batch->row_count} rows, {$batch->valid_count} valid", $batch, ['file_sha256' => $sha]);

            return $batch;
        });
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array{row_number: int, raw: array, normalized: array, status: string, messages: list<string>}>
     */
    public function evaluate(array $rows): array
    {
        $seen = ['email' => [], 'phone' => [], 'number' => [], 'name' => []];
        $out = [];
        $emails = $phones = $numbers = $names = [];
        foreach ($rows as $row) {
            $emails[] = Normalize::email($row['email']);
            $phones[] = Normalize::phone($row['phone']);
            $numbers[] = $row['membership_number'] !== '' ? strtoupper($row['membership_number']) : null;
            $names[] = Normalize::name($row['full_name']);
        }
        $existingEmails = Member::whereIn('email_normalized', array_filter($emails))->pluck('membership_number', 'email_normalized');
        $existingPhones = Member::whereIn('phone_normalized', array_filter($phones))->pluck('membership_number', 'phone_normalized');
        $existingNumbers = MembershipNumber::whereIn('number', array_filter($numbers))->pluck('number', 'number');
        $existingNames = Member::whereIn('name_normalized', array_filter($names))->pluck('membership_number', 'name_normalized');

        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2; // spreadsheet row number, header is row 1
            $errors = [];
            $duplicates = [];
            $n = [
                'full_name' => Normalize::text($row['full_name']),
                'email' => $emails[$i],
                'phone' => $row['phone'] !== '' ? trim($row['phone']) : null,
                'phone_normalized' => $phones[$i],
                'membership_number' => $numbers[$i],
                'ijebu_town' => Normalize::text($row['ijebu_town']),
                'city' => Normalize::text($row['city']),
                'occupation' => Normalize::text($row['occupation']),
                'address' => Normalize::text($row['address']),
                'date_of_birth' => null,
                'joined_on' => null,
            ];
            if (! $n['full_name'] || mb_strlen($n['full_name']) < 3) {
                $errors[] = 'Full name is required.';
            } elseif (mb_strlen($n['full_name']) > 190) {
                $errors[] = 'Full name is too long.';
            }
            if ($n['email'] && ! filter_var($n['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email address is not valid.';
            }
            if ($n['phone'] && ! Normalize::isPlausiblePhone($n['phone_normalized'])) {
                $errors[] = 'Phone number is not valid.';
            }
            if ($n['membership_number'] && ! preg_match('/^[A-Z0-9][A-Z0-9\/-]{0,31}$/', $n['membership_number'])) {
                $errors[] = 'Membership number may only contain letters, digits, “-” and “/”.';
            }
            foreach (['date_of_birth', 'joined_on'] as $field) {
                try {
                    $n[$field] = Csv::date($row[$field])?->toDateString();
                } catch (DomainRuleException $e) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)).': '.$e->getMessage();
                }
            }
            if ($n['date_of_birth'] && $n['date_of_birth'] > now()->toDateString()) {
                $errors[] = 'Date of birth cannot be in the future.';
            }

            foreach (['email' => $n['email'], 'phone' => $n['phone_normalized'], 'number' => $n['membership_number'], 'name' => $names[$i]] as $key => $value) {
                if ($value && isset($seen[$key][$value])) {
                    $duplicates[] = "Same {$key} as row {$seen[$key][$value]} in this file.";
                } elseif ($value) {
                    $seen[$key][$value] = $rowNumber;
                }
            }
            if ($n['email'] && isset($existingEmails[$n['email']])) {
                $duplicates[] = "Email already belongs to member {$existingEmails[$n['email']]}.";
            }
            if ($n['phone_normalized'] && isset($existingPhones[$n['phone_normalized']])) {
                $duplicates[] = "Phone already belongs to member {$existingPhones[$n['phone_normalized']]}.";
            }
            if ($n['membership_number'] && isset($existingNumbers[$n['membership_number']])) {
                $duplicates[] = "Membership number {$n['membership_number']} has already been issued.";
            }
            if ($names[$i] && isset($existingNames[$names[$i]])) {
                $duplicates[] = "Name matches existing member {$existingNames[$names[$i]]}.";
            }

            $out[] = [
                'row_number' => $rowNumber,
                'raw' => $row,
                'normalized' => $n,
                'status' => $errors ? 'invalid' : ($duplicates ? 'duplicate' : 'valid'),
                'messages' => array_merge($errors, $duplicates),
            ];
        }

        return $out;
    }

    /** @return array{batch: ImportBatch, replayed: bool} */
    public function commit(ImportBatch $batch, User $officer): array
    {
        if ($batch->kind !== 'members') {
            throw new DomainRuleException('This is not a member import.');
        }
        Settings::requireApproval('approval.member_import', 'Importing existing members');

        try {
            return DB::transaction(function () use ($batch, $officer) {
                $locked = ImportBatch::lockForUpdate()->findOrFail($batch->id);
                if ($locked->status === 'committed') {
                    return ['batch' => $locked, 'replayed' => true];
                }
                if ($locked->status !== 'previewed') {
                    throw new DomainRuleException('This import was cancelled and cannot be committed.');
                }
                if ($locked->invalid_count > 0 || $locked->duplicate_count > 0) {
                    throw new DomainRuleException('Correct every invalid or duplicate row and upload the file again before importing.');
                }
                $rows = $locked->rows()->get();
                // Re-check against the register as it is now: it may have changed since preview.
                $recheck = $this->evaluate($rows->map(fn ($r) => $r->raw)->all());
                $problems = array_filter($recheck, fn ($r) => $r['status'] !== 'valid');
                if ($problems) {
                    $first = reset($problems);
                    throw new DomainRuleException("The register changed since this preview (row {$first['row_number']}: {$first['messages'][0]}). Upload the file again.");
                }

                foreach ($rows as $row) {
                    $n = $row->normalized;
                    $number = $this->numbers->issue($n['membership_number'], $officer->id);
                    $member = Member::create([
                        'membership_number' => $number,
                        'full_name' => $n['full_name'],
                        'name_normalized' => Normalize::name($n['full_name']),
                        'email' => $n['email'],
                        'email_normalized' => $n['email'],
                        'phone' => $n['phone'],
                        'phone_normalized' => $n['phone_normalized'],
                        'date_of_birth' => $n['date_of_birth'],
                        'joined_on' => $n['joined_on'],
                        'ijebu_town' => $n['ijebu_town'],
                        'city' => $n['city'],
                        'occupation' => $n['occupation'],
                        'address' => $n['address'],
                        'status' => 'active',
                        'origin' => 'import',
                        'import_batch_id' => $locked->id,
                        'created_by' => $officer->id,
                    ]);
                    $row->update(['result_type' => 'Member', 'result_id' => $member->id]);
                }
                $locked->update(['status' => 'committed', 'committed_by' => $officer->id, 'committed_at' => now()]);
                Audit::record('member_import.committed', "Member import {$locked->reference} committed: {$rows->count()} members created, no login accounts", $locked, ['members_created' => $rows->count()]);

                return ['batch' => $locked->fresh(), 'replayed' => false];
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'import_batches_committed_file_unique')) {
                throw new DomainRuleException('This exact file has already been imported. No records were changed.');
            }
            throw new DomainRuleException('A duplicate was created by another officer while importing. No records were changed; upload the file again to see the conflict.');
        }
    }

    public function cancel(ImportBatch $batch, User $officer): void
    {
        DB::transaction(function () use ($batch, $officer) {
            $locked = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== 'previewed') {
                throw new DomainRuleException('Only an unconfirmed preview can be cancelled.');
            }
            $locked->update(['status' => 'cancelled', 'cancelled_by' => $officer->id, 'cancelled_at' => now()]);
            Audit::record('import.cancelled', "Import {$locked->reference} cancelled", $locked);
        });
    }

    private function reference(): string
    {
        do {
            $ref = Tokens::reference('IMP');
        } while (ImportBatch::where('reference', $ref)->exists());

        return $ref;
    }
}
