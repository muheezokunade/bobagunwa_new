<?php

namespace Tests\Feature\Http;

use App\Models\AccountToken;
use App\Models\Assessment;
use App\Models\AuditEvent;
use App\Models\BackupRun;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\FinancialReversal;
use App\Models\Gallery;
use App\Models\ImportBatch;
use App\Models\ObligationAdjustment;
use App\Models\OutboundMessage;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Post;
use App\Models\PrivateFile;
use App\Models\User;
use App\Services\BackupService;
use App\Services\Finance\LedgerQuery;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Behaviour of each administrative mutation through HTTP: authorised success,
 * state change, audit evidence, validation failure without side effects,
 * duplicate-submission handling and ownership where applicable.
 */
class AdminMutationBehaviourTest extends TestCase
{
    use RefreshDatabase;

    private function audited(string $action, ?int $subjectId = null): void
    {
        $query = AuditEvent::where('action', $action);
        if ($subjectId !== null) {
            $query->where('subject_id', $subjectId);
        }
        $this->assertTrue($query->exists(), "Expected audit event {$action}");
    }

    public function test_payment_rejection(): void
    {
        $m = $this->member();
        $treasurer = $this->officer('treasurer');
        $verifier = $this->officer('payment_verifier');
        [$p] = app(PaymentService::class)->record(['member_id' => $m->id, 'amount' => '5000', 'method' => 'cash', 'received_on' => now()->toDateString()], $treasurer, $this->key());
        $this->actingAs($verifier)->post(route('admin.finance.payments.reject', $p), ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->assertSame('pending', $p->fresh()->status);
        $this->actingAs($verifier)->post(route('admin.finance.payments.reject', $p), ['reason' => 'Cash not found in the cash book'])->assertSessionHasNoErrors();
        $this->assertSame(['rejected', $verifier->id], [$p->fresh()->status, $p->fresh()->rejected_by]);
        $this->audited('payment.rejected', $p->id);
        $this->actingAs($verifier)->post(route('admin.finance.payments.reject', $p), ['reason' => 'Second attempt'])->assertSessionHasErrors('domain');
        $this->actingAs($verifier)->post(route('admin.finance.payments.verify', $p), ['confirm' => '1'])->assertSessionHasErrors('domain');
        $this->assertSame(0, app(LedgerQuery::class)->memberSummary($m->id)['verified_payments']);
    }

    public function test_payment_reversal_with_dependent_allocation_and_replay(): void
    {
        $m = $this->member();
        $a = $this->assess([$m], '50000');
        $p = $this->verifiedPayment($m, '20000');
        app(PaymentService::class)->allocate($p, [$this->obligationOf($m, $a)->id => 2_000_000], $this->officer('treasurer'), $this->key());
        $verifier = $this->officer('payment_verifier');
        $this->actingAs($verifier)->post(route('admin.finance.payments.reverse', $p), ['reason' => 'Bounced', 'idempotency_key' => $this->key()])->assertSessionHasErrors('confirm');
        $this->assertSame('verified', $p->fresh()->status);
        $key = $this->key();
        $this->actingAs($verifier)->post(route('admin.finance.payments.reverse', $p), ['reason' => 'Transfer bounced (synthetic)', 'idempotency_key' => $key, 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($verifier)->post(route('admin.finance.payments.reverse', $p), ['reason' => 'Transfer bounced (synthetic)', 'idempotency_key' => $key, 'confirm' => '1'])->assertSessionHas('status', fn ($s) => str_contains($s, 'already recorded'));
        $this->actingAs($verifier)->post(route('admin.finance.payments.reverse', $p), ['reason' => 'Again', 'idempotency_key' => $this->key(), 'confirm' => '1'])->assertSessionHasErrors('domain');
        $this->assertSame('reversed', $p->fresh()->status);
        $this->assertSame(2, FinancialReversal::where('payment_id', $p->id)->count());
        $this->assertSame(5_000_000, app(LedgerQuery::class)->memberSummary($m->id)['outstanding']);
        $this->audited('payment.reversed', $p->id);
        $this->assertEmpty(array_filter(app(LedgerQuery::class)->exceptions()));
    }

    public function test_allocation_reversal(): void
    {
        $m = $this->member();
        $a = $this->assess([$m], '50000');
        $p = $this->verifiedPayment($m, '20000');
        app(PaymentService::class)->allocate($p, [$this->obligationOf($m, $a)->id => 2_000_000], $this->officer('treasurer'), $this->key());
        $allocation = PaymentAllocation::sole();
        $verifier = $this->officer('payment_verifier');
        $this->actingAs($verifier)->post(route('admin.finance.allocations.reverse', $allocation), ['reason' => '', 'idempotency_key' => $this->key()])->assertSessionHasErrors('reason');
        $this->actingAs($verifier)->post(route('admin.finance.allocations.reverse', $allocation), ['reason' => 'Applied to the wrong levy', 'idempotency_key' => $this->key()])->assertRedirect(route('admin.finance.payments.show', $p));
        $this->assertSame('reversed', $allocation->fresh()->status);
        $this->actingAs($verifier)->post(route('admin.finance.allocations.reverse', $allocation), ['reason' => 'Twice', 'idempotency_key' => $this->key()])->assertSessionHasErrors('domain');
        $this->assertSame(2_000_000, app(LedgerQuery::class)->memberSummary($m->id)['unallocated_credit']);
        $this->audited('allocation.reversed');
    }

    public function test_obligation_adjustment_and_replay(): void
    {
        $m = $this->member();
        $o = $this->obligationOf($m, $this->assess([$m], '50000'));
        $t = $this->officer('treasurer');
        $this->actingAs($t)->post(route('admin.finance.obligations.adjust', $o), ['kind' => 'waiver', 'amount' => 'abc', 'reason' => 'Hardship', 'effective_on' => now()->toDateString(), 'idempotency_key' => $this->key()])->assertSessionHasErrors('domain');
        $this->actingAs($t)->post(route('admin.finance.obligations.adjust', $o), ['kind' => 'refund', 'amount' => '1', 'reason' => 'x', 'effective_on' => 'bad', 'idempotency_key' => $this->key()])->assertSessionHasErrors(['kind', 'reason', 'effective_on']);
        $key = $this->key();
        $body = ['kind' => 'waiver', 'amount' => '5000', 'reason' => 'Hardship waiver (synthetic)', 'effective_on' => now()->toDateString(), 'idempotency_key' => $key];
        $this->actingAs($t)->post(route('admin.finance.obligations.adjust', $o), $body)->assertSessionHasNoErrors();
        $this->actingAs($t)->post(route('admin.finance.obligations.adjust', $o), $body)->assertSessionHas('status', fn ($s) => str_contains($s, 'already recorded'));
        $this->assertSame(1, ObligationAdjustment::count());
        $this->assertSame(4_500_000, app(LedgerQuery::class)->memberSummary($m->id)['outstanding']);
        $this->audited('obligation.adjusted');
    }

    public function test_assessment_discard_and_category_update_with_version_conflict(): void
    {
        $t = $this->officer('treasurer');
        $cat = $this->category()->fresh();
        $draft = Assessment::create(['reference' => 'ASM-DISCARD', 'fee_category_id' => $cat->id, 'title' => 'Draft', 'amount_kobo' => 100, 'effective_on' => now()->toDateString(), 'status' => 'draft', 'target_mode' => 'all_active', 'created_by' => $t->id]);
        $this->actingAs($t)->post(route('admin.finance.assessments.discard', $draft))->assertRedirect(route('admin.finance.assessments.index'));
        $this->assertSame('discarded', $draft->fresh()->status);
        $this->actingAs($t)->post(route('admin.finance.assessments.discard', $draft))->assertSessionHasErrors('domain');
        $this->audited('assessment.discarded', $draft->id);

        $update = ['code' => $cat->code, 'name' => 'Renamed dues', 'kind' => 'dues', 'version' => $cat->version, 'is_active' => '1', 'description' => 'Synthetic'];
        $this->actingAs($t)->post(route('admin.finance.categories.update', $cat), $update)->assertSessionHasNoErrors();
        $this->assertSame('Renamed dues', $cat->fresh()->name);
        $this->actingAs($t)->post(route('admin.finance.categories.update', $cat), $update)->assertSessionHasErrors('domain'); // stale version
        $this->actingAs($t)->post(route('admin.finance.categories.update', $cat), ['code' => 'bad code!', 'name' => '', 'kind' => 'x', 'version' => $cat->fresh()->version])->assertSessionHasErrors(['code', 'name', 'kind']);
        $this->audited('category.updated', $cat->id);
    }

    public function test_finance_import_cancel_is_kind_scoped(): void
    {
        $t = $this->officer('treasurer');
        $secretariat = $this->officer('secretariat');
        $finance = ImportBatch::create(['reference' => 'FIN-C', 'kind' => 'finance_transactions', 'status' => 'previewed', 'original_filename' => 'f.csv', 'file_sha256' => str_repeat('d', 64), 'created_by' => $t->id]);
        $members = ImportBatch::create(['reference' => 'IMP-C', 'kind' => 'members', 'status' => 'previewed', 'original_filename' => 'm.csv', 'file_sha256' => str_repeat('e', 64), 'created_by' => $secretariat->id]);
        // A members-import officer cannot cancel a financial import through the members route, and vice versa.
        $this->actingAs($secretariat)->post(route('admin.imports.members.cancel', $finance))->assertNotFound();
        $this->actingAs($t)->post(route('admin.finance.imports.cancel', $members))->assertNotFound();
        $this->actingAs($t)->post(route('admin.finance.imports.cancel', $finance))->assertRedirect(route('admin.finance.imports.index'));
        $this->assertSame(['cancelled', 'previewed'], [$finance->fresh()->status, $members->fresh()->status]);
        $this->actingAs($t)->post(route('admin.finance.imports.cancel', $finance))->assertSessionHasErrors('domain');
        $this->audited('import.cancelled', $finance->id);
    }

    public function test_member_access_link_and_officer_access_link(): void
    {
        $member = $this->member();
        $officer = $this->officer('membership_officer');
        $this->actingAs($officer)->post(route('admin.members.access', $member))->assertSessionHasErrors('confirm_identity');
        $this->assertSame(0, AccountToken::count());
        $this->actingAs($officer)->post(route('admin.members.access', $member), ['confirm_identity' => '1'])->assertSessionHas('one_time_link');
        $this->actingAs($officer)->post(route('admin.members.access', $member), ['confirm_identity' => '1'])->assertSessionHas('one_time_link');
        $this->assertSame(1, AccountToken::whereNull('revoked_at')->count(), 'Issuing again revokes the earlier unused link');
        $this->audited('account.activation_issued');
        $noEmail = $this->member(['email' => null]);
        $noEmail->update(['email_normalized' => null]);
        $this->actingAs($officer)->post(route('admin.members.access', $noEmail), ['confirm_identity' => '1'])->assertSessionHasErrors('domain');

        $admin = $this->officer('system_admin');
        $target = $this->officer('treasurer');
        $this->actingAs($admin)->post(route('admin.access.link', $target))->assertSessionHasErrors('confirm_identity');
        $this->actingAs($admin)->post(route('admin.access.link', $target), ['confirm_identity' => '1'])->assertSessionHas('one_time_link', fn ($l) => str_contains($l['label'], 'Password reset'));
        $this->audited('account.password_reset_issued', $target->id);
    }

    public function test_officer_creation_and_member_correction(): void
    {
        $admin = $this->officer('system_admin');
        $this->actingAs($admin)->post(route('admin.access.store'), ['name' => 'New Officer', 'email' => 'bad', 'roles' => [], 'reason' => 'x'])->assertSessionHasErrors(['email', 'roles', 'reason']);
        $this->actingAs($admin)->post(route('admin.access.store'), ['name' => 'New Officer', 'email' => 'new.officer@example.test', 'roles' => ['treasurer'], 'reason' => 'Elected treasurer (synthetic)'])->assertSessionHas('one_time_link');
        $new = User::where('email', 'new.officer@example.test')->sole();
        $this->assertSame(['pending_activation', ['treasurer']], [$new->status, $new->roles->pluck('key')->all()]);
        $this->actingAs($admin)->post(route('admin.access.store'), ['name' => 'Again', 'email' => 'new.officer@example.test', 'roles' => ['treasurer'], 'reason' => 'Duplicate'])->assertSessionHasErrors('domain');
        $this->audited('access.officer_created', $new->id);

        $member = $this->member();
        $officer = $this->officer('membership_officer');
        $body = ['version' => $member->version, 'reason' => 'Corrected spelling', 'full_name' => 'Corrected Synthetic Name', 'email' => $member->email];
        $this->actingAs($officer)->post(route('admin.members.update', $member), $body)->assertSessionHasNoErrors();
        $this->assertSame('Corrected Synthetic Name', $member->fresh()->full_name);
        $this->actingAs($officer)->post(route('admin.members.update', $member), $body)->assertSessionHasErrors('domain'); // stale version
        $this->audited('member.corrected', $member->id);
        $this->actingAs($officer)->post(route('admin.members.status', $member), ['status' => 'suspended', 'reason' => 'Synthetic suspension'])->assertSessionHasNoErrors();
        $this->audited('member.status_changed', $member->id);
    }

    public function test_events_create_update_status_and_responses(): void
    {
        $sec = $this->officer('secretariat');
        $this->actingAs($sec)->post(route('admin.events.store'), ['title' => '', 'starts_at' => 'not a date', 'audience' => 'everyone'])->assertSessionHasErrors(['title', 'starts_at', 'audience']);
        $this->assertSame(0, Event::count());
        $this->actingAs($sec)->post(route('admin.events.store'), ['title' => 'Synthetic gathering', 'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'), 'ends_at' => now()->addMonth()->subHour()->format('Y-m-d\TH:i'), 'audience' => 'public'])->assertSessionHasErrors('ends_at');
        $this->actingAs($sec)->post(route('admin.events.store'), ['title' => 'Synthetic gathering', 'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'), 'audience' => 'public', 'rsvp_enabled' => '1'])->assertRedirect();
        $event = Event::sole();
        $this->assertSame('draft', $event->status);
        $this->actingAs($sec)->post(route('admin.events.update', $event), ['title' => 'Renamed gathering', 'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'), 'audience' => 'members'])->assertSessionHasNoErrors();
        $this->assertSame(['Renamed gathering', 'members'], [$event->fresh()->title, $event->fresh()->audience]);
        $this->actingAs($sec)->post(route('admin.events.status', $event), ['status' => 'cancelled'])->assertSessionHasErrors('domain'); // draft -> cancelled not allowed
        $this->actingAs($sec)->post(route('admin.events.status', $event), ['status' => 'published'])->assertSessionHasNoErrors();
        $this->assertSame('published', $event->fresh()->status);
        $this->actingAs($sec)->get(route('admin.events.responses', $event))->assertOk();
        foreach (['event.created', 'event.updated', 'event.status_changed'] as $action) {
            $this->audited($action, $event->id);
        }
    }

