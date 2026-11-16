<?php

namespace Tests;

use App\Models\Assessment;
use App\Models\FeeCategory;
use App\Models\Member;
use App\Models\MemberObligation;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\AssessmentService;
use App\Services\Finance\PaymentService;
use App\Services\MembershipNumberService;
use App\Support\Idempotency;
use App\Support\Normalize;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * All database tests run against MariaDB 11.4 (bobagunwa_test) so triggers,
 * generated columns, CHECK constraints and locking behave as in production.
 * Every fixture is synthetic.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Settings::flush();
    }

    protected function officer(string|array $roles, array $attributes = []): User
    {
        static $n = 0;
        $n++;
        $user = User::create($attributes + ['name' => "Synthetic Officer {$n}", 'email' => "officer{$n}-".uniqid().'@example.test', 'status' => 'active', 'activated_at' => now()]);
        $user->forceFill(['password' => 'synthetic-password-123'])->save();
        $user->roles()->attach(Role::whereIn('key', (array) $roles)->pluck('id'));

        return $user->fresh();
    }

    protected function member(array $attributes = [], bool $withAccount = false): Member
    {
        static $n = 0;
        $n++;
        $member = DB::transaction(function () use ($attributes, $n) {
            $number = app(MembershipNumberService::class)->issue(null, null);
            $name = $attributes['full_name'] ?? "Synthetic Member {$n}";

            return Member::create($attributes + [
                'membership_number' => $number, 'full_name' => $name, 'name_normalized' => Normalize::name($name),
                'email' => "member{$n}-".uniqid().'@example.test', 'status' => 'active', 'origin' => 'manual',
            ]);
        });
        $member->email_normalized = Normalize::email($member->email);
        $member->save();
        if ($withAccount) {
            $user = User::create(['name' => $member->full_name, 'email' => $member->email_normalized, 'member_id' => $member->id, 'status' => 'active', 'activated_at' => now()]);
            $user->forceFill(['password' => 'synthetic-password-123'])->save();
            $user->roles()->attach(Role::where('key', 'member')->value('id'));
        }

        return $member->fresh();
    }

    protected function category(): FeeCategory
    {
        return FeeCategory::create(['code' => 'T'.strtoupper(substr(uniqid(), -6)), 'name' => 'Synthetic dues', 'kind' => 'dues', 'is_active' => true]);
    }

    /** Post a synthetic assessment of $naira to the given members. */
    protected function assess(array $members, string $naira, ?User $treasurer = null): Assessment
    {
        $treasurer ??= $this->officer('treasurer');
        $service = app(AssessmentService::class);
        $draft = $service->createDraft(['fee_category_id' => $this->category()->id, 'title' => 'Synthetic assessment', 'amount' => $naira, 'effective_on' => now()->subDays(10)->toDateString(), 'target_mode' => 'selected', 'member_ids' => array_map(fn ($m) => $m->id, $members)], $treasurer);
        [$posted] = $service->post($draft, $treasurer, Idempotency::newKey(), count($members));

        return $posted;
    }

    protected function verifiedPayment(Member $member, string $naira, ?User $treasurer = null, ?User $verifier = null): Payment
    {
        $treasurer ??= $this->officer('treasurer');
        $verifier ??= $this->officer('payment_verifier');
        [$payment] = app(PaymentService::class)->record(['member_id' => $member->id, 'amount' => $naira, 'method' => 'bank_transfer', 'received_on' => now()->subDays(5)->toDateString(), 'confirm_possible_duplicate' => true], $treasurer, Idempotency::newKey());

        return app(PaymentService::class)->verify($payment, $verifier, 'Synthetic verification');
    }

    protected function obligationOf(Member $member, Assessment $assessment): MemberObligation
    {
        return MemberObligation::where('member_id', $member->id)->where('assessment_id', $assessment->id)->firstOrFail();
    }

    protected function key(): string
    {
        return Idempotency::newKey();
    }
}
