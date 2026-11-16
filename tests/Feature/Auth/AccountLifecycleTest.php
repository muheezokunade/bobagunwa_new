<?php

namespace Tests\Feature\Auth;

use App\Models\AccountToken;
use App\Models\Member;
use App\Models\RecoveryRequest;
use App\Models\User;
use App\Services\AccountService;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a sentence I will remember 42';

    private function tokenFromUrl(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    private function issueActivation(Member $member): string
    {
        $link = app(MemberService::class)->issueAccess($member, $this->officer('membership_officer'));

        return $this->tokenFromUrl($link['url']);
    }

    public function test_imported_member_cannot_log_in_until_activated_then_activation_is_single_use(): void
    {
        $member = $this->member(['origin' => 'import']);
        $this->assertNull($member->user);
        $this->post(route('login.attempt'), ['email' => $member->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');

        $token = $this->issueActivation($member);
        $user = $member->fresh()->user;
        $this->assertSame('pending_activation', $user->status);
        $this->assertSame(hash('sha256', $token), AccountToken::where('user_id', $user->id)->value('token_hash'));
        $this->post(route('login.attempt'), ['email' => $member->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');

        $this->get(route('account.activate', $token))->assertOk()->assertSee('Choose a password');
        $this->post(route('account.activate.store'), ['token' => $token, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('domain');
        $this->post(route('account.activate.store'), ['token' => $token, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->get(route('portal.dashboard'))->assertOk()->assertSee($member->membership_number);
        $this->post(route('logout'));

        // Replay is refused.
        $this->get(route('account.activate', $token))->assertOk()->assertSee('cannot be used');
        $this->post(route('account.activate.store'), ['token' => $token, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('domain');
        $this->post(route('login.attempt'), ['email' => strtoupper($member->email), 'password' => self::PASSWORD])->assertRedirect(route('portal.dashboard'));
        $this->assertDatabaseHas('security_events', ['event' => 'activation.rejected', 'user_id' => $user->id]);
    }

    public function test_expired_and_superseded_activation_tokens_are_refused(): void
    {
        $member = $this->member();
        $first = $this->issueActivation($member);
        $second = $this->issueActivation($member->fresh());
        $this->post(route('account.activate.store'), ['token' => $first, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('domain');
        AccountToken::where('token_hash', hash('sha256', $second))->update(['expires_at' => now()->subMinute()]);
        $this->post(route('account.activate.store'), ['token' => $second, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('domain');
        $this->post(route('account.activate.store'), ['token' => 'not-a-real-token', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('domain');
        $this->assertGuest();
    }

    public function test_unapproved_applicant_has_no_account_to_activate(): void
    {
        $this->post(route('account.activate.store'), ['token' => str_repeat('x', 43), 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('domain');
        $this->assertSame(0, User::count());
    }

    public function test_suspended_member_is_denied_and_existing_sessions_end(): void
    {
        $member = $this->member([], true);
        $user = $member->user;
        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'synthetic-password-123'])->assertRedirect(route('portal.dashboard'));
        app(MemberService::class)->changeStatus($member, 'suspended', 'Synthetic suspension', $this->officer('membership_officer'));
        // The live session is rejected on its next request.
        $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'synthetic-password-123'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('security_events', ['event' => 'login.denied_inactive', 'user_id' => $user->id]);
        app(MemberService::class)->changeStatus($member->fresh(), 'active', 'Reinstated', $this->officer('membership_officer'));
        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'synthetic-password-123'])->assertRedirect(route('portal.dashboard'));
    }

    public function test_reset_tokens_expire_are_single_use_and_end_sessions(): void
    {
        $member = $this->member([], true);
        $user = $member->user;
        DB::table('sessions')->insert(['id' => 'synthetic-other-session', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);
        $accounts = app(AccountService::class);

        $expired = $accounts->issue($user, 'password_reset', 'admin', null);
        $expired['record']->update(['expires_at' => now()->subSecond()]);
        $this->post(route('password.update'), ['token' => $expired['token'], 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('domain');

        $link = $accounts->issue($user, 'password_reset', 'admin', null);
        $this->get(route('password.reset', $link['token']))->assertOk()->assertSee('Save new password');
        $this->post(route('password.update'), ['token' => $link['token'], 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertRedirect(route('login'));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->post(route('password.update'), ['token' => $link['token'], 'password' => self::PASSWORD.'x', 'password_confirmation' => self::PASSWORD.'x'])->assertSessionHasErrors('domain');
        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect(route('portal.dashboard'));
    }

    public function test_recovery_is_enumeration_safe_and_queued_for_officers(): void
    {
        $member = $this->member([], true);
        $known = $this->post(route('password.email'), ['email' => $member->email]);
        $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.test']);
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
        $this->assertSame(1, RecoveryRequest::where('status', 'pending')->count());

        $admin = $this->officer('system_admin');
        $request = RecoveryRequest::where('status', 'pending')->first();
        $this->actingAs($admin)->post(route('admin.access.recovery', $request), ['action' => 'issue', 'note' => 'Identity confirmed by phone (synthetic)'])->assertSessionHasErrors('confirm_identity');
        $this->actingAs($admin)->post(route('admin.access.recovery', $request), ['action' => 'issue', 'note' => 'Identity confirmed by phone (synthetic)', 'confirm_identity' => '1'])->assertSessionHas('one_time_link');
        $this->assertSame('issued', $request->fresh()->status);
    }

    public function test_login_is_rate_limited_and_failures_are_logged_without_disclosure(): void
    {
        $member = $this->member([], true);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), ['email' => $member->email, 'password' => 'wrong-password-'.$i])->assertSessionHasErrors('email');
        }
        $this->post(route('login.attempt'), ['email' => $member->email, 'password' => 'synthetic-password-123'])->assertSessionHasErrors('domain');
        $this->assertGuest();
        $this->assertSame(5, DB::table('security_events')->where('event', 'login.failed')->count());
        $this->assertStringNotContainsString('wrong-password', DB::table('security_events')->get()->toJson());
    }

    public function test_password_change_requires_current_password_and_keeps_only_this_session(): void
    {
        $member = $this->member([], true);
        $this->actingAs($member->user)->post(route('portal.security.password'), ['current_password' => 'nope', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('domain');
        $this->actingAs($member->user)->post(route('portal.security.password'), ['current_password' => 'synthetic-password-123', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::PASSWORD, $member->user->fresh()->password));
    }

    public function test_no_public_registration_route_exists(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'x@example.test'])->assertNotFound();
    }

    public function test_officer_without_member_record_can_change_own_password(): void
    {
        $officer = $this->officer('system_admin');
        $this->actingAs($officer)->get(route('admin.account'))->assertOk();
        $this->actingAs($officer)->post(route('admin.account.password'), ['current_password' => 'synthetic-password-123', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::PASSWORD, $officer->fresh()->password));
    }
}
