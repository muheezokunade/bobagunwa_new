<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Throwable;

/**
 * Post-deployment smoke check run on the server itself: sends internal
 * requests through the HTTP kernel (no network, no certificate needed and
 * not affected by the .htaccess protection) and checks status codes and the
 * security headers. Used by deploy/staging-deploy.sh after the switch; a
 * failure triggers an automatic rollback.
 */
class Smoke extends Command
{
    protected $signature = 'boba:smoke {--json : Machine-readable output}';

    protected $description = 'Check that key pages render and carry security headers (internal requests, no network)';

    private const EXPECTED = [
        '/' => 200, '/membership' => 200, '/membership/apply' => 200, '/membership/status' => 200,
        '/contact' => 200, '/login' => 200, '/account/recover' => 200, '/privacy' => 200,
        '/sitemap.xml' => 200, '/portal' => 302, '/admin' => 302, '/admin/finance' => 302,
        '/no-such-page-'.'smoke' => 404,
    ];

    public function handle(Kernel $kernel): int
    {
        $base = rtrim((string) config('app.url'), '/');
        $results = [];
        foreach (self::EXPECTED as $path => $expected) {
            try {
                $request = Request::create($base.$path, 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTPS' => str_starts_with($base, 'https') ? 'on' : 'off']);
                $response = $kernel->handle($request);
                $status = $response->getStatusCode();
                $headers = $response->headers;
                $ok = $status === $expected
                    && str_contains((string) $headers->get('Content-Security-Policy'), "default-src 'self'")
                    && $headers->get('X-Frame-Options') === 'DENY'
                    && $headers->get('X-Content-Type-Options') === 'nosniff';
                $detail = "HTTP {$status}".($response->exception ? ' '.class_basename($response->exception).': '.mb_substr($response->exception->getMessage(), 0, 160) : '');
                $kernel->terminate($request, $response);
            } catch (Throwable $e) {
                $ok = false;
                $detail = class_basename($e).': '.mb_substr($e->getMessage(), 0, 160);
            }
            $results[] = ['path' => $path, 'expected' => $expected, 'ok' => $ok, 'detail' => $detail];
        }
        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Path', 'Expected', 'Result', 'Detail'], array_map(fn ($r) => [$r['path'], $r['expected'], $r['ok'] ? 'PASS' : 'FAIL', $r['detail']], $results));
        }

        return collect($results)->every(fn ($r) => $r['ok']) ? self::SUCCESS : self::FAILURE;
    }
}
