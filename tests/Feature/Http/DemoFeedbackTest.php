<?php

namespace Tests\Feature\Http;

use App\Models\Leader;
use App\Models\MembershipApplication;
use App\Models\Page;
use App\Models\PageRevision;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('<option value="6" >June</option>', false);
        $form = ['first_name' => 'Synthetic', 'last_name' => 'Applicant', 'email' => 'dob.synthetic@example.test', 'phone' => '08035550999',
            'ijebu_town' => 'Ijebu Ode', 'city' => 'Lagos', 'occupation' => 'Synthetic', 'reason' => 'A synthetic reason long enough to pass validation.',
            'declaration' => '1', 'privacy' => '1', 'website' => '', 'form_stamp' => Crypt::encryptString((string) (time() - 30))];

        // 31 February is refused with the normal message.
        $this->post(route('membership.apply.store'), $form + ['date_of_birth_day' => '31', 'date_of_birth_month' => '2', 'date_of_birth_year' => '1981'])
            ->assertSessionHasErrors(['date_of_birth' => 'Enter your date of birth as a valid date.']);

        $this->post(route('membership.apply.store'), $form + ['date_of_birth_day' => '15', 'date_of_birth_month' => '6', 'date_of_birth_year' => '1981'])
            ->assertRedirect(route('membership.receipt'));
        $this->assertSame('1981-06-15', MembershipApplication::sole()->date_of_birth->format('Y-m-d'));
        $this->assertSame('15 June 1981', Format::date(MembershipApplication::sole()->date_of_birth));
    }
}
