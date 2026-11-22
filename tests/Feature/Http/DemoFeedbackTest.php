<?php

namespace Tests\Feature\Http;

use App\Models\Leader;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PrivateFile;
use App\Services\UploadService;
use App\Support\Format;
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
        foreach (['under_review', 'approved'] as $step) {
            $this->actingAs($officer)->post(route('admin.applications.decide', $application), ['decision' => $step, 'private_note' => 'Synthetic review'])->assertSessionHasNoErrors();
        }
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
}
