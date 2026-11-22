<?php

namespace Tests\Feature\Http;

use App\Console\Commands\StagingPrepare;
use App\Models\AccountToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The server-side commands the staging deployment script depends on.
 */
class DeploymentChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_database_session_defaults_to_innodb_even_if_the_host_defaults_to_myisam(): void
    {
        // Namecheap's MariaDB may default to MyISAM (no transactions, foreign keys or row locks).
        $options = config('database.connections.mariadb.options');
        $this->assertContains('SET SESSION default_storage_engine=InnoDB', $options);
        $this->assertSame('innodb', strtolower(DB::selectOne('SELECT @@session.default_storage_engine AS e')->e));
    }

    public function test_smoke_check_passes_on_a_working_installation(): void
    {
        $this->artisan('boba:smoke')->assertExitCode(0);
    }

    public function test_staging_prepare_creates_synthetic_officers_with_private_single_use_links_and_refuses_production(): void
    {
        $file = storage_path('framework/testing/acceptance-links-'.uniqid().'.json');
        $this->artisan('boba:staging:prepare', ['--links-file' => $file])->assertExitCode(1);
        $this->artisan('boba:staging:prepare', ['--confirm-synthetic' => true, '--links-file' => $file])->assertExitCode(0);
        $this->assertSame('600', substr(sprintf('%o', fileperms($file)), -3));
        $links = json_decode(file_get_contents($file), true)['officers'];
        $this->assertSame(StagingPrepare::ROLES, array_keys($links));
        foreach ($links as $role => $link) {
            $this->assertStringEndsWith('@staging-synthetic.invalid', $link['email']);
            $this->assertSame('activation', $link['purpose']);
            $user = User::where('email', $link['email'])->sole();
            $this->assertNull($user->password, 'No password is ever set by the command');
            $this->assertSame([$role], $user->roles->pluck('key')->all());
        }
        $this->artisan('boba:staging:prepare', ['--confirm-synthetic' => true, '--links-file' => $file])->assertExitCode(0);
        $this->assertSame(6, User::where('email', 'like', '%@staging-synthetic.invalid')->count(), 'Re-running does not duplicate accounts');
        $this->assertSame(6, AccountToken::whereNull('revoked_at')->whereNull('consumed_at')->count(), 'Re-issuing revokes the earlier unused links');
        @unlink($file);

        $this->app['env'] = 'production';
        $this->artisan('boba:staging:prepare', ['--confirm-synthetic' => true, '--links-file' => $file])->assertExitCode(1);
        $this->assertFileDoesNotExist($file);
    }

    // boba:preflight is exercised against a real empty and migrated database by the
    // simulated staging deployment (docs/37); it issues DDL, which would commit the
    // RefreshDatabase transaction, so it is not run inside this suite.
}
