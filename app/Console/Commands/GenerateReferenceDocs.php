<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as Router;

/**
 * Regenerates docs/04-database-schema.md and docs/17-route-inventory.md from
 * the live schema and router so they cannot drift from the code. Run after
 * any migration or route change: `php artisan boba:docs:reference`.
 */
class GenerateReferenceDocs extends Command
{
    protected $signature = 'boba:docs:reference';

    protected $description = 'Regenerate the schema and route reference documents from the running application';

    private const TABLES = [
        'users' => 'Authenticatable accounts for members and officers. Never deleted (trigger); status pending_activation → active → suspended/disabled. Password is null until single-use activation.',
        'sessions' => 'Database-backed sessions (allows “sign out other devices” and immediate termination on suspension or reset). Excluded from backup contents.',
        'cache' => 'Rate-limiter and cache store (no Redis on shared hosting). Excluded from backup contents.',
        'cache_locks' => 'Atomic cache locks. Excluded from backup contents.',
        'roles' => 'Role templates (docs/15-roles-and-permissions.md). Assignment templates only; checks always use permissions.',
        'permissions' => 'The 24 named permissions checked by routes and services.',
        'permission_role' => 'Which permissions each role grants (synchronised from App\\Support\\PermissionCatalog).',
        'role_user' => 'Role assignments with granting officer, reason and time.',
        'audit_events' => 'Append-only administrative audit trail (update/delete blocked by triggers). Context is redacted of secrets.',
        'security_events' => 'Append-only authentication and access-control events (failed logins, denials, resets).',
        'settings' => 'Typed organisation settings and launch-approval gates. Never holds secrets.',
        'idempotency_keys' => 'Exactly-once execution keys for financial and import mutations (unique scope+key).',
        'private_files' => 'Uploaded evidence/documents stored outside the webroot; served only through authorised downloads.',
        'backup_runs' => 'Register of encrypted backups and restore verifications.',
        'membership_number_sequences' => 'Locked sequence row used to issue membership numbers.',
        'membership_numbers' => 'Every membership number ever issued. Immutable and undeletable, so numbers can never be reused.',
        'import_batches' => 'Member and financial import previews/commits with file hash; a file can be committed once per kind.',
        'import_rows' => 'Row-level validation results and the record each committed row created.',
        'membership_applications' => 'Public applications with screening result, private status-token hash and review state. One open application per email/phone (generated-column unique keys).',
        'application_events' => 'Append-only review timeline: private notes and applicant-visible messages kept separately.',
        'members' => 'The membership register. Never deleted; membership number immutable and FK-bound to membership_numbers.',
        'account_tokens' => 'SHA-256 hashes of single-use activation and reset tokens with expiry, consumption and revocation.',
        'outbound_messages' => 'Prepared notifications and their honest delivery state (manual attestation or SMTP acceptance). Never contain raw tokens.',
        'recovery_requests' => 'Enumeration-safe account recovery queue handled by officers after identity verification.',
        'fee_categories' => 'Dues/levy categories that label assessments.',
        'assessments' => 'Draft → posted/discarded charges. Posted assessments are immutable (trigger).',
        'member_obligations' => 'One immutable amount owed per member per posted assessment.',
        'obligation_adjustments' => 'Append-only signed waivers, corrections, increases and cancellations.',
        'payments' => 'Money received: pending → verified/rejected; verified → reversed only via a reversal record. Financial fields immutable.',
        'financial_reversals' => 'Append-only reversal records for allocations and payments; inserting one performs the linked status change atomically.',
        'payment_allocations' => 'Application of verified funds to the same member’s obligations. Composite foreign keys forbid cross-member allocation.',
        'media' => 'Re-encoded public images under public/media.',
        'pages' => 'Managed public pages with a pointer to the published revision.',
        'page_revisions' => 'Immutable page drafts; publishing selects one.',
        'posts' => 'Public news and member-only notices with draft/published/archived states.',
        'announcement_reads' => 'Which member has read which notice.',
        'events' => 'Public or member events with publication state and RSVP settings.',
        'event_rsvps' => 'One response per member per event.',
        'activities' => 'Published association activities.',
        'leaders' => 'Leadership directory entries (published only when approved).',
        'galleries' => 'Photo galleries.',
        'gallery_items' => 'Images within galleries.',
        'documents' => 'Member-only downloadable documents (private files).',
        'enquiries' => 'Contact-form enquiries with reference, status and assignee.',
        'enquiry_notes' => 'Officer notes on enquiries.',
        'migrations' => 'Laravel migration ledger.',
    ];

    public function handle(): int
    {
        $this->writeSchema();
        $this->writeRoutes();
        $this->info('Regenerated docs/04-database-schema.md and docs/17-route-inventory.md');

        return self::SUCCESS;
    }

