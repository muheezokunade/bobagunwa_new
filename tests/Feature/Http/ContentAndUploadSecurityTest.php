<?php

namespace Tests\Feature\Http;

use App\Models\Page;
use App\Models\PrivateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContentAndUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_drafts_stay_private_until_a_publisher_publishes_them(): void
    {
        $editor = $this->officer('secretariat');
        $page = Page::where('key', 'history')->first();
        $this->actingAs($editor)->post(route('admin.content.pages.save', $page), ['title' => 'Our history (synthetic)', 'body' => "## Origins\n\nSynthetic history text.<script>alert(1)</script> [bad](javascript:alert(1))"])->assertSessionHasNoErrors();
        $this->post(route('logout'));
        $this->get(route('history'))->assertOk()->assertDontSee('Synthetic history text')->assertSee('being prepared');

        $revision = $page->revisions()->first();
        $this->actingAs($editor)->post(route('admin.content.pages.publish', [$page, $revision]))->assertSessionHasNoErrors();
        $this->post(route('logout'));
        $html = $this->get(route('history'))->assertOk()->assertSee('Synthetic history text')->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    public function test_news_publication_and_member_only_notices(): void
    {
        $editor = $this->officer('secretariat');
        $this->actingAs($editor)->post(route('admin.content.store', 'news'), ['title' => 'Synthetic headline', 'body' => 'Body'])->assertRedirect();
        $this->actingAs($editor)->post(route('admin.content.store', 'announcements'), ['title' => 'Members only notice', 'body' => 'Private'])->assertRedirect();
        $news = \App\Models\Post::where('title', 'Synthetic headline')->first();
        $notice = \App\Models\Post::where('title', 'Members only notice')->first();
        $this->post(route('logout'));
        $this->get(route('news.show', $news->slug))->assertNotFound();
        $this->actingAs($editor)->post(route('admin.content.status', ['news', $news->id]), ['status' => 'published']);
        $this->actingAs($editor)->post(route('admin.content.status', ['announcements', $notice->id]), ['status' => 'published']);
        $this->post(route('logout'));
        $this->get(route('news.show', $news->slug))->assertOk()->assertSee('Synthetic headline');
        $this->get(route('news.index'))->assertDontSee('Members only notice');
        $member = $this->member([], true);
        $this->actingAs($member->user)->get(route('portal.notices'))->assertSee('Members only notice');
        $this->actingAs($member->user)->post(route('portal.notices.read', $notice))->assertRedirect();
        $this->assertDatabaseHas('announcement_reads', ['post_id' => $notice->id, 'member_id' => $member->id]);
        // An editor without publishing permission cannot publish.
        $this->actingAs($this->officer('treasurer'))->post(route('admin.content.status', ['news', $news->id]), ['status' => 'archived'])->assertForbidden();
    }

    public function test_prohibited_and_disguised_uploads_are_rejected(): void
    {
        $treasurer = $this->officer('treasurer');
        $member = $this->member();
        $base = ['member_id' => $member->id, 'amount' => '1000', 'method' => 'cash', 'received_on' => now()->toDateString()];
        $cases = [
            'pdf with javascript' => UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog /OpenAction << /S /JavaScript /JS (app.alert(1)) >> >>\n%%EOF"),
            'php disguised as jpg' => UploadedFile::fake()->createWithContent('teller.jpg', '<?php system($_GET["c"]); ?>'),
            'html file' => UploadedFile::fake()->createWithContent('receipt.html', '<html><script>alert(1)</script></html>'),
        ];
        foreach ($cases as $label => $file) {
            $this->actingAs($treasurer)->post(route('admin.finance.payments.store'), $base + ['evidence' => $file, 'idempotency_key' => $this->key()])->assertSessionHasErrors('domain');
            $this->assertSame(0, PrivateFile::count(), $label);
        }
        $this->assertSame(0, \App\Models\Payment::count());

        // A genuine image is re-encoded (metadata and trailing payloads discarded) and stored privately.
        $image = imagecreatetruecolor(40, 30);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean().'<?php echo "trailing payload"; ?>';
        $this->actingAs($treasurer)->post(route('admin.finance.payments.store'), $base + ['evidence' => UploadedFile::fake()->createWithContent('teller.jpg', $jpeg), 'idempotency_key' => $this->key()])->assertSessionHasNoErrors();
        $stored = PrivateFile::sole();
        $content = file_get_contents(storage_path('app/private/files/'.$stored->path));
        $this->assertStringNotContainsString('trailing payload', $content);
        $this->assertStringStartsNotWith(public_path(), storage_path('app/private/files/'.$stored->path));
        $this->actingAs($this->officer('membership_officer'))->get(route('admin.finance.payments.evidence', \App\Models\Payment::sole()))->assertForbidden();
    }

    public function test_media_upload_requires_rights_confirmation_and_valid_image(): void
    {
        $editor = $this->officer('secretariat');
        $png = UploadedFile::fake()->image('crest.png', 20, 20);
        $this->actingAs($editor)->post(route('admin.content.media.upload'), ['image' => $png, 'alt_text' => 'Synthetic'])->assertSessionHasErrors('rights_confirmed');
        $this->actingAs($editor)->post(route('admin.content.media.upload'), ['image' => UploadedFile::fake()->createWithContent('x.png', 'GIF89a fake'), 'alt_text' => 'Synthetic', 'rights_confirmed' => '1'])->assertSessionHasErrors('domain');
        $this->actingAs($editor)->post(route('admin.content.media.upload'), ['image' => $png, 'alt_text' => 'Synthetic', 'rights_confirmed' => '1'])->assertSessionHasNoErrors();
        $media = \App\Models\Media::sole();
        $this->assertFileExists(public_path('media/'.$media->path));
        @unlink(public_path('media/'.$media->path));
    }
}
