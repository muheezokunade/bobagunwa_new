<?php

namespace Tests\Feature\Http;

use App\Models\User;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the findings of the 22 Nov 2026 staging walkthrough.
 */
class TesterFeedbackFixesTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a different sentence to remember 77';

    public function test_password_change_leaves_only_the_new_session_signed_in(): void
    {
        config(['session.driver' => 'database']);
        $officer = $this->officer('system_admin');
        $this->post(route('login.attempt'), ['email' => $officer->email, 'password' => 'synthetic-password-123'])->assertRedirect();
        $before = DB::table('sessions')->where('user_id', $officer->id)->pluck('id');
        $this->assertCount(1, $before, 'signing in stores one session');
        DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $officer->id, 'ip_address' => '192.0.2.10', 'user_agent' => 'Other device', 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);

        $this->post(route('admin.account.password'), ['current_password' => 'synthetic-password-123', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasNoErrors();

        $after = DB::table('sessions')->where('user_id', $officer->id)->pluck('id');
        $this->assertCount(1, $after, 'only the session that changed the password remains');
        $this->assertNotContains($before[0], $after, 'the pre-change session ID no longer works');
    }

    public function test_activation_link_does_not_switch_accounts_silently(): void
    {
        $member = $this->member();
        $link = app(MemberService::class)->issueAccess($member, $this->officer('membership_officer'));
        $token = basename(parse_url($link['url'], PHP_URL_PATH));
        $officer = $this->officer('membership_officer');

        $this->actingAs($officer)->get(route('account.activate', $token))->assertRedirect(route('account.activate.form'));
        $this->actingAs($officer)->get(route('account.activate.form'))->assertOk()
            ->assertSee('You are signed in as')->assertSee('different')->assertDontSee('Choose a password');
        $this->actingAs($officer)->post(route('account.activate.store'), ['token' => $token, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertSessionHasErrors('domain');

        $this->assertAuthenticatedAs($officer);
        $user = User::where('member_id', $member->id)->sole();
        $this->assertSame('pending_activation', $user->status, 'the link was not used up');
    }

    public function test_robots_and_sitemap_use_absolute_urls_and_include_the_homepage(): void
    {
        $robots = $this->get(route('robots'))->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->get(route('sitemap'))->assertOk()->assertSee('<loc>'.rtrim(route('home'), '/').'/</loc>', false);
    }

    public function test_application_errors_use_the_form_labels(): void
    {
        $this->post(route('membership.apply.store'), ['form_stamp' => Crypt::encryptString((string) (time() - 30)), 'website' => ''])
            ->assertSessionHasErrors(['last_name' => 'The surname field is required.', 'ijebu_town' => 'The Ijebu town field is required.']);
    }
}
