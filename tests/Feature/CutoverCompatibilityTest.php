<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Platform\Support\RuntimeConfig;
use Tests\TestCase;

/**
 * The platform replaces the legacy backend in place: the server keeps its own (ignored)
 * config/database.php, filesystems.php, app.php and .env. These tests pin the fallbacks.
 */
class CutoverCompatibilityTest extends TestCase
{
    private array $envBackup = [];

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
        parent::tearDown();
    }

    private function setEnv(string $key, string $value): void
    {
        $this->envBackup[$key] ??= $_ENV[$key] ?? null;
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    public function test_an_existing_server_oracle_connection_is_kept_untouched(): void
    {
        $this->setEnv('ORACLE_DRIVER', 'oracle');
        $server = ['driver' => 'oracle', 'host' => 'server-host', 'username' => 'legacy'];
        config(['database.connections.oracle' => $server]);

        RuntimeConfig::apply();

        $this->assertSame($server, config('database.connections.oracle'));
    }

    public function test_a_missing_oracle_connection_falls_back_to_the_legacy_env_names(): void
    {
        $this->setEnv('ORACLE_DRIVER', 'oracle');
        $this->setEnv('DB_HOST', 'legacy-host');
        $this->setEnv('DB_SERVICE_NAME', 'legacy-service');
        config(['database.connections.oracle' => null]);

        RuntimeConfig::apply();

        $this->assertSame('oracle', config('database.connections.oracle.driver'));
        $this->assertSame('legacy-host', config('database.connections.oracle.host'));
        $this->assertSame('legacy-service', config('database.connections.oracle.service_name'));
    }

    public function test_wells_media_disk_and_private_files_are_configured_without_a_server_file(): void
    {
        config(['filesystems.disks.legacy_media' => null, 'filesystems.disks.local.serve' => true]);

        RuntimeConfig::apply();

        $this->assertSame('local', config('filesystems.disks.legacy_media.driver'));
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }

    public function test_driver_fixed_code_defaults_to_the_owner_decision(): void
    {
        $this->envBackup['SMS_TEST_CODE'] ??= $_ENV['SMS_TEST_CODE'] ?? null;
        unset($_ENV['SMS_TEST_CODE'], $_SERVER['SMS_TEST_CODE']);
        putenv('SMS_TEST_CODE');
        $this->assertSame('000000', (require config_path('services.php'))['sms']['test_code']);

        $this->setEnv('SMS_TEST_CODE', 'disabled');
        $this->assertNull((require config_path('services.php'))['sms']['test_code']);
    }

    public function test_old_dashboard_bookmarks_redirect_temporarily(): void
    {
        $this->get('/admin/dashboard')->assertStatus(302)->assertRedirect('/fleet');
        $this->get('/admin/vehicle-claims')->assertRedirect('/fleet/claims');
        $this->get('/admin/trips/42')->assertRedirect('/fleet/trips/42');
        $this->get('/wells/diesel-inventories')->assertRedirect('/wells/diesel');
        $this->get('/permissions')->assertRedirect('/platform/access');
    }

    public function test_the_legacy_valves_service_worker_is_replaced_by_an_unregistering_stub(): void
    {
        $worker = file_get_contents(public_path('valves-map-sw.js'));

        $this->assertStringContainsString('registration.unregister()', $worker);
        $this->assertStringNotContainsString('/wms.php', $worker);
    }
}
