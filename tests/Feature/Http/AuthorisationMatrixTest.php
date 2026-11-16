<?php

namespace Tests\Feature\Http;

use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every parameter-free administration page is requested by every role. A
 * role may open a page only if it holds the permission the route requires;
 * hiding menu items is never relied upon.
 */
class AuthorisationMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string|null> route name => required permissions (null = any officer) */
    private function adminRoutes(): array
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true) || $route->parameterNames()) {
                continue;
            }
            $permission = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));
            $routes[$name] = $permission ? substr($permission, 11) : null;
        }

        return $routes;
    }

    public function test_every_role_reaches_exactly_the_admin_pages_it_is_permitted(): void
    {
        $routes = $this->adminRoutes();
        $this->assertGreaterThanOrEqual(25, count($routes));
        foreach (PermissionCatalog::roles() as $key => $role) {
            $user = $key === 'member' ? $this->member([], true)->user : $this->officer($key);
            foreach ($routes as $name => $required) {
                $allowed = $role['is_staff'] && ($required === null || array_intersect(explode('|', $required), $role['permissions']));
                $response = $this->actingAs($user)->get(route($name));
                if ($allowed) {
                    $this->assertContains($response->getStatusCode(), [200], "{$key} should open {$name}");
                } else {
                    $this->assertContains($response->getStatusCode(), [403, 302], "{$key} must not open {$name} (got {$response->getStatusCode()})");
                }
            }
        }
    }

    public function test_anonymous_visitors_are_redirected_from_every_private_page(): void
    {
        foreach (array_keys($this->adminRoutes()) as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
        foreach (['portal.dashboard', 'portal.finances', 'portal.statement', 'portal.profile', 'portal.security', 'portal.notices', 'portal.events', 'portal.documents'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    public function test_system_administrator_has_no_treasury_or_approval_powers(): void
    {
        $admin = $this->officer('system_admin');
        $member = $this->member();
        $this->actingAs($admin)->get(route('admin.finance.payments.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.finance.members.show', $member))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.finance.payments.store'), ['member_id' => $member->id, 'amount' => '1', 'method' => 'cash', 'received_on' => now()->toDateString(), 'idempotency_key' => $this->key()])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.applications.index'))->assertForbidden();
        foreach (['finance.payments.verify', 'finance.reverse', 'finance.assessments.manage', 'applications.review'] as $permission) {
            $this->assertFalse($admin->hasPermission($permission));
        }
    }

    public function test_membership_officer_sees_no_finance_and_executive_is_read_only(): void
    {
        $member = $this->member();
        $officer = $this->officer('membership_officer');
        $this->actingAs($officer)->get(route('admin.members.show', $member))->assertOk()->assertDontSee('Outstanding');
        $this->actingAs($officer)->get(route('admin.finance.members.show', $member))->assertForbidden();
        $executive = $this->officer('executive');
        $this->actingAs($executive)->get(route('admin.finance.members.show', $member))->assertOk();
        $this->actingAs($executive)->post(route('admin.members.status', $member), ['status' => 'suspended', 'reason' => 'Not allowed'])->assertForbidden();
        $this->actingAs($executive)->get(route('admin.members.export'))->assertForbidden();
    }

    public function test_member_cannot_reach_another_members_records(): void
    {
        $alice = $this->member([], true);
        $bob = $this->member([], true);
        $assessment = $this->assess([$alice, $bob], '50000');
        $this->actingAs($alice->user)->get(route('portal.finances'))->assertOk()->assertSee('Synthetic assessment');
        $this->actingAs($alice->user)->get(route('admin.members.show', $bob))->assertForbidden();
        $this->actingAs($alice->user)->get(route('admin.finance.members.show', $bob))->assertForbidden();
        $this->actingAs($alice->user)->get(route('admin.finance.reports.export', 'balances'))->assertForbidden();
        $this->actingAs($alice->user)->get(route('portal.statement'))->assertOk()->assertDontSee($bob->membership_number)->assertDontSee($bob->full_name);
        $this->actingAs($alice->user)->get(route('admin.members.export'))->assertForbidden();
    }

    public function test_unauthorised_exports_are_denied(): void
    {
        foreach (['membership_officer', 'payment_verifier', 'system_admin'] as $role) {
            $this->actingAs($this->officer($role))->get(route('admin.finance.reports.export', 'balances'))->assertForbidden();
        }
        foreach (['membership_officer', 'treasurer', 'system_admin'] as $role) {
            $this->actingAs($this->officer($role))->get(route('admin.members.export'))->assertForbidden();
        }
        $this->actingAs($this->officer('treasurer'))->get(route('admin.finance.reports.export', 'balances'))->assertOk();
        $this->actingAs($this->officer('secretariat'))->get(route('admin.members.export'))->assertOk();
        $this->assertDatabaseHas('security_events', ['event' => 'access.denied']);
    }

    public function test_role_escalation_and_last_administrator_protection(): void
    {
        $admin = $this->officer('system_admin');
        $this->actingAs($admin)->post(route('admin.access.roles', $admin), ['roles' => ['system_admin', 'treasurer'], 'reason' => 'Self escalation attempt'])->assertSessionHasErrors('domain');
        $this->assertFalse($admin->fresh()->hasPermission('finance.read'));

        $other = $this->officer('system_admin');
        $this->actingAs($admin)->post(route('admin.access.roles', $other), ['roles' => ['secretariat'], 'reason' => 'Rotate duties'])->assertSessionHasNoErrors();
        // A different administrator disables the first one; that is allowed while others remain.
        $third = $this->officer('system_admin');
        $this->actingAs($third)->post(route('admin.access.status', $admin), ['status' => 'disabled', 'reason' => 'Synthetic test reason'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('login'));
        // $third is now the last active access manager; removing their role must fail.
        $this->actingAs($other)->get(route('admin.access.index'))->assertForbidden();
        $fourth = $this->officer('secretariat');
        $this->actingAs($third)->post(route('admin.access.roles', $fourth), ['roles' => ['system_admin'], 'reason' => 'Promote'])->assertSessionHasNoErrors();
        $this->actingAs($fourth->fresh())->post(route('admin.access.roles', $third), ['roles' => ['secretariat'], 'reason' => 'Demote'])->assertSessionHasNoErrors();
        $this->actingAs($third->fresh())->get(route('admin.access.index'))->assertForbidden();
        $this->actingAs($fourth->fresh())->post(route('admin.access.status', $fourth), ['status' => 'disabled', 'reason' => 'Self disable attempt'])->assertSessionHasErrors('domain');
        $remaining = \App\Models\User::where('status', 'active')->get()->filter(fn ($u) => $u->hasPermission('access.manage'));
        $this->assertCount(1, $remaining);
    }
}