    public function test_enquiry_update_assignment_rules(): void
    {
        $sec = $this->officer('secretariat');
        $enquiry = Enquiry::create(['reference' => 'ENQ-T', 'name' => 'Visitor', 'email' => 'v@example.test', 'topic' => 'general', 'subject' => 'Hi', 'message' => 'Hello there', 'consent_at' => now()]);
        $nonOfficer = $this->member([], true)->user;
        $this->actingAs($sec)->post(route('admin.enquiries.update', $enquiry), ['status' => 'resolved', 'assigned_to' => $nonOfficer->id])->assertSessionHasErrors('domain');
        $this->actingAs($sec)->post(route('admin.enquiries.update', $enquiry), ['status' => 'bogus'])->assertSessionHasErrors('status');
        $this->assertSame('new', $enquiry->fresh()->status);
        $this->actingAs($sec)->post(route('admin.enquiries.update', $enquiry), ['status' => 'resolved', 'assigned_to' => $sec->id, 'note' => 'Replied by phone'])->assertSessionHasNoErrors();
        $this->assertSame(['resolved', $sec->id], [$enquiry->fresh()->status, $enquiry->fresh()->assigned_to]);
        $this->assertNotNull($enquiry->fresh()->resolved_at);
        $this->audited('enquiry.updated', $enquiry->id);
    }

