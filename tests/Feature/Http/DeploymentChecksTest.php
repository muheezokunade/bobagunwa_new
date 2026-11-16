<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The server-side commands the staging deployment script depends on.
 */
class DeploymentChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_check_passes_on_a_working_installation(): void
    {
        $this->artisan('boba:smoke')->assertExitCode(0);
    }
    // boba:preflight is exercised against a real empty and migrated database by the
    // simulated staging deployment (docs/37); it issues DDL, which would commit the
    // RefreshDatabase transaction, so it is not run inside this suite.
}