    private function writeSchema(): void
    {
        $db = DB::getDatabaseName();
        $out = "# Database schema\n\n_Generated by `php artisan boba:docs:reference` from the live MariaDB schema on ".now()->toDateString()." — do not edit by hand._\n\n";
        $out .= 'Engine: InnoDB · charset utf8mb4 · collation utf8mb4_unicode_ci · money: signed BIGINT kobo · timestamps: UTC. Integrity triggers are listed in [05-database-integrity-safeguards.md](05-database-integrity-safeguards.md).'."\n\n";
        $tables = collect(DB::select("SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME", [$db]))->pluck('t');
        $out .= '**'.$tables->count()." tables.**\n\n";
        foreach ($tables as $table) {
            $out .= "## `{$table}`\n\n".(self::TABLES[$table] ?? '_Purpose not documented._')."\n\n| Column | Type | Null | Default / generated |\n|---|---|---|---|\n";
            foreach (DB::select('SELECT COLUMN_NAME c, COLUMN_TYPE t, IS_NULLABLE n, COLUMN_DEFAULT d, GENERATION_EXPRESSION g FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$db, $table]) as $c) {
                $extra = $c->g ? 'generated: `'.str_replace('|', '\|', $c->g).'`' : ($c->d !== null ? '`'.$c->d.'`' : '');
                $out .= "| `{$c->c}` | `{$c->t}` | {$c->n} | {$extra} |\n";
            }
            $indexes = collect(DB::select('SELECT INDEX_NAME i, NON_UNIQUE u, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? GROUP BY INDEX_NAME, NON_UNIQUE ORDER BY INDEX_NAME', [$db, $table]));
            $out .= "\n**Indexes:** ".$indexes->map(fn ($i) => ($i->u ? '' : 'UNIQUE ')."`{$i->i}`({$i->cols})")->implode('; ')."\n";
            $fks = collect(DB::select('SELECT CONSTRAINT_NAME n, GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) cols, REFERENCED_TABLE_NAME rt, GROUP_CONCAT(REFERENCED_COLUMN_NAME ORDER BY ORDINAL_POSITION) rcols FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL GROUP BY CONSTRAINT_NAME, REFERENCED_TABLE_NAME', [$db, $table]));
            if ($fks->isNotEmpty()) {
                $out .= "\n**Foreign keys (all RESTRICT):** ".$fks->map(fn ($f) => "({$f->cols}) → `{$f->rt}`({$f->rcols})")->implode('; ')."\n";
            }
            $checks = collect(DB::select('SELECT CONSTRAINT_NAME n, CHECK_CLAUSE c FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ?', [$db, $table]))
                ->reject(fn ($c) => str_contains($c->c, 'json_valid'));
            if ($checks->isNotEmpty()) {
                $out .= "\n**Check constraints:** ".$checks->map(fn ($c) => "`{$c->n}`: `".str_replace('|', '\|', $c->c).'`')->implode('; ')."\n";
            }
            $triggers = collect(DB::select('SELECT TRIGGER_NAME n, ACTION_TIMING t, EVENT_MANIPULATION e FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? AND EVENT_OBJECT_TABLE = ? ORDER BY TRIGGER_NAME', [$db, $table]));
            if ($triggers->isNotEmpty()) {
                $out .= "\n**Triggers:** ".$triggers->map(fn ($t) => "`{$t->n}` ({$t->t} {$t->e})")->implode('; ')."\n";
            }
            $out .= "\n";
        }
        file_put_contents(base_path('docs/04-database-schema.md'), $out);
    }

    private function writeRoutes(): void
    {
        $rows = collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => $r->getName() && ! str_starts_with($r->uri(), '_') && $r->uri() !== 'up' && ! str_starts_with($r->getName(), 'storage.'))
            ->map(function (Route $r) {
                $mw = collect($r->gatherMiddleware())->map(fn ($m) => is_string($m) ? $m : '');
                $permission = $mw->first(fn ($m) => str_starts_with($m, 'permission:'));
                $access = match (true) {
                    (bool) $permission => 'Officer with `'.str_replace('|', '` or `', substr($permission, 11)).'`',
                    $mw->contains('staff') => 'Any active officer',
                    $mw->contains('member') => 'Signed-in member (own records only)',
                    $mw->contains('guest') => 'Signed-out visitors',
                    $mw->contains('auth') => 'Any signed-in account',
                    default => 'Public',
                };
                $throttle = $mw->first(fn ($m) => str_starts_with($m, 'throttle:'));
                $area = explode('.', $r->getName())[0];

                return [
                    'area' => match ($area) {
                        'admin' => 'Administration', 'portal' => 'Member portal', 'login', 'logout', 'password', 'account' => 'Authentication', default => 'Public website'
                    },
                    'methods' => implode('|', array_diff($r->methods(), ['HEAD'])),
                    'uri' => '/'.ltrim($r->uri(), '/'),
                    'name' => $r->getName(),
                    'action' => class_basename(explode('@', $r->getActionName())[0]).'@'.(explode('@', $r->getActionName())[1] ?? ''),
                    'access' => $access.($throttle ? ' · rate limit `'.substr($throttle, 9).'`' : '').(in_array('POST', $r->methods(), true) ? ' · CSRF + origin check' : ''),
                ];
            })->sortBy(['area', 'uri'])->groupBy('area');

        $out = "# Route inventory\n\n_Generated by `php artisan boba:docs:reference` on ".now()->toDateString()." from the live router — do not edit by hand._\n\n";
        $out .= 'Access is enforced server-side by middleware on every route and again inside the services for financial and membership decisions; menus only hide links. Behaviour, validation, states and acceptance criteria for each workflow are in the procedure documents and the tests named in [03-requirements-traceability.md](03-requirements-traceability.md). '
            ."Every authenticated response is `Cache-Control: no-store` and `noindex`.\n\n";
        foreach ($rows as $area => $routes) {
            $out .= "## {$area} ({$routes->count()} routes)\n\n| Method | Path | Name | Handler | Access |\n|---|---|---|---|---|\n";
            foreach ($routes as $r) {
                $out .= "| {$r['methods']} | `{$r['uri']}` | `{$r['name']}` | `{$r['action']}` | {$r['access']} |\n";
            }
            $out .= "\n";
        }
        file_put_contents(base_path('docs/17-route-inventory.md'), $out);
    }
}
