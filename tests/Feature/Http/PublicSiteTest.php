<?php

namespace Tests\Feature\Http;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_public_page_renders_with_security_headers_and_metadata(): void
    {
        foreach (['home', 'about', 'history', 'leadership', 'membership', 'membership.apply', 'membership.status', 'activities', 'events.index', 'news.index', 'gallery.index', 'contact', 'privacy', 'terms', 'login', 'password.request'] as $name) {
            $response = $this->get(route($name))->assertOk();
            $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
            $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
            $html = $response->getContent();
            $this->assertSame(1, substr_count($html, '<h1'), "{$name} must have exactly one h1");
            $this->assertStringContainsString('<meta name="description"', $html);
            $this->assertStringContainsString('lang="en"', $html);
            $this->assertStringNotContainsString(' style="', $html, "{$name} must not use inline styles (CSP)");
        }
        $this->get('/members')->assertRedirect('/membership');
        $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->get(route('login'))->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_no_fabricated_content_is_shown_on_an_empty_site(): void
    {
        $html = $this->get(route('home'))->getContent();
        $this->assertStringNotContainsString('News and gatherings', $html, 'No placeholder news');
        $this->assertStringNotContainsString('Service before self', $html, 'No placeholder leaders');
        $this->get(route('leadership'))->assertSee('being confirmed');
        $this->get(route('contact'))->assertDontSee('@bobagunwa.com');
    }

    public function test_unpublished_and_member_only_events_are_hidden_from_the_public(): void
    {
        Event::create(['slug' => 'draft-x', 'title' => 'Draft synthetic event', 'starts_at' => now()->addDay(), 'audience' => 'public', 'status' => 'draft']);
        Event::create(['slug' => 'members-x', 'title' => 'Members synthetic event', 'starts_at' => now()->addDay(), 'audience' => 'members', 'status' => 'published']);
        $this->get(route('events.index'))->assertDontSee('Draft synthetic event')->assertDontSee('Members synthetic event');
        $this->get(route('events.show', 'draft-x'))->assertNotFound();
        $this->get(route('events.show', 'members-x'))->assertNotFound();
    }

    public function test_contact_enquiry_is_stored_with_reference(): void
    {
        $this->post(route('contact.store'), ['name' => 'Synthetic Visitor', 'email' => 'visitor@example.test', 'topic' => 'general', 'subject' => 'Hello', 'message' => 'A synthetic enquiry message.', 'consent' => '1', 'form_stamp' => Crypt::encryptString((string) (time() - 10))])
            ->assertRedirect(route('contact'))->assertSessionHas('enquiry_reference');
        $this->assertDatabaseHas('enquiries', ['email' => 'visitor@example.test', 'status' => 'new']);
        $this->post(route('contact.store'), ['name' => 'X', 'email' => 'bad', 'topic' => 'general', 'subject' => 'x', 'message' => 'short', 'form_stamp' => Crypt::encryptString((string) (time() - 10))])->assertSessionHasErrors(['email', 'message', 'consent']);
    }

    public function test_csrf_and_origin_protection_reject_forged_posts_outside_tests(): void
    {
        $this->app['env'] = 'production';
        $this->post(route('login.attempt'), ['email' => 'x@example.test', 'password' => 'y'])->assertStatus(419);
    }
}