    public function test_message_cancellation(): void
    {
        $officer = $this->officer('membership_officer');
        $message = OutboundMessage::create(['purpose' => 'test', 'recipient_name' => 'X', 'subject' => 'S', 'body' => 'B', 'status' => 'pending']);
        $this->actingAs($officer)->post(route('admin.messages.cancel', $message), ['note' => 'x'])->assertSessionHasErrors('note');
        $this->actingAs($officer)->post(route('admin.messages.cancel', $message), ['note' => 'Duplicate message, not sent'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $message->fresh()->status);
        $this->actingAs($officer)->post(route('admin.messages.delivered', $message), ['note' => 'Sent anyway', 'confirm' => '1'])->assertSessionHasErrors('domain');
        $this->audited('message.cancelled', $message->id);
    }

    public function test_content_update_unpublish_and_gallery_item(): void
    {
        $sec = $this->officer('secretariat');
        $post = Post::create(['type' => 'news', 'audience' => 'public', 'slug' => 'beh-news', 'title' => 'Original', 'body' => 'Body', 'status' => 'published', 'published_at' => now()]);
        $this->actingAs($sec)->post(route('admin.content.update', ['news', $post->id]), ['title' => '', 'body' => ''])->assertSessionHasErrors(['title', 'body']);
        $this->actingAs($sec)->post(route('admin.content.update', ['news', $post->id]), ['title' => 'Updated title', 'body' => 'Updated'])->assertSessionHasNoErrors();
        $this->assertSame('Updated title', $post->fresh()->title);
        $this->audited('content.updated', $post->id);
        // Scope: a news id cannot be edited through the notices route.
        $this->actingAs($sec)->post(route('admin.content.update', ['announcements', $post->id]), ['title' => 'Hijack', 'body' => 'x'])->assertNotFound();

        $page = Page::where('key', 'history')->first();
        $rev = PageRevision::create(['page_id' => $page->id, 'title' => 'History', 'body' => 'Synthetic']);
        $this->actingAs($sec)->post(route('admin.content.pages.publish', [$page, $rev]));
        $this->actingAs($sec)->post(route('admin.content.pages.unpublish', $page))->assertSessionHasNoErrors();
        $this->assertNull($page->fresh()->published_revision_id);
        $this->audited('page.unpublished', $page->id);
        // A revision of another page cannot be published onto this page.
        $other = PageRevision::create(['page_id' => Page::where('key', 'about')->value('id'), 'title' => 'About', 'body' => 'x']);
        $this->actingAs($sec)->post(route('admin.content.pages.publish', [$page, $other]))->assertNotFound();

        $gallery = Gallery::create(['slug' => 'beh-gallery', 'title' => 'Gallery', 'status' => 'draft']);
        $image = UploadedFile::fake()->image('photo.jpg', 40, 30);
        $this->actingAs($sec)->post(route('admin.content.galleries.items', $gallery->id), ['image' => $image, 'alt_text' => 'Synthetic photo'])->assertSessionHasErrors('rights_confirmed');
        $this->actingAs($sec)->post(route('admin.content.galleries.items', $gallery->id), ['image' => UploadedFile::fake()->image('photo.jpg', 40, 30), 'alt_text' => 'Synthetic photo', 'rights_confirmed' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, $gallery->items()->count());
        $this->audited('gallery.item_added', $gallery->id);
        @unlink(public_path('media/'.$gallery->items()->first()->media->path));
    }

    public function test_backup_creation_requires_a_configured_key_and_succeeds_with_one(): void
    {
        $admin = $this->officer('system_admin');
        config(['boba.backup.public_key' => '']);
        $this->actingAs($admin)->post(route('admin.backups.store'))->assertSessionHasErrors('domain');
        $this->assertSame(0, BackupRun::where('status', 'succeeded')->count());
        $dir = storage_path('app/private/backup-http-test-'.uniqid());
        config(['boba.backup.public_key' => BackupService::generateKeypair()['public'], 'boba.backup.path' => $dir]);
        $this->actingAs($admin)->post(route('admin.backups.store'))->assertSessionHasNoErrors();
        $run = BackupRun::where('status', 'succeeded')->sole();
        $this->assertFileExists($dir.'/'.$run->file_name);
        $this->audited('backup.created', $run->id);
        $this->actingAs($admin)->get(route('admin.backups.download', $run))->assertOk();
        $this->actingAs($this->officer('treasurer'))->get(route('admin.backups.download', $run))->assertForbidden();
        File::deleteDirectory($dir);
    }

    public function test_refused_payment_does_not_leave_its_evidence_file(): void
    {
        $t = $this->officer('treasurer');
        $m = $this->member();
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();
        $base = ['member_id' => $m->id, 'amount' => '1000', 'method' => 'bank_transfer', 'received_on' => now()->toDateString(), 'external_reference' => 'EVID-1'];
        $this->actingAs($t)->post(route('admin.finance.payments.store'), $base + ['evidence' => UploadedFile::fake()->createWithContent('a.jpg', $jpeg), 'idempotency_key' => $this->key()])->assertSessionHasNoErrors();
        $this->assertSame(1, PrivateFile::count());
        // Same bank reference: refused, and the second upload is removed.
        $this->actingAs($t)->post(route('admin.finance.payments.store'), $base + ['evidence' => UploadedFile::fake()->createWithContent('b.jpg', $jpeg), 'idempotency_key' => $this->key()])->assertSessionHasErrors('domain');
        $this->assertSame(1, PrivateFile::count());
        $this->assertSame(1, Payment::count());
    }
}
