<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\RecoveryRequest;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\AccountService;
use App\Services\TwoFactorService;
use App\Support\Audit;
use App\Support\Normalize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Officer accounts, role assignments and the account-recovery queue.
 *
 * Safeguards: officers cannot change their own roles or status, and the last
 * active account able to manage access can never be removed or disabled.
 */
class AccessController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function index(): View
    {
        return view('admin.access.index', [
            'officers' => User::with('roles', 'member:id,membership_number')->whereHas('roles', fn ($q) => $q->where('is_staff', true))->orderBy('name')->get(),
            'roles' => Role::with('permissions')->orderBy('id')->get(),
            'recovery' => RecoveryRequest::with('user.member')->where('status', 'pending')->oldest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,key'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        $email = Normalize::email($data['email']);
        $roles = Role::whereIn('key', $data['roles'])->where('is_staff', true)->get();
        if ($roles->isEmpty()) {
            throw new DomainRuleException('Choose at least one officer role.');
        }
        // Officer logins are separate from member portal logins (decided 22 Nov 2026): an officer
        // who is also a member keeps his member account and gets a second login with another email.
        $existing = User::where('email', $email)->first();
        if ($existing && $existing->roles()->where('is_staff', true)->exists()) {
            throw new DomainRuleException('This person already has an officer account. Open it to change roles.');
        }
        if ($existing) {
            throw new DomainRuleException('This email address is already used by another account, such as the person’s member portal login. Officer logins are separate: use a different email address for the officer account.');
        }

        $user = DB::transaction(function () use ($email, $data, $roles, $request) {
            $user = User::create(['name' => $data['name'], 'email' => $email, 'status' => 'pending_activation']);
            foreach ($roles as $role) {
                $user->roles()->syncWithoutDetaching([$role->id => ['granted_by' => $request->user()->id, 'reason' => $data['reason'], 'granted_at' => now()]]);
            }
            Audit::record('access.officer_created', "Officer access granted to {$user->name}", $user, ['roles' => $roles->pluck('key')->all(), 'reason' => $data['reason']]);

            return $user;
        });

        $flash = ['status' => "Officer access granted to {$user->name}."];
        if ($user->status === 'pending_activation') {
            $link = $this->accounts->issue($user, 'activation', 'admin', $request->user()->id);
            $message = $this->accounts->prepareLinkMessage($user, 'activation', $link['url'], $link['record']);
            $flash['one_time_link'] = ['url' => $link['url'], 'label' => "Activation link for {$user->name}", 'message_id' => $message->id, 'expires' => 'in '.config('boba.accounts.activation_token_days').' days'];
        }

        return redirect()->route('admin.access.show', $user)->with($flash);
    }

    public function show(User $user): View
    {
        return view('admin.access.show', [
            'user' => $user->load('roles', 'member'),
            'roles' => Role::with('permissions')->orderBy('id')->get(),
            'events' => SecurityEvent::where('user_id', $user->id)->latest('id')->limit(20)->get(),
        ]);
    }

    public function roles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['roles' => ['nullable', 'array'], 'roles.*' => ['string', 'exists:roles,key'], 'reason' => ['required', 'string', 'min:5', 'max:255']]);
        if ($user->id === $request->user()->id) {
            Audit::security('access.self_change_refused', $request->user()->id);
            throw new DomainRuleException('You cannot change your own roles. Ask another administrator.');
        }
        $wanted = Role::whereIn('key', $data['roles'] ?? [])->get();
        if ($user->member_id) {
            if ($wanted->contains('is_staff', true)) {
                throw new DomainRuleException('A member portal account cannot hold officer roles. Officer logins are separate: create an officer account with a different email address.');
            }
            $wanted = $wanted->push(Role::where('key', 'member')->first())->unique('id');
        }

        DB::transaction(function () use ($user, $wanted, $data, $request) {
            // Serialise all access changes so two administrators cannot remove each other concurrently.
            DB::table('roles')->lockForUpdate()->get();
            $before = $user->roles()->pluck('key')->sort()->values()->all();
            $sync = $wanted->mapWithKeys(fn ($r) => [$r->id => ['granted_by' => $request->user()->id, 'reason' => $data['reason'], 'granted_at' => now()]])->all();
            $user->roles()->sync($sync);
            $this->assertAccessManagerRemains();
            $after = $user->roles()->pluck('key')->sort()->values()->all();
            Audit::record('access.roles_changed', "Roles changed for {$user->name}", $user, ['from' => $before, 'to' => $after, 'reason' => $data['reason']]);
            Audit::security('access.roles_changed', $user->id, ['from' => $before, 'to' => $after, 'by' => $request->user()->id]);
        });
        $this->accounts->endAllSessions($user);

        return redirect()->route('admin.access.show', $user)->with('status', 'Roles updated. The officer has been signed out so the change takes effect immediately.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended,disabled'], 'reason' => ['required', 'string', 'min:5', 'max:255']]);
        if ($user->id === $request->user()->id) {
            throw new DomainRuleException('You cannot change the status of your own account.');
        }
        if ($data['status'] === 'active' && $user->status === 'pending_activation') {
            throw new DomainRuleException('This account has not been activated yet. Issue an activation link instead.');
        }
        DB::transaction(function () use ($user, $data) {
            DB::table('roles')->lockForUpdate()->get();
            $user->update(['status' => $data['status']]);
            $this->assertAccessManagerRemains();
            Audit::record('access.status_changed', "Account {$data['status']} for {$user->name}", $user, ['reason' => $data['reason']]);
        });
        if ($data['status'] !== 'active') {
            $this->accounts->endAllSessions($user);
        }

        return redirect()->route('admin.access.show', $user)->with('status', 'Account status updated.');
    }

    public function link(Request $request, User $user): RedirectResponse
    {
        $request->validate(['confirm_identity' => ['accepted']], ['confirm_identity.accepted' => 'Confirm that you have verified this person’s identity.']);
        $purpose = $user->status === 'pending_activation' ? 'activation' : 'password_reset';
        $link = $this->accounts->issue($user, $purpose, 'admin', $request->user()->id);
        $message = $this->accounts->prepareLinkMessage($user, $purpose, $link['url'], $link['record']);

        return redirect()->route('admin.access.show', $user)->with('one_time_link', ['url' => $link['url'], 'label' => ucfirst(str_replace('_', ' ', $purpose))." link for {$user->name}", 'message_id' => $message->id, 'expires' => $purpose === 'activation' ? 'in '.config('boba.accounts.activation_token_days').' days' : 'in '.config('boba.accounts.reset_token_minutes').' minutes']);
    }

    public function twoFactorReset(Request $request, User $user, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255'], 'confirm_identity' => ['accepted']], ['confirm_identity.accepted' => 'Confirm that you have verified this person’s identity.']);
        $twoFactor->adminReset($user, $request->user(), $data['reason']);

        return redirect()->route('admin.access.show', $user)->with('status', 'Two-factor sign-in reset. The officer must set it up again at their next sign-in.');
    }

    public function recovery(Request $request, RecoveryRequest $recovery): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:issue,dismiss'], 'note' => ['required', 'string', 'min:5', 'max:255'], 'confirm_identity' => ['required_if:action,issue', 'accepted_if:action,issue']]);
        if ($recovery->status !== 'pending') {
            throw new DomainRuleException('This request has already been handled.');
        }
        if ($data['action'] === 'dismiss' || ! $recovery->user) {
            $recovery->update(['status' => 'dismissed', 'handled_by' => $request->user()->id, 'handled_at' => now(), 'note' => $data['note']]);
            Audit::record('recovery.dismissed', 'Account recovery request dismissed', $recovery, ['note' => $data['note']]);

            return redirect()->route('admin.access.index')->with('status', 'Request dismissed.');
        }
        $user = $recovery->user;
        $purpose = $user->status === 'pending_activation' ? 'activation' : 'password_reset';
        $link = $this->accounts->issue($user, $purpose, 'admin', $request->user()->id);
        $message = $this->accounts->prepareLinkMessage($user, $purpose, $link['url'], $link['record']);
        $recovery->update(['status' => 'issued', 'handled_by' => $request->user()->id, 'handled_at' => now(), 'note' => $data['note']]);

        return redirect()->route('admin.access.index')->with('one_time_link', ['url' => $link['url'], 'label' => "Recovery link for {$user->name}", 'message_id' => $message->id, 'expires' => $purpose === 'activation' ? 'in '.config('boba.accounts.activation_token_days').' days' : 'in '.config('boba.accounts.reset_token_minutes').' minutes']);
    }

    private function assertAccessManagerRemains(): void
    {
        $remaining = User::where('status', 'active')
            ->whereHas('roles.permissions', fn ($q) => $q->where('key', 'access.manage'))
            ->get()->filter(fn (User $u) => $u->isActive())->count();
        if ($remaining < 1) {
            throw new DomainRuleException('This change would leave no active administrator able to manage access. Grant the role to another officer first.');
        }
    }
}
