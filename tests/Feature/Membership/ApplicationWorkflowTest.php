<?php

namespace Tests\Feature\Membership;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Ọlá', 'last_name' => 'Synthetic', 'other_names' => 'Adébáyọ̀',
            'email' => 'ola.synthetic@example.test', 'phone' => '0803 555 0101', 'date_of_birth' => '1981-06-15',
            'ijebu_town' => 'Ijebu Ode', 'city' => 'Lagos', 'occupation' => 'Synthetic engineer',
            'reason' => 'I would like to join my age group in the association (synthetic).',
            'declaration' => '1', 'privacy' => '1', 'website' => '', 'form_stamp' => Crypt::encryptString((string) (time() - 30)),
        ];
    }

    private function submit(array $overrides = []): array
    {
        $response = $this->post(route('membership.apply.store'), $this->form($overrides));
        $response->assertRedirect(route('membership.receipt'));
        $receipt = session('application_receipt');
        $this->get(route('membership.receipt'))->assertOk()->assertSee($receipt['reference'])->assertSee($receipt['token']);

        return $receipt;
    }

    public function test_public_submission_creates_no_member_or_account_and_tracks_privately(): void
    {
        $receipt = $this->submit();
        $application = MembershipApplication::where('reference', $receipt['reference'])->firstOrFail();
        $this->assertSame('eligible', $application->eligibility_status);
        $this->assertSame('submitted', $application->status);
        $this->assertSame('Ọlá', $application->first_name);
        $this->assertSame(0, Member::count());
        $this->assertSame(0, User::count());
        $this->assertNotSame($receipt['token'], $application->status_token_hash);

        $this->post(route('membership.status.lookup'), ['reference' => $receipt['reference'], 'code' => $receipt['token']])
            ->assertOk()->assertSee('Received')->assertDontSee('ola.synthetic@example.test');
        $this->post(route('membership.status.lookup'), ['reference' => $receipt['reference'], 'code' => $receipt['token'].'x'])
            ->assertOk()->assertSee('could not find');
        $this->post(route('membership.status.lookup'), ['reference' => 'BOB-APP-2026-AAAAAA', 'code' => $receipt['token']])
            ->assertOk()->assertSee('could not find');
    }

    public function test_out_of_range_birth_date_is_flagged_not_rejected(): void
    {
        foreach (['1979-12-31', '1983-01-01'] as $i => $dob) {
            $receipt = $this->submit(['date_of_birth' => $dob, 'email' => "edge{$i}@example.test", 'phone' => "0803555020{$i}"]);
            $application = MembershipApplication::where('reference', $receipt['reference'])->first();
            $this->assertSame('flagged', $application->eligibility_status);
            $this->assertSame('birth_date_outside_range', $application->eligibility_flags[0]['code']);
        }
        foreach (['1980-01-01', '1982-12-31'] as $i => $dob) {
            $receipt = $this->submit(['date_of_birth' => $dob, 'email' => "inrange{$i}@example.test", 'phone' => "0803555030{$i}"]);
            $this->assertSame('eligible', MembershipApplication::where('reference', $receipt['reference'])->value('eligibility_status'));
        }
    }

    public function test_validation_duplicates_and_anti_automation(): void
    {
        $this->post(route('membership.apply.store'), $this->form(['declaration' => null, 'date_of_birth' => '2099-01-01', 'email' => 'not-an-email']))
            ->assertSessionHasErrors(['declaration', 'date_of_birth', 'email']);
        $this->post(route('membership.apply.store'), $this->form(['date_of_birth' => '1981-02-30']))->assertSessionHasErrors('date_of_birth');
        $this->submit();
        $this->post(route('membership.apply.store'), $this->form(['email' => 'OLA.Synthetic@example.test']))
            ->assertSessionHasErrors('domain');
        $this->post(route('membership.apply.store'), $this->form(['email' => 'other@example.test', 'phone' => '+234 803 555 0101']))
            ->assertSessionHasErrors('domain');
        $this->post(route('membership.apply.store'), $this->form(['email' => 'bot@example.test', 'phone' => '08000000000', 'website' => 'http://spam.example']))
            ->assertSessionHasErrors('domain');
        $this->post(route('membership.apply.store'), $this->form(['email' => 'fast@example.test', 'phone' => '08000000001', 'form_stamp' => Crypt::encryptString((string) time())]))
            ->assertSessionHasErrors('domain');
        $this->assertSame(1, MembershipApplication::count());
    }

    public function test_officer_review_approval_creates_exactly_one_member_and_rejects_repeat(): void
    {
        $receipt = $this->submit();
        $application = MembershipApplication::where('reference', $receipt['reference'])->first();
        $officer = $this->officer('membership_officer');

        // Other roles are denied at the route.
        foreach (['treasurer', 'system_admin', 'secretariat'] as $role) {
            $this->actingAs($this->officer($role))->get(route('admin.applications.show', $application))->assertForbidden();
            $this->actingAs($this->officer($role))->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'x'])->assertForbidden();
        }
        $this->actingAs($officer)->get(route('admin.applications.show', $application))->assertOk()->assertSee($application->reference);

        // Cannot approve straight from "submitted".
        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'Too soon'])->assertSessionHasErrors('domain');
        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'under_review', 'private_note' => 'Starting review'])->assertSessionHasNoErrors();
        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'changes_requested', 'private_note' => 'Need town', 'applicant_message' => 'Please confirm your Ijebu town of origin.'])->assertSessionHasNoErrors();

        // Applicant replies privately.
        $this->post(route('membership.status.respond'), ['reference' => $receipt['reference'], 'code' => $receipt['token'], 'response' => 'My town is Ijebu Ode.'])->assertOk()->assertSee('passed to the reviewing officers');
        $this->assertSame('under_review', $application->fresh()->status);
        $this->post(route('membership.status.lookup'), ['reference' => $receipt['reference'], 'code' => $receipt['token']])->assertDontSee('Need town');

        $response = $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'Meets criteria', 'applicant_message' => 'Welcome.']);
        $response->assertSessionHas('one_time_link');
        $member = Member::where('membership_application_id', $application->id)->sole();
        $this->assertMatchesRegularExpression('/^BOB-\d{6}$/', $member->membership_number);
        $this->assertSame('pending_activation', $member->user->status);
        $this->assertNull($member->user->password);
        $this->assertStringNotContainsString(session('one_time_link')['url'], OutboundMessage::pluck('body')->implode("\n"));
        $this->assertStringNotContainsString(explode('/', session('one_time_link')['url'])[5] ?? 'none', \App\Models\AuditEvent::get()->toJson());

        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'Again'])->assertSessionHasErrors('domain');
        $this->assertSame(1, Member::count());
        $this->assertSame(3, $application->events()->where('actor_user_id', $officer->id)->where('type', 'status_changed')->count());
    }

    public function test_rejection_requires_applicant_message_and_is_final(): void
    {
        $receipt = $this->submit();
        $application = MembershipApplication::where('reference', $receipt['reference'])->first();
        $officer = $this->officer('membership_officer');
        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'under_review', 'private_note' => 'Review']);
        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'rejected', 'private_note' => 'Not eligible'])->assertSessionHasErrors('domain');
        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'rejected', 'private_note' => 'Not eligible', 'applicant_message' => 'We are unable to approve.'])->assertSessionHasNoErrors();
        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'under_review', 'private_note' => 'Reopen'])->assertSessionHasErrors('domain');
        $this->assertSame(0, Member::count());
        // A rejected applicant may apply again with the same details.
        $this->submit();
    }
}
