<?php

namespace Tests\Feature\Http;

use App\Models\Assessment;
use App\Models\BackupRun;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\FeeCategory;
use App\Models\Gallery;
use App\Models\ImportBatch;
use App\Models\OutboundMessage;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PaymentAllocation;
use App\Models\Post;
use App\Models\RecoveryRequest;
use App\Models\SystemAlert;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\Finance\PaymentService;
use App\Support\PermissionCatalog;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteObject;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every administrative mutating route (POST/PUT/PATCH/DELETE) is called
 * through HTTP by every role and anonymously, with real records bound to
 * its parameters. The server must:
 *   - redirect anonymous callers to sign-in;
 *   - return 403 exactly when the role lacks the route's permission;
 *   - never fail with a server error for a permitted role (an empty body
 *     must be rejected by validation or a business rule, not crash);
 *   - reject any request without a CSRF token when forgery protection is active.
 * Results are written to storage/logs/admin-mutation-matrix.json, which the
 * coverage document generator reads.
 */
class AdminMutationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private array $params = [];

    /** @return array<string, RouteObject> */
    public static function mutatingAdminRoutes(): array
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name && str_starts_with($name, 'admin.') && array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())) {
                $routes[$name] = $route;
            }
        }
        ksort($routes);

        return $routes;
    }

    public static function requiredPermissions(RouteObject $route): ?array
    {
        $mw = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));

        return $mw ? explode('|', substr($mw, 11)) : null;
    }

    private function fixtures(): void
    {
        $treasurer = $this->officer('treasurer');
        $verifier = $this->officer('payment_verifier');
        $member = $this->member([], true);
        $assessment = $this->assess([$member], '50000', $treasurer);
        $payment = $this->verifiedPayment($member, '20000', $treasurer, $verifier);
        app(PaymentService::class)->allocate($payment, [$this->obligationOf($member, $assessment)->id => 100], $treasurer, $this->key());
        [$application] = app(ApplicationService::class)->submit(['first_name' => 'Matrix', 'last_name' => 'Applicant', 'email' => 'matrix@example.test', 'phone' => '08077770001', 'date_of_birth' => '1981-01-01', 'ijebu_town' => 'Ijebu Ode', 'city' => 'Lagos', 'occupation' => 'Synthetic', 'reason' => 'Synthetic matrix application for route coverage.']);
        $draft = Assessment::create(['reference' => 'ASM-MATRIX', 'fee_category_id' => FeeCategory::first()->id, 'title' => 'Draft', 'amount_kobo' => 100, 'effective_on' => now()->toDateString(), 'status' => 'draft', 'target_mode' => 'all_active', 'created_by' => $treasurer->id]);
        $memberBatch = ImportBatch::create(['reference' => 'IMP-MATRIX', 'kind' => 'members', 'status' => 'previewed', 'original_filename' => 'm.csv', 'file_sha256' => str_repeat('a', 64), 'created_by' => $treasurer->id]);
        $financeBatch = ImportBatch::create(['reference' => 'FIN-MATRIX', 'kind' => 'finance_transactions', 'status' => 'previewed', 'original_filename' => 'f.csv', 'file_sha256' => str_repeat('b', 64), 'created_by' => $treasurer->id]);
        $page = Page::where('key', 'about')->first();
        $revision = PageRevision::create(['page_id' => $page->id, 'title' => 'About', 'body' => 'Synthetic']);
        $post = Post::create(['type' => 'news', 'audience' => 'public', 'slug' => 'matrix-news', 'title' => 'Matrix', 'body' => 'Body', 'status' => 'draft']);
        $gallery = Gallery::create(['slug' => 'matrix-gallery', 'title' => 'Matrix gallery', 'status' => 'draft']);
        $event = Event::create(['slug' => 'matrix-event', 'title' => 'Matrix event', 'starts_at' => now()->addWeek(), 'audience' => 'public', 'status' => 'draft']);
        $enquiry = Enquiry::create(['reference' => 'ENQ-MATRIX', 'name' => 'Visitor', 'email' => 'v@example.test', 'topic' => 'general', 'subject' => 'Hi', 'message' => 'Hello there', 'consent_at' => now()]);
        $message = OutboundMessage::create(['purpose' => 'test', 'recipient_name' => 'X', 'subject' => 'S', 'body' => 'B', 'status' => 'pending']);
        $recovery = RecoveryRequest::create(['identifier_hash' => str_repeat('c', 64), 'user_id' => $member->user->id, 'status' => 'pending']);
        $alert = SystemAlert::create(['type' => 'test', 'severity' => 'warning', 'fingerprint' => 'matrix', 'title' => 'Matrix alert', 'summary' => 'Synthetic', 'first_detected_at' => now(), 'last_detected_at' => now()]);
        $backup = BackupRun::create(['kind' => 'backup', 'status' => 'succeeded', 'file_name' => 'none.bobabk']);

        $this->params = [
            'application' => $application->id, 'message' => $message->id, 'member' => $member->id,
            'obligation' => $this->obligationOf($member, $assessment)->id, 'category' => FeeCategory::first()->id,
            'assessment' => $draft->id, 'payment' => $payment->id, 'allocation' => PaymentAllocation::first()->id,
            'page' => $page->id, 'revision' => $revision->id, 'event' => $event->id, 'enquiry' => $enquiry->id,
            'user' => $treasurer->id, 'recovery' => $recovery->id, 'key' => 'approval.member_import',
            'alert' => $alert->id, 'run' => $backup->id, 'report' => 'balances',
        ];
        $this->batches = ['members' => $memberBatch->id, 'finance' => $financeBatch->id];
        $this->contentIds = ['news' => $post->id, 'galleries' => $gallery->id];
    }

    private array $batches = [];

    private array $contentIds = [];

    private function url(string $name, RouteObject $route): string
    {
        $params = [];
        foreach ($route->parameterNames() as $p) {
            $params[$p] = match (true) {
                $p === 'batch' => str_contains($name, '.finance.') ? $this->batches['finance'] : $this->batches['members'],
                $p === 'type' => str_contains($name, 'galleries') ? 'galleries' : 'news',
                $p === 'id' => str_contains($name, 'galleries') ? $this->contentIds['galleries'] : $this->contentIds['news'],
                default => $this->params[$p] ?? throw new \RuntimeException("No fixture for parameter {$p} of {$name}"),
            };
        }

        return route($name, $params);
    }

    public function test_every_admin_mutation_enforces_roles_server_side(): void
    {
        $this->fixtures();
        $routes = self::mutatingAdminRoutes();
        $this->assertGreaterThanOrEqual(50, count($routes));
        $results = [];
        $users = [];
        foreach (PermissionCatalog::roles() as $key => $role) {
            $users[$key] = $key === 'member' ? $this->member([], true)->user : $this->officer($key);
        }

        foreach ($routes as $name => $route) {
            $url = $this->url($name, $route);
            $required = self::requiredPermissions($route);
            $this->app['auth']->forgetGuards();
            $anonymous = $this->post($url)->getStatusCode();
            $this->assertSame(302, $anonymous, "Anonymous POST to {$name}");
            $row = ['route' => $name, 'uri' => '/'.$route->uri(), 'permission' => $required ? implode(' or ', $required) : 'any active officer', 'anonymous' => $anonymous];
            foreach (PermissionCatalog::roles() as $key => $role) {
                $allowed = $role['is_staff'] && ($required === null || array_intersect($required, $role['permissions']));
                $status = $this->actingAs(User::find($users[$key]->id))->post($url)->getStatusCode();
                if ($allowed) {
                    $this->assertNotContains($status, [403, 404, 405, 500], "{$key} should be able to call {$name} (got {$status})");
                } else {
                    $this->assertSame(403, $status, "{$key} must be denied {$name}");
                }
                $row[$key] = $status;
            }
            $results[] = $row;
        }

        @mkdir(storage_path('logs'), 0775, true);
        file_put_contents(storage_path('logs/admin-mutation-matrix.json'), json_encode(['executed_at' => now()->toIso8601String(), 'routes' => $results], JSON_PRETTY_PRINT));
    }

    public function test_every_admin_mutation_rejects_missing_csrf_token(): void
    {
        $this->fixtures();
        $officer = $this->officer(array_keys(array_filter(PermissionCatalog::roles(), fn ($r) => $r['is_staff'])));
        $this->app['env'] = 'production'; // forgery protection is only bypassed in the testing environment
        foreach (self::mutatingAdminRoutes() as $name => $route) {
            $this->actingAs($officer)->post($this->url($name, $route))->assertStatus(419);
        }
        $this->assertTrue(in_array(PreventRequestForgery::class, app(Kernel::class)->getMiddlewareGroups()['web'], true));
    }
}
