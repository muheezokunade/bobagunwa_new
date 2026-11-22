<?php

namespace Tests\Feature\Http;

use App\Models\Leader;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Payment;
use App\Models\PrivateFile;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\Finance\LedgerQuery;
use App\Services\Finance\PaymentService;
use App\Services\UploadService;
use App\Support\Format;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Changes requested at the 22 Nov 2026 demo and the association's content pack.
 */
class DemoFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_content_loads_as_revisions_publishes_approved_pages_and_is_idempotent(): void
    {
        $this->artisan('boba:content:official', ['--publish' => true])->assertSuccessful();

        $this->get(route('home'))->assertOk()->assertSee('Welcome to the Official Website of Egbe Bobagunwa Okunrin Akile Ijebu')->assertSee('E ku ojumo!')->assertSee('Giwa’s welcome address');
        $this->get(route('about'))->assertOk()->assertSee('Who we are')->assertSee('Regberegbe')->assertSee('upholding the principles of Omoluabi');
        // History stays a draft until the association confirms the facts.
        $this->get(route('history'))->assertOk()->assertDontSee('History on the Awujale');
        $this->assertNull(Page::where('key', 'history')->value('published_revision_id'));
        $this->assertSame(1, Page::where('key', 'history')->sole()->revisions()->count());

        $this->get(route('leadership'))->assertOk()
            ->assertSee('Giwa-in-Council')->assertSee('Bobagunwa Chiefs')->assertSee('Bobagunwa Executives')
            ->assertSee('Gunwa Adebayo Mosuro')->assertSee('Giwa Egbe')->assertSee('2025 – 2027')
            ->assertSee('data-dialog-open="leader-', false)->assertSee('<dialog id="leader-', false);
        $this->assertSame(37, Leader::where('status', 'published')->count());
        $this->assertSame(1, Leader::where('office', 'Akogun Egbe')->count(), 'the duplicated entry is loaded once');
        $this->assertSame(0, Leader::where('office', 'Babalaje Egbe')->count(), 'titles without a holder are not published');

        $revisions = PageRevision::count();
        $this->artisan('boba:content:official', ['--publish' => true])->assertSuccessful();
        $this->assertSame($revisions, PageRevision::count(), 'a second run changes nothing');
        $this->assertSame(37, Leader::count());
    }

    public function test_content_loader_without_publish_creates_drafts_only(): void
    {
        $this->artisan('boba:content:official')->assertSuccessful();
        $this->assertNull(Page::where('key', 'about')->value('published_revision_id'));
        $this->assertSame(0, Leader::where('status', 'published')->count());
        $this->get(route('leadership'))->assertSee('being confirmed');
    }

    public function test_leader_profile_dialog_shows_the_biography(): void
    {
        $leader = Leader::create(['name' => 'Gunwa Synthetic Officer', 'office' => 'Synthetic Office', 'group' => 'council', 'bio' => "Line one of a synthetic biography.\nLine two.", 'status' => 'published']);
        $this->get(route('leadership'))->assertOk()->assertSee('id="leader-'.$leader->id.'"', false)->assertSee('Line one of a synthetic biography.')->assertSee('View profile');
    }

    public function test_date_of_birth_is_entered_as_day_month_name_and_year(): void
    {
        $this->get(route('membership.apply'))->assertOk()
            ->assertSee('name="date_of_birth_day"', false)->assertSee('name="date_of_birth_month"', false)->assertSee('name="date_of_birth_year"', false)
            ->assertSee('<option value="6" >Jun</option>', false);
        $form = ['first_name' => 'Synthetic', 'other_names' => 'Middle', 'last_name' => 'Applicant', 'email' => 'dob.synthetic@example.test', 'phone' => '08035550999',
            'ijebu_town' => 'Ijebu Ode', 'city' => 'Lagos', 'country' => 'Nigeria', 'occupation' => 'Synthetic',
            'declaration' => '1', 'privacy' => '1', 'website' => '', 'form_stamp' => Crypt::encryptString((string) (time() - 30))] + $this->applicationDocuments();

        // 31 February is refused with the normal message.
        $this->post(route('membership.apply.store'), $form + ['date_of_birth_day' => '31', 'date_of_birth_month' => '2', 'date_of_birth_year' => '1981'])
            ->assertSessionHasErrors(['date_of_birth' => 'Enter your date of birth as a valid date.']);

        $this->post(route('membership.apply.store'), $form + ['date_of_birth_day' => '15', 'date_of_birth_month' => '6', 'date_of_birth_year' => '1981'])
            ->assertRedirect(route('membership.receipt'));
        $this->assertSame('1981-06-15', MembershipApplication::sole()->date_of_birth->format('Y-m-d'));
        $this->assertSame('15 Jun 1981', Format::date(MembershipApplication::sole()->date_of_birth));
    }

    private function memberProfileForm(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Synthetic', 'other_names' => 'Profile', 'last_name' => 'Applicant', 'email' => 'profile.synthetic@example.test', 'phone' => '08035551234',
            'date_of_birth_day' => '3', 'date_of_birth_month' => '3', 'date_of_birth_year' => '1981', 'occupation' => 'Synthetic surveyor', 'ijebu_town' => 'Ijebu Igbo',
            'city' => 'Houston', 'country' => 'United States', 'address' => '1 Synthetic Street', 'marital_status' => 'married', 'profile_summary' => 'Synthetic profile summary.',
            'declaration' => '1', 'privacy' => '1', 'website' => '', 'form_stamp' => Crypt::encryptString((string) (time() - 30)),
        ] + $this->applicationDocuments();
    }

    public function test_application_follows_the_member_profile_form_and_stores_documents_privately(): void
    {
        $this->get(route('membership.apply'))->assertOk()->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('Middle name')->assertSee('Profession / trade')->assertSee('Country of residence')->assertSee('Marital status')
            ->assertSee('Birth certificate')->assertSee('Referee letters')->assertSee('Full-length photograph')->assertSee('Data privacy notice')
            ->assertDontSee('Why would you like to join');

        $this->post(route('membership.apply.store'), $this->memberProfileForm())->assertRedirect(route('membership.receipt'));
        $application = MembershipApplication::sole();
        $this->assertSame(['United States', 'married', 'Synthetic profile summary.'], [$application->country, $application->marital_status, $application->profile_summary]);
        $this->assertSame(8, $application->attachments()->count(), 'seven required documents, two referee letters');
        $this->assertSame(2, $application->attachments()->where('kind', 'referee_letter')->count());
        foreach ($application->attachments()->with('file')->get() as $attachment) {
            $path = app(UploadService::class)->privatePath($attachment->file);
            $this->assertFileExists($path);
            $this->assertStringStartsWith(storage_path('app/private'), $path, 'documents are never in the public webroot');
            $this->assertNull($attachment->file->uploaded_by);
        }

        $officer = $this->officer('membership_officer');
        $page = $this->actingAs($officer)->get(route('admin.applications.show', $application))->assertOk()
            ->assertSee('Supporting documents')->assertSee('birth.pdf')->assertSee('Referee letters')->assertSee('United States');
        $attachment = $application->attachments()->where('kind', 'birth_certificate')->sole();
        $this->actingAs($officer)->get(route('admin.applications.attachments.show', [$application, $attachment]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('audit_events', ['action' => 'application.document_viewed']);
        $this->actingAs($this->officer('treasurer'))->get(route('admin.applications.attachments.show', [$application, $attachment]))->assertForbidden();

        // The nightly clean-up keeps documents that belong to an application.
        $this->travel(3)->days();
        app(UploadService::class)->pruneOrphans();
        $this->assertSame(8, PrivateFile::count());

        // Approval carries the profile details to the member record.
        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'under_review', 'private_note' => 'Synthetic review'])->assertSessionHasNoErrors();
        $this->recommend($application->fresh());
        $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'Synthetic review'])->assertSessionHasNoErrors();
        $member = Member::where('membership_application_id', $application->id)->sole();
        $this->assertSame(['United States', 'married', 'Synthetic profile summary.', '1 Synthetic Street'], [$member->country, $member->marital_status, $member->profile_summary, $member->address]);
    }

    public function test_missing_or_unsafe_documents_store_nothing(): void
    {
        $form = $this->memberProfileForm();
        unset($form['attachments']['birth_certificate']);
        $this->post(route('membership.apply.store'), $form)->assertSessionHasErrors(['attachments.birth_certificate' => 'The birth certificate field is required.']);

        $form = $this->memberProfileForm();
        $form['attachments']['lg_certificate'] = UploadedFile::fake()->createWithContent('lg.pdf', "%PDF-1.4\n/OpenAction << /JS (app.alert(1)) >>\n%%EOF");
        $this->post(route('membership.apply.store'), $form)->assertSessionHasErrors('domain');

        $form = $this->memberProfileForm();
        $form['attachments']['passport_photo'] = UploadedFile::fake()->createWithContent('passport.pdf', "%PDF-1.4\n%%EOF");
        $this->post(route('membership.apply.store'), $form)->assertSessionHasErrors('attachments.passport_photo');

        $this->assertSame(0, MembershipApplication::count());
        $this->assertSame(0, PrivateFile::count(), 'files stored before the failure are removed');
    }

    public function test_documents_can_be_made_optional_in_settings(): void
    {
        Settings::set('membership.require_documents', false, null);
        $form = $this->memberProfileForm();
        unset($form['attachments']);
        $this->post(route('membership.apply.store'), $form)->assertRedirect(route('membership.receipt'));
        $this->assertSame(0, MembershipApplication::sole()->attachments()->count());
    }

    public function test_an_oversized_upload_returns_to_the_form_with_a_clear_message(): void
    {
        $this->withServerVariables(['CONTENT_LENGTH' => (string) (1024 * 1024 * 1024)])
            ->post(route('membership.apply.store'), [])
            ->assertRedirect(route('membership.apply', ['too_large' => 1]));
        $this->withServerVariables([])->get(route('membership.apply', ['too_large' => 1]))->assertSee('too large to send together');
    }

    public function test_officer_logins_are_separate_from_member_logins(): void
    {
        $admin = $this->officer('system_admin');
        $member = $this->member([], true);

        // The member's own email cannot become an officer login.
        $this->actingAs($admin)->post(route('admin.access.store'), ['name' => $member->full_name, 'email' => $member->user->email, 'roles' => ['treasurer'], 'reason' => 'Elected treasurer (synthetic)'])
            ->assertSessionHasErrors(['domain' => 'This email address is already used by another account, such as the person’s member portal login. Officer logins are separate: use a different email address for the officer account.']);
        // A member portal account cannot be given officer roles.
        $this->actingAs($admin)->post(route('admin.access.roles', $member->user), ['roles' => ['treasurer'], 'reason' => 'Elected treasurer (synthetic)'])->assertSessionHasErrors('domain');
        $this->assertFalse($member->user->fresh()->roles()->where('is_staff', true)->exists());

        // A separate officer login with another address works and is not linked to the member record.
        $this->actingAs($admin)->post(route('admin.access.store'), ['name' => $member->full_name, 'email' => 'treasurer.office@example.test', 'roles' => ['treasurer'], 'reason' => 'Elected treasurer (synthetic)'])->assertSessionHasNoErrors();
        $officer = User::where('email', 'treasurer.office@example.test')->sole();
        $this->assertNull($officer->member_id);
        $this->assertSame(['treasurer'], $officer->roles()->pluck('key')->all());
        $this->assertSame(['member'], $member->user->fresh()->roles()->pluck('key')->all());
    }

    public function test_two_step_approval_general_secretary_recommends_and_a_different_officer_decides(): void
    {
        [$application] = app(ApplicationService::class)->submit(['first_name' => 'Two', 'last_name' => 'Step', 'email' => 'two.step@example.test', 'phone' => '08035557777', 'date_of_birth' => '1981-01-01', 'ijebu_town' => 'Ijebu Ode', 'city' => 'Lagos', 'occupation' => 'Synthetic']);
        $gs = $this->officer('membership_initiator');
        $chair = $this->officer('membership_officer');

        $this->actingAs($gs)->post(route('admin.applications.decide', $application), ['decision' => 'under_review', 'private_note' => 'Starting review'])->assertSessionHasNoErrors();
        $page = $this->actingAs($gs)->get(route('admin.applications.show', $application))->assertOk();
        $page->assertSee('Recommend approval')->assertDontSee('<option value="approved"', false);
        $this->actingAs($gs)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'x'])->assertSessionHasErrors('domain');

        $this->actingAs($gs)->post(route('admin.applications.decide', $application), ['decision' => 'recommend_approve', 'private_note' => 'Documents and referees checked'])->assertSessionHasNoErrors();
        $this->assertSame(['approve', $gs->id], [$application->fresh()->recommendation, (int) $application->fresh()->recommended_by]);
        $this->assertDatabaseHas('application_events', ['membership_application_id' => $application->id, 'type' => 'recommended']);

        // The chair sees the recommendation and can approve; rejection is not offered against an approval recommendation.
        $this->actingAs($chair)->get(route('admin.applications.show', $application))->assertSee('Recommended for approval')
            ->assertSee('<option value="approved"', false)->assertDontSee('<option value="rejected"', false);

        // An officer holding both rights cannot approve his own recommendation.
        $both = $this->officer(['membership_officer']);
        $this->actingAs($both)->post(route('admin.applications.decide', $application), ['decision' => 'changes_requested', 'private_note' => 'Need referee phone', 'applicant_message' => 'Please add your referees’ phone numbers.'])->assertSessionHasNoErrors();
        $this->assertNull($application->fresh()->recommendation, 'asking for more information clears the recommendation');
        $application->refresh()->update(['status' => 'under_review']);
        $this->actingAs($both)->post(route('admin.applications.decide', $application), ['decision' => 'recommend_approve', 'private_note' => 'Rechecked'])->assertSessionHasNoErrors();
        $this->actingAs($both)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'Self'])
            ->assertSessionHasErrors(['domain' => 'The final decision must be made by a different officer from the one who recommended it.']);
        $this->actingAs($chair)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'Approved by the screening committee chair'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_single_step_approval_when_two_step_is_switched_off(): void
    {
        Settings::set('membership.two_step_approval', false, null);
        [$application] = app(ApplicationService::class)->submit(['first_name' => 'One', 'last_name' => 'Step', 'email' => 'one.step@example.test', 'phone' => '08035558888', 'date_of_birth' => '1981-01-01', 'ijebu_town' => 'Ijebu Ode', 'city' => 'Lagos', 'occupation' => 'Synthetic']);
        $chair = $this->officer('membership_officer');
        $this->actingAs($chair)->post(route('admin.applications.decide', $application), ['decision' => 'under_review', 'private_note' => 'Review'])->assertSessionHasNoErrors();
        $this->actingAs($chair)->post(route('admin.applications.decide', $application), ['decision' => 'approved', 'private_note' => 'Approved'])->assertSessionHasNoErrors();
        // Final-decision rights are still required.
        [$other] = app(ApplicationService::class)->submit(['first_name' => 'Other', 'last_name' => 'Step', 'email' => 'other.step@example.test', 'phone' => '08035559999', 'date_of_birth' => '1981-01-01', 'ijebu_town' => 'Ijebu Ode', 'city' => 'Lagos', 'occupation' => 'Synthetic']);
        $gs = $this->officer('membership_initiator');
        $this->actingAs($gs)->post(route('admin.applications.decide', $other), ['decision' => 'under_review', 'private_note' => 'Review'])->assertSessionHasNoErrors();
        $this->actingAs($gs)->post(route('admin.applications.decide', $other), ['decision' => 'approved', 'private_note' => 'x'])->assertSessionHasErrors('domain');
    }

    public function test_member_reports_a_part_payment_with_a_receipt_and_a_verifier_confirms_it(): void
    {
        $member = $this->member([], true);
        $assessment = $this->assess([$member], '1000');
        $obligation = $this->obligationOf($member, $assessment);
        $user = $member->user;

        $this->actingAs($user)->get(route('portal.finances'))->assertOk()->assertSee('Report a payment')->assertSee('Report payment');
        $this->actingAs($user)->get(route('portal.payments.create', ['obligation' => $obligation->id]))->assertOk()
            ->assertSee('What is this payment for?')->assertSee('value="1000.00"', false)->assertSee('Name on the paying account');

        $form = ['obligation_id' => $obligation->id, 'amount' => '500', 'method' => 'bank_transfer', 'received_on' => now()->subDay()->toDateString(),
            'external_reference' => 'TRF-SYN-500', 'payer_name' => 'Synthetic Brother', 'confirm' => '1', 'idempotency_key' => $this->key(),
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 200, 300)];
        // The receipt is required; a future date is refused.
        $this->actingAs($user)->post(route('portal.payments.store'), array_diff_key($form, ['receipt' => 1]))->assertSessionHasErrors('receipt');
        $this->actingAs($user)->post(route('portal.payments.store'), ['received_on' => now()->addDay()->toDateString()] + $form)->assertSessionHasErrors('received_on');

        $this->actingAs($user)->post(route('portal.payments.store'), $form)->assertRedirect(route('portal.finances'));
        $payment = Payment::sole();
        $this->assertSame(['pending', 50000, true, $user->id, $obligation->id, 'Synthetic Brother'], [$payment->status, $payment->amount_kobo, $payment->submitted_by_member, $payment->recorded_by, $payment->intended_obligation_id, $payment->payer_name]);
        $this->assertNotNull($payment->evidence_file_id);
        $this->assertSame(0, app(LedgerQuery::class)->memberSummary($member->id)['allocated'], 'a pending payment does not count');

        // The officer sees who reported it and what for; the payment verifier confirms; the treasurer applies it.
        $verifier = $this->officer('payment_verifier');
        $this->actingAs($verifier)->get(route('admin.finance.payments.show', $payment))->assertOk()->assertSee('Reported by the member')->assertSee('Member says it is for');
        $this->actingAs($verifier)->post(route('admin.finance.payments.verify', $payment), ['confirm' => '1', 'note' => 'Matched the bank alert'])->assertSessionHasNoErrors();
        $this->actingAs($this->officer('treasurer'))->post(route('admin.finance.payments.allocate', $payment), ['amounts' => [$obligation->id => '500'], 'idempotency_key' => $this->key()])->assertSessionHasNoErrors();

        // Part-payment: ₦500 of ₦1,000 leaves ₦500 outstanding.
        $open = app(LedgerQuery::class)->openObligations($member->id)->firstWhere('id', $obligation->id);
        $this->assertSame([50000, 50000], [(int) $open->allocated, (int) $open->outstanding]);
        $this->actingAs($user)->get(route('portal.finances'))->assertSee('₦500.00');
    }

    public function test_member_cannot_report_against_another_members_levy_or_without_an_account(): void
    {
        $member = $this->member([], true);
        $other = $this->member([], true);
        $assessment = $this->assess([$other], '1000');
        $form = ['obligation_id' => $this->obligationOf($other, $assessment)->id, 'amount' => '1000', 'method' => 'cash', 'received_on' => now()->toDateString(),
            'confirm' => '1', 'idempotency_key' => $this->key(), 'receipt' => UploadedFile::fake()->image('r.png', 50, 50)];
        $this->actingAs($member->user)->post(route('portal.payments.store'), $form)->assertSessionHasErrors('domain');
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, PrivateFile::count(), 'the receipt is removed when the report is refused');
        // Officers (no member record) cannot use the member route.
        $this->actingAs($this->officer('treasurer'))->get(route('portal.payments.create'))->assertRedirect();
        $this->assertSame(0, Payment::count());
    }

    public function test_report_shows_who_has_paid_a_levy_and_exports_csv(): void
    {
        [$paid, $part, $none] = [$this->member(), $this->member(), $this->member()];
        $assessment = $this->assess([$paid, $part, $none], '1000');
        $treasurer = $this->officer('treasurer');
        foreach ([[$paid, '1000'], [$part, '400']] as [$m, $naira]) {
            $payment = $this->verifiedPayment($m, $naira);
            app(PaymentService::class)->allocate($payment, [$this->obligationOf($m, $assessment)->id => Money::parse($naira)], $treasurer, $this->key());
        }
        $categoryId = $assessment->fee_category_id;

        $this->actingAs($treasurer)->get(route('admin.finance.reports.index', ['category' => $categoryId]))->assertOk()
            ->assertSee('Who has paid a due or levy')->assertSee('3 member(s) assessed')->assertSee('1 paid, 1 part-paid, 1 not paid')
            ->assertSee('Part-paid')->assertSee('Not paid');
        $csv = $this->actingAs($treasurer)->get(route('admin.finance.reports.export', ['report' => 'levy-payers', 'category' => $categoryId]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Payment status', $csv);
        $this->assertStringContainsString($part->membership_number, $csv);
        $this->assertStringContainsString('1000.00,400.00,600.00,Part-paid', $csv);
        $this->assertStringContainsString('Not paid', $csv);
        $list = $this->actingAs($treasurer)->get(route('admin.finance.reports.export', ['report' => 'category-list']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Synthetic dues', $list);
        // The membership officer has no finance access.
        $this->actingAs($this->officer('membership_officer'))->get(route('admin.finance.reports.index', ['category' => $categoryId]))->assertForbidden();
    }
}
