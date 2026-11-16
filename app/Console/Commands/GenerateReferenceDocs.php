<?php

namespace App\Console\Commands;

use App\Support\PermissionCatalog;
use App\Support\RouteCatalogue;
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
        $this->writeCatalogue();
        $this->info('Regenerated docs/04-database-schema.md, docs/17-route-inventory.md, docs/34-admin-endpoint-coverage.md and docs/35-route-catalogue.md');

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

    /** @return array<string, Route> */
    public static function documentedRoutes(): array
    {
        $routes = [];
        foreach (Router::getRoutes()->getRoutes() as $r) {
            if ($r->getName() && ! str_starts_with($r->uri(), '_') && $r->uri() !== 'up' && ! str_starts_with($r->getName(), 'storage.')) {
                $routes[$r->getName()] = $r;
            }
        }

        return $routes;
    }

    /** Roles allowed through the route's middleware, derived from PermissionCatalog. */
    public static function rolesFor(Route $r): string
    {
        $mw = collect($r->gatherMiddleware())->filter(fn ($m) => is_string($m));
        $permission = $mw->first(fn ($m) => str_starts_with($m, 'permission:'));
        $roles = PermissionCatalog::roles();
        $names = fn (callable $filter) => collect($roles)->filter($filter)->map(fn ($role) => $role['name'])->values()->implode(', ');
        if ($permission) {
            $needed = explode('|', substr($permission, 11));

            return '`'.implode('` or `', $needed).'` → '.$names(fn ($role) => $role['is_staff'] && array_intersect($needed, $role['permissions']));
        }

        return match (true) {
            $mw->contains('staff') => 'Any active officer → '.$names(fn ($role) => $role['is_staff']),
            $mw->contains('member') => 'Signed-in member, own records only',
            $mw->contains('guest') => 'Signed-out visitors',
            $mw->contains('auth') => 'Any signed-in account',
            default => 'Public',
        };
    }

    public static function securityFor(Route $r): string
    {
        $mw = collect($r->gatherMiddleware())->filter(fn ($m) => is_string($m));
        $out = [];
        if ($mw->contains('auth')) {
            $out[] = 'authenticated session (database sessions)';
        }
        if ($mw->contains('two-factor')) {
            $out[] = 'two-factor enrolment gate when enforcement is on';
        }
        if ($p = $mw->first(fn ($m) => str_starts_with($m, 'permission:'))) {
            $out[] = 'server-side permission check (403 otherwise)';
        }
        if ($t = $mw->first(fn ($m) => str_starts_with($m, 'throttle:'))) {
            $out[] = 'rate limit `'.substr($t, 9).'`';
        }
        if (array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $r->methods())) {
            $out[] = 'CSRF token + Origin check (419 otherwise)';
        }
        if ($mw->contains('auth') || str_starts_with($r->uri(), 'account')) {
            $out[] = '`Cache-Control: no-store`';
        }
        if (str_starts_with($r->uri(), 'account')) {
            $out[] = '`Referrer-Policy: no-referrer`';
        }
        $out[] = 'strict CSP and security headers';

        return implode('; ', $out);
    }

    /** Field names validated by the handler (and private helpers it calls), read from its source. */
    public static function validationFor(Route $r): string
    {
        [$class, $method] = array_pad(explode('@', $r->getActionName()), 2, null);
        if (! $method || ! class_exists($class) || ! method_exists($class, $method)) {
            return 'None (no handler input).';
        }
        $source = self::methodSource($class, $method);
        if (preg_match_all('/\$this->(\w+)\(/', $source, $calls)) {
            foreach (array_unique($calls[1]) as $helper) {
                if (method_exists($class, $helper) && (new \ReflectionMethod($class, $helper))->isPrivate()) {
                    $source .= self::methodSource($class, $helper);
                }
            }
        }
        $fields = [];
        $offset = 0;
        while (($pos = strpos($source, 'validate([', $offset)) !== false) {
            $body = self::bracketed($source, $pos + strlen('validate('));
            $offset = $pos + strlen($body) + 10;
            foreach (self::topLevelSplit(substr($body, 1, -1)) as $entry) {
                if (preg_match("/^\\s*'([A-Za-z0-9_.*]+)'\\s*=>\\s*(.+)$/s", $entry, $m)) {
                    $rules = trim(preg_replace('/\s+/', ' ', $m[2]));
                    $rules = preg_replace('/Rule::(\w+)\([^)]*\)(->\w+\([^)]*\))*/', 'Rule::$1', $rules);
                    $rules = preg_replace("/'\\s*\\.\\s*config\\('([^']+)'\\)/", '{$1}', $rules);
                    $rules = trim(str_replace(["'", '"'], '', $rules), '[] ');
                    $fields[$m[1]] = '`'.$m[1].'`: '.str_replace('|', ', ', $rules);
                }
            }
        }
        if (isset($fields['idempotency_key']) || str_contains($source, 'Idempotency::')) {
            $fields['idempotency_key'] = '`idempotency_key`: required exactly-once key (a replay returns the first result)';
        }
        if ($fields === [] && preg_match('/validate\(\s*(array_filter\()?\$/', $source)) {
            return 'Rules assembled per content type or per setting inside `'.class_basename($class).'@'.$method.'` and validated server-side before any write.';
        }
        if ($fields === []) {
            return in_array('GET', $r->methods(), true) ? 'Read-only; route parameters resolved by model binding.' : 'No body fields; acts on the bound record.';
        }

        return implode('<br>', $fields);
    }

    private static function bracketed(string $s, int $open): string
    {
        $depth = 0;
        $quote = null;
        for ($i = $open, $n = strlen($s); $i < $n; $i++) {
            $c = $s[$i];
            if ($quote) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
            } elseif ($c === '[' || $c === '(') {
                $depth++;
            } elseif ($c === ']' || $c === ')') {
                if (--$depth === 0) {
                    return substr($s, $open, $i - $open + 1);
                }
            }
        }

        return substr($s, $open);
    }

    /** @return list<string> */
    private static function topLevelSplit(string $s): array
    {
        $parts = [];
        $depth = 0;
        $quote = null;
        $current = '';
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $c = $s[$i];
            if ($quote) {
                $current .= $c;
                if ($c === '\\' && $i + 1 < $n) {
                    $current .= $s[++$i];
                } elseif ($c === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
            } elseif ($c === '[' || $c === '(') {
                $depth++;
            } elseif ($c === ']' || $c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }
            $current .= $c;
        }
        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    private static function methodSource(string $class, string $method): string
    {
        $ref = new \ReflectionMethod($class, $method);
        $lines = file($ref->getFileName());

        return implode('', array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
    }

    /** @return array<string, list<string>> route name => ["File::test_method", ...] */
    public static function testReferences(array $names): array
    {
        $refs = array_fill_keys($names, []);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('tests/Feature')));
        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            $class = basename($file->getFilename(), '.php');
            $parts = preg_split('/(?=public function (test_\w+)\()/', $code);
            foreach ($parts as $part) {
                if (! preg_match('/^public function (test_\w+)\(/', $part, $tm)) {
                    continue;
                }
                $found = preg_match_all("/route\\(\\s*'([a-z0-9._-]+)'/", $part, $m) ? $m[1] : [];
                // Loops such as foreach ([...names...] as $name) { route($name) } — count quoted route names in the method.
                if (preg_match('/route\\(\\s*\\$\\w+/', $part) && preg_match_all("/'([a-z0-9._-]+)'/", $part, $q)) {
                    $found = array_merge($found, $q[1]);
                }
                if ($found) {
                    foreach (array_unique($found) as $name) {
                        if (isset($refs[$name])) {
                            $refs[$name][] = $class.'::'.$tm[1];
                        }
                    }
                }
            }
        }

        return $refs;
    }

    private function writeCatalogue(): void
    {
        $routes = self::documentedRoutes();
        $catalogue = RouteCatalogue::all();
        $refs = self::testReferences(array_keys($routes));
        $mutatingAdmin = array_filter($routes, fn (Route $r) => str_starts_with($r->getName(), 'admin.') && array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $r->methods()));
        $matrix = 'AdminMutationMatrixTest::test_every_admin_mutation_enforces_roles_server_side, AdminMutationMatrixTest::test_every_admin_mutation_rejects_missing_csrf_token';
        $date = now()->toDateString();

        // Endpoint coverage matrix for administrative mutations.
        $out = "# Administrative endpoint coverage matrix\n\n_Generated by `php artisan boba:docs:reference` on {$date} from the live router and the test sources — do not edit by hand._\n\n";
        $out .= 'Every administrative route that accepts POST, PUT, PATCH or DELETE is listed. Two generic HTTP tests call **every** one of them: '
            .'`AdminMutationMatrixTest` posts as each of the '.count(PermissionCatalog::roles()).' roles and anonymously (asserting 302 to sign-in, 403 exactly where the role lacks the permission, and no 403/404/405/500 where it holds it) and posts again with forgery protection active (asserting 419). '
            .'The “Behaviour tests” column lists the HTTP tests that call the route by name and assert its effect (state change, audit row, validation failure without side effects, duplicate-submission handling or ownership). '
            ."Lower-level service tests are not counted.\n\n";
        $out .= '**'.count($mutatingAdmin).' administrative mutation routes.** ';
        $without = array_filter(array_keys($mutatingAdmin), fn ($n) => $refs[$n] === []);
        $out .= count($mutatingAdmin) - count($without).' have route-specific HTTP behaviour tests; '.count($without)." rely on the generic matrix only.\n\n";
        $out .= "| Route | Method · path | Permission | Matrix (roles + CSRF) | Behaviour tests |\n|---|---|---|---|---|\n";
        foreach ($mutatingAdmin as $name => $r) {
            $perm = collect($r->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));
            $tests = $refs[$name] ? implode('<br>', array_map(fn ($t) => '`'.$t.'`', array_unique($refs[$name]))) : '**none — matrix only**';
            $out .= "| `{$name}` | POST `/{$r->uri()}` | ".($perm ? '`'.str_replace('|', '` or `', substr($perm, 11)).'`' : 'any active officer').' | yes | '.$tests." |\n";
        }
        file_put_contents(base_path('docs/34-admin-endpoint-coverage.md'), $out);

        // Per-route catalogue.
        $out = "# Route catalogue\n\n_Generated by `php artisan boba:docs:reference` on {$date} — do not edit by hand. Meaning comes from `App\\Support\\RouteCatalogue`; roles, security, validation fields and tests are derived from the code. `tests/Feature/Docs/RouteCatalogueSyncTest` fails if a route is undocumented._\n\n";
        $out .= 'Status values: **Implemented · HTTP-tested** (at least one feature test calls or asserts the route), **Implemented · not directly HTTP-tested** (behaviour is exercised only through other routes or services — listed honestly in [24-known-issues.md](24-known-issues.md)).'."\n\n";
        $out .= '**'.count($routes).' routes.**'."\n\n";
        $untested = [];
        foreach ($routes as $name => $r) {
            $meta = $catalogue[$name] ?? ['p' => '_Undocumented_', 'd' => '', 'a' => ''];
            $isRead = ! array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $r->methods());
            $public = ! collect($r->gatherMiddleware())->contains(fn ($m) => in_array($m, ['auth', 'staff', 'member'], true));
            $tests = array_unique($refs[$name]);
            if (isset($mutatingAdmin[$name])) {
                array_unshift($tests, $matrix);
            }
            $status = $tests ? 'Implemented · HTTP-tested' : 'Implemented · not directly HTTP-tested';
            if (! $tests) {
                $untested[] = $name;
            }
            $out .= "## `{$name}`\n\n";
            $out .= '| | |'."\n|---|---|\n";
            $out .= '| Method · path | '.implode('|', array_diff($r->methods(), ['HEAD'])).' `/'.ltrim($r->uri(), '/')."` |\n";
            $out .= '| Handler | `'.class_basename(explode('@', $r->getActionName())[0]).'@'.(explode('@', $r->getActionName())[1] ?? '')."` |\n";
            $out .= '| Purpose | '.$meta['p']." |\n";
            $out .= '| Roles / permission | '.self::rolesFor($r)." |\n";
            $out .= '| Validation | '.self::validationFor($r)." |\n";
            $out .= '| Data | '.$meta['d']." |\n";
            $out .= '| Success | '.($meta['s'] ?? self::READ_SUCCESS_TEXT)." |\n";
            $out .= '| Failure states | '.($meta['f'] ?? ($isRead ? ($public ? RouteCatalogue::PUBLIC_FAILURE : RouteCatalogue::READ_FAILURE) : 'Validation errors re-shown; business-rule refusals shown on the form.'))." |\n";
            $out .= '| Security controls | '.self::securityFor($r)." |\n";
            $out .= '| Acceptance criterion | '.$meta['a']." |\n";
            $out .= '| Tests | '.($tests ? implode('<br>', array_map(fn ($t) => '`'.$t.'`', $tests)) : '—')." |\n";
            $out .= "| Status | {$status} |\n\n";
        }
        $summary = "## Routes without a direct HTTP test\n\n".($untested ? implode(', ', array_map(fn ($n) => "`{$n}`", $untested)) : 'None.')."\n\n";
        $out = str_replace('**'.count($routes).' routes.**'."\n\n", '**'.count($routes).' routes**, '.(count($routes) - count($untested)).' HTTP-tested.'."\n\n".$summary, $out);
        file_put_contents(base_path('docs/35-route-catalogue.md'), $out);
    }

    private const READ_SUCCESS_TEXT = RouteCatalogue::READ_SUCCESS;
}
