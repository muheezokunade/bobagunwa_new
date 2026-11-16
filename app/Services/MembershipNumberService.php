<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\MembershipNumber;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Issues permanent, never-reused membership numbers.
 *
 * Must run inside the caller's transaction. The sequence row is locked
 * FOR UPDATE so concurrent approvals cannot receive the same value, and the
 * unique key on membership_numbers.number is the final guarantee.
 */
class MembershipNumberService
{
    public function issue(?string $providedNumber, ?int $issuedBy): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Membership numbers must be issued inside a transaction.');
        }
        Settings::requireApproval('approval.membership_number_format', 'Issuing membership numbers');

        if ($providedNumber !== null) {
            $number = strtoupper(trim($providedNumber));
            if (! preg_match('/^[A-Z0-9][A-Z0-9\/-]{0,31}$/', $number)) {
                throw new DomainRuleException("Membership number {$providedNumber} has an invalid format.");
            }
            if (MembershipNumber::where('number', $number)->exists()) {
                throw new DomainRuleException("Membership number {$number} has already been issued.");
            }
            MembershipNumber::create(['number' => $number, 'source' => 'provided', 'issued_by' => $issuedBy, 'issued_at' => now()]);

            return $number;
        }

        $sequence = DB::table('membership_number_sequences')->where('name', 'members')->lockForUpdate()->first();
        $prefix = (string) Settings::get('membership.number_prefix');
        $width = max(3, min(10, (int) Settings::get('membership.number_width')));
        $value = (int) $sequence->next_value;

        // Skip any value already taken (for example by an imported legacy number).
        do {
            $number = $prefix.str_pad((string) $value, $width, '0', STR_PAD_LEFT);
            $taken = MembershipNumber::where('number', $number)->exists();
            $value++;
        } while ($taken);

        MembershipNumber::create(['number' => $number, 'source' => 'sequence', 'sequence_value' => $value - 1, 'issued_by' => $issuedBy, 'issued_at' => now()]);
        DB::table('membership_number_sequences')->where('name', 'members')->update(['next_value' => $value, 'updated_at' => now()]);

        return $number;
    }

    public function preview(): string
    {
        $next = (int) DB::table('membership_number_sequences')->where('name', 'members')->value('next_value');

        return Settings::get('membership.number_prefix').str_pad((string) $next, (int) Settings::get('membership.number_width'), '0', STR_PAD_LEFT);
    }
}
