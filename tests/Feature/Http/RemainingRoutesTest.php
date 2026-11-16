<?php

namespace Tests\Feature\Http;

use App\Models\Document;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Gallery;
use App\Models\PrivateFile;
use App\Services\UploadService;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HTTP tests for the routes that had no direct test: public pages, member
 * self-service, exports and templates.
 */
class RemainingRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_information_pages_render_and_hide_drafts(): void
    {
        foreach (['about', 'membership', 'activities', 'gallery.index', 'privacy', 'terms', 'membership.apply', 'membership.status', 'password.request'] as $name) {
            $this->get(route($name))->assertOk()->assertHeader('Content-Security-Policy');
        }
        $this->get(route('membership'))->assertSee('1980')->assertSee('1982');
        $this->get(route('password.reset.form'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        Gallery::create(['slug' => 'draft-g', 'title' => 'Draft gallery', 'status' => 'draft']);
        Gallery::create(['slug' => 'live-g', 'title' => 'Live gallery', 'status' => 'published']);
        $this->get(route('gallery.show', 'draft-g'))->assertNotFound();
        $this->get(route('gallery.show', 'live-g'))->assertOk()->assertSee('Live gallery');
        $this->get(route('gallery.index'))->assertSee('Live gallery')->assertDontSee('Draft gallery');
    }

    public function test_member_profile_update_is_limited_and_versioned(): void
    {
        $member = $this->member([], true);
        $user = $member->user;
        $this->actingAs($user)->get(route('portal.profile'))->assertOk()->assertSee($member->membership_number);
        $this->actingAs($user)->post(route('portal.profile.update'), ['version' => $member->version, 'phone' => 'not a phone!'])->assertSessionHasErrors('phone');
        $this->actingAs($user)->post(route('portal.profile.update'), ['version' => $member->version, 'city' => 'Abeokuta', 'full_name' => 'Hijacked Name', 'membership_number' => 'X'])->assertSessionHasNoErrors();
        $fresh = $member->fresh();
        $this->assertSame('Abeokuta', $fresh->city);
        $this->assertSame([$member->full_name, $member->membership_number], [$fresh->full_name, $fresh->membership_number], 'Protected fields cannot be changed by the member');
        $this->actingAs($user)->post(route('portal.profile.update'), ['version' => $member->version, 'city' => 'Stale'])->assertSessionHasErrors('domain');
        $this->assertDatabaseHas('audit_events', ['action' => 'member.profile_updated', 'subject_id' => $member->id]);
        $this->actingAs($this->officer('treasurer'))->get(route('portal.profile'))->assertRedirect(route('admin.dashboard')); // officers without a member record are sent to administration
    }

    public function test_member_rsvp_capacity_and_unpublished_events(): void
    {
        $alice = $this->member([], true);
        $bob = $this->member([], true);
        $event = Event::create(['slug' => 'rsvp-e', 'title' => 'Members meeting', 'starts_at' => now()->addWeek(), 'audience' => 'members', 'status' => 'published', 'rsvp_enabled' => true, 'capacity' => 2]);
        $draft = Event::create(['slug' => 'rsvp-d', 'title' => 'Draft meeting', 'starts_at' => now()->addWeek(), 'audience' => 'members', 'status' => 'draft', 'rsvp_enabled' => true]);
        $this->actingAs($alice->user)->get(route('portal.events'))->assertOk()->assertSee('Members meeting')->assertDontSee('Draft meeting');
        $this->actingAs($alice->user)->get(route('portal.events.show', $event))->assertOk();
        $this->actingAs($alice->user)->get(route('portal.events.show', $draft))->assertNotFound();
        $this->actingAs($alice->user)->post(route('portal.events.rsvp', $draft), ['response' => 'attending'])->assertNotFound();
        $this->actingAs($alice->user)->post(route('portal.events.rsvp', $event), ['response' => 'yes'])->assertSessionHasErrors('response');
        $this->actingAs($alice->user)->post(route('portal.events.rsvp', $event), ['response' => 'attending', 'guests' => 1])->assertSessionHasNoErrors();
        $this->actingAs($alice->user)->post(route('portal.events.rsvp', $event), ['response' => 'attending', 'guests' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, EventRsvp::where('event_id', $event->id)->count(), 'One response per member');
        $this->actingAs($bob->user)->post(route('portal.events.rsvp', $event), ['response' => 'attending'])->assertSessionHasErrors('domain');
        $this->assertSame(1, EventRsvp::where('event_id', $event->id)->count());
    }

    public function test_member_documents_are_private_and_published_only(): void
    {
        $officer = $this->officer('secretariat');
        $pdf = UploadedFile::fake()->createWithContent('rules.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
        $file = app(UploadService::class)->storePrivate($pdf, 'document', $officer);
        $published = Document::create(['title' => 'Constitution draft', 'private_file_id' => $file->id, 'status' => 'published', 'created_by' => $officer->id]);
        $draft = Document::create(['title' => 'Unreleased minutes', 'private_file_id' => $file->id, 'status' => 'draft', 'created_by' => $officer->id]);
        $member = $this->member([], true);
        $this->actingAs($member->user)->get(route('portal.documents'))->assertOk()->assertSee('Constitution draft')->assertDontSee('Unreleased minutes');
        $this->actingAs($member->user)->get(route('portal.documents.download', $published))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($member->user)->get(route('portal.documents.download', $draft))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->get(route('portal.documents.download', $published))->assertRedirect(route('login'));
        $this->assertStringStartsWith(storage_path('app/private'), app(UploadService::class)->privatePath(PrivateFile::find($file->id)));
        @unlink(app(UploadService::class)->privatePath($file));
    }

    public function test_member_can_end_other_sessions(): void
    {
        $member = $this->member([], true);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $member->user->id, 'payload' => 'x', 'last_activity' => time()]);
        $this->actingAs($member->user)->get(route('portal.security'))->assertOk();
        $this->actingAs($member->user)->post(route('portal.security.sessions.end'))->assertRedirect(route('portal.security'));
        $this->assertSame(0, DB::table('sessions')->where('id', 'other-device')->count());
        $this->assertDatabaseHas('security_events', ['event' => 'sessions.ended_by_user', 'user_id' => $member->user->id]);
    }

    public function test_member_statement_csv_is_own_records_only(): void
    {
        $alice = $this->member([], true);
        $bob = $this->member([], true);
        $this->assess([$alice], '12345');
        $this->assess([$bob], '999');
        $csv = $this->actingAs($alice->user)->get(route('portal.statement.csv', ['from' => now()->subYear()->toDateString(), 'to' => now()->toDateString()]))->assertOk()->streamedContent();
        $this->assertStringContainsString('12345.00', $csv);
        $this->assertStringNotContainsString(',999.00', $csv);
        $this->actingAs($alice->user)->get(route('portal.statement.csv', ['from' => '2026-02-01', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
    }

    public function test_officer_exports_templates_and_forms(): void
    {
        $m = $this->member();
        $this->assess([$m], '5000');
        $treasurer = $this->officer('treasurer');
        foreach (['admin.finance.categories.index', 'admin.finance.assessments.create', 'admin.finance.payments.create'] as $name) {
            $this->actingAs($treasurer)->get(route($name))->assertOk();
        }
        $statement = $this->actingAs($treasurer)->get(route('admin.finance.members.statement.csv', $m))->assertOk()->streamedContent();
        $this->assertStringContainsString('5000.00', $statement);
        $this->actingAs($treasurer)->get(route('admin.finance.imports.template', 'finance_transactions'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->actingAs($treasurer)->get(route('admin.finance.imports.template', 'nonsense'))->assertNotFound();
        $secretariat = $this->officer('secretariat');
        $this->actingAs($secretariat)->get(route('admin.imports.members.template'))->assertOk()->assertSee('full_name,email,phone,date_of_birth');
        $this->actingAs($secretariat)->get(route('admin.finance.members.statement.csv', $m))->assertForbidden();
        $admin = $this->officer('system_admin');
        $this->actingAs($admin)->get(route('admin.finance.members.statement.csv', $m))->assertForbidden();
        $audit = $this->actingAs($admin)->get(route('admin.audit.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('assessment.posted', $audit);
        $this->assertDatabaseHas('audit_events', ['action' => 'audit.exported']);
        $this->actingAs($treasurer)->get(route('admin.audit.export'))->assertForbidden();
    }

    public function test_recovery_code_regeneration_requires_current_code(): void
    {
        $officer = $this->officer('treasurer');
        $this->actingAs($officer)->post(route('admin.account.two-factor.recovery'), ['code' => '123456'])->assertSessionHasErrors('domain');
        $this->actingAs($officer)->post(route('admin.account.two-factor.start'));
        $secret = session('two_factor.pending_secret');
        $this->actingAs($officer)->post(route('admin.account.two-factor.confirm'), ['code' => Totp::code($secret, Totp::currentStep())]);
        $old = session('recovery_codes');
        $this->actingAs($officer)->post(route('admin.account.two-factor.recovery'), ['code' => '000000'])->assertSessionHasErrors('domain');
        $this->actingAs($officer)->post(route('admin.account.two-factor.recovery'), ['code' => Totp::code($secret, Totp::currentStep() + 1)])->assertSessionHas('recovery_codes');
        $new = session('recovery_codes');
        $this->assertCount(8, $new);
        $this->assertEmpty(array_intersect($old, $new));
        $this->assertDatabaseHas('security_events', ['event' => 'two_factor.recovery_codes_regenerated', 'user_id' => $officer->id]);
    }
}
