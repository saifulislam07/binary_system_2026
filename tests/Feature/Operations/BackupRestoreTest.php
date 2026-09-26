<?php

namespace Tests\Feature\Operations;

use App\Enums\PlacementSide;
use App\Services\BackupRestoreVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

/**
 * A real backup → restore round trip against the MySQL test database:
 * mysqldump via Spatie Backup, then backup:verify-restore imports it into a
 * scratch database and checks it. Data must be committed for mysqldump to
 * see it, so this class opts out of the per-test transaction.
 */
class BackupRestoreTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private string $backupRoot;

    /**
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $bin = (string) config('database.connections.mysql.dump.dump_binary_path');
        $finder = new ExecutableFinder;
        $found = $bin !== ''
            ? File::exists(rtrim($bin, '/\\').'/mysqldump') || File::exists(rtrim($bin, '/\\').'/mysqldump.exe')
            : $finder->find('mysqldump') !== null && $finder->find('mysql') !== null;

        if (! $found) {
            $this->markTestSkipped('mysqldump/mysql client not available (set DB_DUMP_BINARY_PATH).');
        }

        $this->backupRoot = storage_path('framework/testing/backups-'.getmypid());
        // Spatie reads its config once at boot, so only the disk root is redirected here.
        config(['filesystems.disks.backups.root' => $this->backupRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupRoot ?? '');
        RefreshDatabaseState::$migrated = false; // this class committed rows

        parent::tearDown();
    }

    public function test_the_newest_backup_restores_into_a_consistent_database()
    {
        $this->seedCommissionRules();
        $root = $this->root();
        $this->sell($this->join($root, PlacementSide::Left), 1_000);
        $this->sell($this->join($root, PlacementSide::Right), 5_000);

        $this->artisan('backup:run', ['--only-db' => true, '--disable-notifications' => true])->assertSuccessful();

        $this->artisan('backup:verify-restore')
            ->expectsOutputToContain('Restore check passed')
            ->assertSuccessful();

        $last = Cache::get(BackupRestoreVerifier::CACHE_KEY);
        $this->assertTrue($last['ok']);

        $scratch = config('database.connections.mysql.database').'_restore_check';
        $this->assertEmpty(DB::select('SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?', [$scratch]), 'The scratch database is dropped afterwards');
    }

    public function test_a_damaged_backup_fails_the_check()
    {
        $this->root();
        $this->artisan('backup:run', ['--only-db' => true, '--disable-notifications' => true])->assertSuccessful();

        // A newer, broken archive: what a half-uploaded or corrupted backup looks like.
        File::put($this->backupRoot.'/'.config('backup.backup.name').'/'.now()->addMinute()->format('Y-m-d-H-i-s').'.zip', 'not a zip');

        $this->artisan('backup:verify-restore')
            ->expectsOutputToContain('FAILED')
            ->assertFailed();

        $this->assertFalse(Cache::get(BackupRestoreVerifier::CACHE_KEY)['ok']);
    }
}
