<?php

namespace Tests\Feature\Http;

use App\Models\ApplicationEvent;
use App\Models\BackupRun;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\EnquiryNote;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Media;
use App\Models\MembershipApplication;
use App\Models\OutboundMessage;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Post;
use App\Models\PrivateFile;
use App\Models\RecoveryRequest;
use App\Models\SecurityEvent;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminViewsRenderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_admin_pages_render(): void
    {
        $admin = $this->officer(['system_admin', 'membership_officer', 'secretariat', 'treasurer', 'executive']);
        $other = $this->officer(['payment_verifier']);
        $member = $this->member(['full_name' => 'Synthetic Dup'], true);
        $dup = $this->member(['full_name' => 'Synthetic Dup']);
        $incomplete = $this->member(['email' => null]);
        $this->actingAs($admin);
        Audit::record('member.corrected', 'Record corrected', $member, ['changes' => ['city' => ['from' => null, 'to' => 'Lagos']], 'reason' => 'Synthetic reason']);
        Audit::record('member.status_changed', 'status', $member, ['from' => 'active', 'to' => 'suspended', 'reason' => 'Synthetic']);
        SecurityEvent::create(['user_id' => $other->id, 'event' => 'login.failed', 'context' => ['why' => 'test <script>']]);

        $app = MembershipApplication::create([
            'reference' => 'APP-TEST-1', 'status_token_hash' => str_repeat('a', 64), 'status_token_expires_at' => now()->addDays(90),
            'first_name' => 'Ade', 'last_name' => 'Synthetic', 'email' => $member->email, 'email_normalized' => $member->email_normalized,
            'phone' => '08030000000', 'phone_normalized' => '2348030000000', 'date_of_birth' => '1981-01-01', 'ijebu_town' => 'Ijebu Ode',
            'city' => 'Lagos', 'occupation' => 'Engineer', 'reason' => "Line one\nLine <b>two</b>", 'eligibility_status' => 'flagged',
            'eligibility_flags' => [['code' => 'dob', 'message' => 'Outside range']], 'screening_version' => 'v1', 'privacy_notice_version' => 'v1',
            'declaration_accepted_at' => now(), 'privacy_accepted_at' => now(), 'status' => 'under_review',
        ]);
        ApplicationEvent::create(['membership_application_id' => $app->id, 'type' => 'submitted', 'to_status' => 'submitted', 'applicant_message' => 'Received']);
        ApplicationEvent::create(['membership_application_id' => $app->id, 'type' => 'status_changed', 'from_status' => 'submitted', 'to_status' => 'under_review', 'actor_user_id' => $admin->id, 'private_note' => 'Note']);
        $msg = OutboundMessage::create(['purpose' => 'application_update', 'related_type' => 'MembershipApplication', 'related_id' => $app->id, 'recipient_name' => 'Ade', 'recipient_email' => 'a@example.test', 'subject' => 'Hello', 'body' => "Body\nline", 'status' => 'pending']);

        $batch = ImportBatch::create(['reference' => 'IMP-1', 'kind' => 'members', 'status' => 'previewed', 'original_filename' => 'x.csv', 'file_sha256' => str_repeat('b', 64), 'row_count' => 2, 'valid_count' => 1, 'invalid_count' => 1, 'summary' => ['unknown_columns' => ['foo'], 'columns' => ['full_name']], 'created_by' => $admin->id]);
        ImportRow::create(['import_batch_id' => $batch->id, 'row_number' => 2, 'raw' => ['full_name' => 'A'], 'normalized' => ['full_name' => 'A', 'email' => null, 'phone' => null, 'membership_number' => null], 'status' => 'valid', 'messages' => []]);
        ImportRow::create(['import_batch_id' => $batch->id, 'row_number' => 3, 'raw' => ['full_name' => ''], 'normalized' => ['full_name' => '', 'email' => null, 'phone' => null, 'membership_number' => null], 'status' => 'invalid', 'messages' => ['full_name is required.']]);
        $clean = ImportBatch::create(['reference' => 'IMP-2', 'kind' => 'members', 'status' => 'previewed', 'original_filename' => 'y.csv', 'file_sha256' => str_repeat('c', 64), 'row_count' => 0, 'created_by' => $admin->id]);
        $fin = ImportBatch::create(['reference' => 'IMP-3', 'kind' => 'finance_transactions', 'status' => 'previewed', 'original_filename' => 'f.csv', 'file_sha256' => str_repeat('d', 64), 'row_count' => 1, 'valid_count' => 1, 'summary' => ['obligations_kobo' => 1000000, 'payments_kobo' => 0, 'members' => 1], 'source_approval_reference' => 'MIN-1', 'created_by' => $admin->id]);
        ImportRow::create(['import_batch_id' => $fin->id, 'row_number' => 2, 'raw' => [], 'normalized' => ['record_type' => 'obligation', 'membership_number' => 'BOB-1', 'date' => '2025-01-01', 'amount_kobo' => 1000000, 'external_reference' => 'X'], 'status' => 'valid', 'messages' => []]);

        $mediaItem = Media::create(['path' => 'test/x.jpg', 'original_name' => 'x.jpg', 'mime' => 'image/jpeg', 'width' => 100, 'height' => 80, 'size_bytes' => 2048, 'alt_text' => 'Alt']);
        $page = Page::firstOrCreate(['key' => 'about'], ['label' => 'About']);
        $rev = PageRevision::create(['page_id' => $page->id, 'title' => 'About', 'body' => "## Hi\n\ntext", 'created_by' => $admin->id]);
        PageRevision::create(['page_id' => $page->id, 'title' => 'About 2', 'body' => 'text 2', 'created_by' => $admin->id]);
        $page->update(['published_revision_id' => $rev->id, 'published_at' => now()]);
        $post = Post::create(['type' => 'news', 'audience' => 'public', 'slug' => 'n-'.uniqid(), 'title' => 'News one', 'body' => 'x', 'status' => 'draft', 'cover_media_id' => $mediaItem->id]);
        $gallery = Gallery::create(['slug' => 'g-'.uniqid(), 'title' => 'Gal', 'status' => 'published']);
        GalleryItem::create(['gallery_id' => $gallery->id, 'media_id' => $mediaItem->id, 'caption' => 'Cap']);
        $pf = PrivateFile::create(['purpose' => 'member_document', 'path' => 'p/'.uniqid(), 'original_name' => 'doc.pdf', 'mime' => 'application/pdf', 'size_bytes' => 4096, 'sha256' => str_repeat('e', 64)]);
        $doc = Document::create(['title' => 'Doc', 'private_file_id' => $pf->id, 'status' => 'draft']);

        $event = Event::create(['slug' => 'e-'.uniqid(), 'title' => 'AGM', 'starts_at' => now()->addDays(5), 'audience' => 'members', 'status' => 'published', 'rsvp_enabled' => true]);
        EventRsvp::create(['event_id' => $event->id, 'member_id' => $member->id, 'response' => 'attending', 'guests' => 1]);

        $enq = Enquiry::create(['reference' => 'ENQ-1', 'name' => 'X', 'email' => 'x@example.test', 'topic' => 'general', 'subject' => 'Q', 'message' => "Hi\nthere", 'status' => 'new', 'consent_at' => now()]);
        EnquiryNote::create(['enquiry_id' => $enq->id, 'user_id' => $admin->id, 'note' => 'Called']);
        $rr = RecoveryRequest::create(['identifier_hash' => str_repeat('f', 64), 'user_id' => $other->id, 'status' => 'pending']);
        RecoveryRequest::create(['identifier_hash' => str_repeat('0', 64), 'status' => 'pending']);
        $bk = BackupRun::create(['kind' => 'backup', 'status' => 'succeeded', 'file_name' => 'b.enc', 'sha256' => str_repeat('1', 64), 'size_bytes' => 123456, 'details' => ['tables' => 30, 'rows' => 1000, 'triggers' => 12, 'migration' => 'm', 'finance' => ['verified_payments' => 5000, 'outstanding' => 100]], 'created_by' => $admin->id]);
        BackupRun::create(['kind' => 'restore_verification', 'status' => 'failed', 'file_name' => 'b.enc', 'details' => ['ok' => false, 'problems' => ['x differs'], 'rows' => 1000, 'triggers' => 12]]);
        Settings::set('approval.member_import', ['approved' => true, 'reference' => 'MIN', 'recorded_by' => $admin->id, 'recorded_at' => now()->toIso8601String()], $admin->id);

        $urls = [
            route('admin.applications.index'), route('admin.applications.index', ['status' => 'all', 'eligibility' => 'flagged', 'q' => 'Ade']),
            route('admin.applications.show', $app), route('admin.messages.index'), route('admin.messages.index', ['status' => 'all']), route('admin.messages.show', $msg),
            route('admin.members.index'), route('admin.members.index', ['incomplete' => 1, 'account' => 'none']), route('admin.members.show', $member), route('admin.members.show', $incomplete),
            route('admin.imports.members.index'), route('admin.imports.members.show', $batch), route('admin.imports.members.show', $clean), route('admin.finance.imports.show', $fin),
            route('admin.content.index'), route('admin.content.pages.edit', $page), route('admin.content.list', 'news'), route('admin.content.create', 'news'),
            route('admin.content.edit', ['news', $post->id]), route('admin.content.create', 'leaders'), route('admin.content.edit', ['galleries', $gallery->id]),
            route('admin.content.create', 'documents'), route('admin.content.edit', ['documents', $doc->id]), route('admin.content.media'),
            route('admin.events.index'), route('admin.events.create'), route('admin.events.edit', $event),
            route('admin.enquiries.index'), route('admin.enquiries.index', ['status' => 'resolved', 'mine' => 1]), route('admin.enquiries.show', $enq),
            route('admin.access.index'), route('admin.access.show', $other), route('admin.access.show', $admin),
            route('admin.audit.index'), route('admin.audit.index', ['log' => 'security']), route('admin.settings.index'), route('admin.backups.index'),
        ];
        $out = getenv('RENDER_OUT') ?: sys_get_temp_dir();
        foreach ($urls as $i => $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->status(), $url."\n".($response->exception?->getMessage() ?? ''));
            file_put_contents($out.'/page-'.$i.'.html', $response->getContent());
        }
        // A POST with validation errors to check error rendering paths.
        $this->from(route('admin.members.show', $member))->post(route('admin.members.update', $member), [])->assertRedirect();
        $this->get(route('admin.members.show', $member))->assertOk()->assertSee('Please correct');
        $this->from(route('admin.access.index'))->post(route('admin.access.store'), ['roles' => ['treasurer']])->assertRedirect();
        $this->get(route('admin.access.index'))->assertOk();
        // Real decision on the application.
        $this->post(route('admin.messages.delivered', $msg), ['note' => 'Sent by phone', 'confirm' => '1'])->assertRedirect(route('admin.messages.index'));
        $this->post(route('admin.settings.update'), ['membership__number_width' => 6, 'org__name' => 'Synthetic'])->assertRedirect(route('admin.settings.index'));
        $this->post(route('admin.settings.approval', 'approval.historical_finance'), ['approved' => 1, 'reference' => 'MIN-2'])->assertRedirect(route('admin.settings.index'));
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('MIN-2');
    }
}
