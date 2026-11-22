<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Settings;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_rfc_6238_reference_vectors(): void
    {
        // RFC 6238 Appendix B, SHA-1 secret "12345678901234567890"; last 6 digits of the 8-digit values.
        $secret = Totp::base32Encode('12345678901234567890');
        foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037', 20000000000 => '353130'] as $time => $code) {
            $this->assertSame($code, Totp::code($secret, intdiv($time, 30)), "t={$time}");
        }
        $this->assertSame('12345678901234567890', Totp::base32Decode($secret));
    }

    private function enrol(User $user): array
    {
        $this->actingAs($user)->post(route('admin.account.two-factor.start'))->assertSessionHas('two_factor_setup');
        $secret = session('two_factor.pending_secret');
        $this->actingAs($user)->post(route('admin.account.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('domain');
        $this->actingAs($user)->post(route('admin.account.two-factor.confirm'), ['code' => Totp::code($secret, Totp::currentStep())])->assertSessionHas('recovery_codes');
        $codes = session('recovery_codes');
        $this->post(route('logout'));

        return [$secret, $codes];
    }

    public function test_enrolment_stores_encrypted_secret_and_hashed_recovery_codes(): void
    {
        $officer = $this->officer('treasurer');
        [$secret, $codes] = $this->enrol($officer);
        $this->assertCount(8, $codes);
        $raw = DB::table('users')->where('id', $officer->id)->first();
        $this->assertNotNull($raw->two_factor_confirmed_at);
        $this->assertStringNotContainsString($secret, (string) $raw->two_factor_secret, 'Secret must be encrypted at rest');
        $this->assertStringNotContainsString($codes[0], (string) $raw->two_factor_recovery_codes);
        $this->assertStringNotContainsString($secret, AuditEvent::get()->toJson().SecurityEvent::get()->toJson());
    }

    public function test_sign_in_requires_code_rejects_replay_and_uses_recovery_codes_once(): void
    {
        $officer = $this->officer('treasurer');
        [$secret, $codes] = $this->enrol($officer);
        // Password alone does not authenticate.
        $this->post(route('login.attempt'), ['email' => $officer->email, 'password' => 'synthetic-password-123'])->assertRedirect(route('login.two-factor'));
        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->post(route('login.two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
        // The enrolment code's time step was consumed; use the next step's code.
        $code = Totp::code($secret, Totp::currentStep() + 1);
        $this->post(route('login.two-factor.verify'), ['code' => $code])->assertRedirect();
        $this->assertAuthenticatedAs($officer);
        $this->post(route('logout'));
        // Replay of the same code is refused.
        $this->post(route('login.attempt'), ['email' => $officer->email, 'password' => 'synthetic-password-123']);
        $this->post(route('login.two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('security_events', ['event' => 'two_factor.code_replay_rejected', 'user_id' => $officer->id]);
        // Recovery code works once.
        $this->post(route('login.two-factor.verify'), ['code' => strtolower($codes[0])])->assertRedirect();
        $this->assertAuthenticatedAs($officer);
        $this->post(route('logout'));
        $this->post(route('login.attempt'), ['email' => $officer->email, 'password' => 'synthetic-password-123']);
        $this->post(route('login.two-factor.verify'), ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertSame(7, count($officer->fresh()->two_factor_recovery_codes));
    }

    public function test_challenge_expires_and_locks_after_repeated_failures(): void
    {
        $officer = $this->officer('treasurer');
        $this->enrol($officer);
        $this->post(route('login.attempt'), ['email' => $officer->email, 'password' => 'synthetic-password-123']);
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login.two-factor.verify'), ['code' => '00000'.$i])->assertSessionHasErrors('code');
        }
        $this->post(route('login.two-factor.verify'), ['code' => '999999'])->assertRedirect(route('login'));
        $this->get(route('login.two-factor'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.attempt'), ['email' => $officer->email, 'password' => 'synthetic-password-123']);
        session()->put('two_factor.login', ['user_id' => $officer->id, 'expires' => time() - 1, 'attempts' => 0]);
        $this->post(route('login.two-factor.verify'), ['code' => '123456'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_enforcement_cannot_be_enabled_while_privileged_officers_are_unenrolled_and_then_applies(): void
    {
        // The wider scope (finance officers included), which the association may switch on later.
        Settings::set('security.two_factor_include_finance', true, null);
        $admin = $this->officer('system_admin');
        $treasurer = $this->officer('treasurer');
        $field = ['security__require_two_factor' => '1', 'security__two_factor_include_finance' => '1', 'membership__number_width' => '6'];
        $this->actingAs($admin)->post(route('admin.settings.update'), $field)->assertSessionHasErrors('security__require_two_factor');
        $this->assertFalse(Settings::bool('security.require_two_factor'));

        $this->enrol($admin);
        // Treasurer still unenrolled -> still refused (lockout prevention).
        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin)->post(route('admin.settings.update'), $field)->assertSessionHasErrors('security__require_two_factor');
        // Simulate the treasurer enrolling, then enable; then a new unenrolled privileged officer is redirected to enrol.
        $treasurer->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => Totp::newSecret()])->save();
        $this->actingAs($admin)->post(route('admin.settings.update'), $field)->assertSessionHasNoErrors();
        $this->assertTrue(Settings::bool('security.require_two_factor'));
        $newcomer = $this->officer('payment_verifier');
        $this->actingAs($newcomer)->get(route('admin.finance.index'))->assertRedirect(route('admin.account'));
        $this->actingAs($newcomer)->get(route('admin.account'))->assertOk();
        // A non-privileged officer is not forced.
        $this->actingAs($this->officer('membership_officer'))->get(route('admin.dashboard'))->assertOk();
    }

    public function test_admin_reset_for_lost_device_never_self_and_ends_sessions(): void
    {
        $admin = $this->officer('system_admin');
        $officer = $this->officer('treasurer');
        $this->enrol($officer);
        $this->actingAs($officer)->post(route('admin.access.two-factor.reset', $officer), ['reason' => 'Lost phone', 'confirm_identity' => '1'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.access.two-factor.reset', $admin), ['reason' => 'Self reset', 'confirm_identity' => '1'])->assertSessionHasErrors('domain');
        $this->actingAs($admin)->post(route('admin.access.two-factor.reset', $officer), ['reason' => 'Lost phone'])->assertSessionHasErrors('confirm_identity');
        DB::table('sessions')->insert(['id' => 'tf-session', 'user_id' => $officer->id, 'payload' => 'x', 'last_activity' => time()]);
        $this->actingAs($admin)->post(route('admin.access.two-factor.reset', $officer), ['reason' => 'Lost phone, identity confirmed by phone', 'confirm_identity' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($officer->fresh()->two_factor_confirmed_at);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $officer->id)->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'two_factor.reset', 'subject_id' => $officer->id]);
    }

    public function test_disable_requires_password_and_code(): void
    {
        $officer = $this->officer('membership_officer');
        [$secret] = $this->enrol($officer);
        $this->actingAs($officer)->post(route('admin.account.two-factor.disable'), ['password' => 'wrong', 'code' => Totp::code($secret, Totp::currentStep() + 1)])->assertSessionHasErrors('domain');
        $this->actingAs($officer)->post(route('admin.account.two-factor.disable'), ['password' => 'synthetic-password-123', 'code' => Totp::code($secret, Totp::currentStep() + 1)])->assertSessionHasNoErrors();
        $this->assertNull($officer->fresh()->two_factor_confirmed_at);
    }

    public function test_by_default_two_factor_covers_administrators_only(): void
    {
        $admin = $this->officer('system_admin');
        $treasurer = $this->officer('treasurer');
        // Only the administrator needs to enrol before enforcement can be switched on.
        $admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => Totp::newSecret()])->save();
        $this->actingAs($admin)->post(route('admin.settings.update'), ['security__require_two_factor' => '1', 'membership__number_width' => '6'])->assertSessionHasNoErrors();
        $this->assertTrue(Settings::bool('security.require_two_factor'));
        // Finance officers and members are not forced; a new administrator is.
        $this->actingAs($treasurer)->get(route('admin.finance.index'))->assertOk();
        $this->actingAs($this->officer('system_admin'))->get(route('admin.dashboard'))->assertRedirect(route('admin.account'));
        $this->actingAs($this->member([], true)->user)->get(route('portal.dashboard'))->assertOk();
    }
}
