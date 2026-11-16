<?php

namespace Tests\Feature\Docs;

use App\Console\Commands\GenerateReferenceDocs;
use App\Support\RouteCatalogue;
use Tests\TestCase;

/**
 * Keeps the per-route documentation in step with the router: every named
 * route must be described, no description may outlive its route, and the
 * committed generated documents must list every route.
 */
class RouteCatalogueSyncTest extends TestCase
{
    public function test_every_route_is_catalogued_and_nothing_stale(): void
    {
        $routes = array_keys(GenerateReferenceDocs::documentedRoutes());
        $catalogue = RouteCatalogue::all();
        $this->assertSame([], array_values(array_diff($routes, array_keys($catalogue))), 'Routes missing from App\Support\RouteCatalogue');
        $this->assertSame([], array_values(array_diff(array_keys($catalogue), $routes)), 'Catalogue entries for routes that no longer exist');
        foreach ($catalogue as $name => $meta) {
            foreach (['p', 'd', 'a'] as $field) {
                $this->assertNotSame('', trim((string) ($meta[$field] ?? '')), "{$name} lacks {$field}");
            }
            $isRead = ! array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], GenerateReferenceDocs::documentedRoutes()[$name]->methods());
            if (! $isRead) {
                $this->assertArrayHasKey('s', $meta, "{$name} is a mutation and needs an explicit success state");
                $this->assertArrayHasKey('f', $meta, "{$name} is a mutation and needs explicit failure states");
            }
        }
    }

    public function test_generated_documents_list_every_route(): void
    {
        $catalogue = file_get_contents(base_path('docs/35-route-catalogue.md'));
        $inventory = file_get_contents(base_path('docs/17-route-inventory.md'));
        $coverage = file_get_contents(base_path('docs/34-admin-endpoint-coverage.md'));
        foreach (GenerateReferenceDocs::documentedRoutes() as $name => $route) {
            $this->assertStringContainsString("## `{$name}`", $catalogue, "docs/35 is stale: run php artisan boba:docs:reference ({$name})");
            $this->assertStringContainsString("`{$name}`", $inventory, "docs/17 is stale ({$name})");
            if (str_starts_with($name, 'admin.') && array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())) {
                $this->assertStringContainsString("| `{$name}` |", $coverage, "docs/34 is stale ({$name})");
            }
        }
    }
}
