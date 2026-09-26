<?php

namespace Tests\Feature\Operations;

use App\Jobs\QueueHeartbeat;
use App\Models\Admin;
use App\Services\SystemHealth;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Backup\Events\BackupWasSuccessful;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_is_healthy_when_the_database_and_cache_work()
    {
        $this->get('/up')->assertOk();
    }

    public function test_up_fails_when_cron_or_the_queue_worker_stops_once_workers_are_required()
    {
        config(['business.health.require_workers' => true]);
        $health = app(SystemHealth::class);

        $this->get('/up')->assertStatus(500); // neither has ever run

        $health->recordSchedulerHeartbeat();
        $this->get('/up')->assertStatus(500); // queue still silent

        dispatch(new QueueHeartbeat); // sync queue in tests = a worker ran it
        $this->get('/up')->assertOk();

        $this->travel(5)->minutes(); // cron stopped: scheduler beat is stale
        $this->get('/up')->assertStatus(500);
        $this->assertStringContainsString('Scheduler', implode(' ', $health->criticalFailures()));
    }

    public function test_backups_and_restore_checks_are_tracked()
    {
        $this->assertNull(Cache::get(SystemHealth::BACKUP));

        event(new BackupWasSuccessful('backups', 'test'));

        $this->assertNotNull(Cache::get(SystemHealth::BACKUP));
        $backup = collect(app(SystemHealth::class)->checks())->firstWhere('check', 'Last backup');
        $this->assertTrue($backup['ok']);
    }

    public function test_the_admin_health_page_lists_every_check()
    {
        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin')
            ->get(route('admin.health'))
            ->assertOk()
            ->assertSee('Scheduler (cron)')
            ->assertSee('Queue worker')
            ->assertSee('Last restore test')
            ->assertViewHas('checks', fn (array $checks) => count($checks) === 8);

        $this->actingAs(Admin::factory()->create()->assignRole('finance'), 'admin')
            ->get(route('admin.health'))
            ->assertForbidden();
    }

    public function test_the_scheduler_runs_the_business_jobs_backups_and_heartbeats()
    {
        $events = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn (Event $e) => [($e->description ?: (string) $e->command) => $e->expression]);

        $expected = [
            'commission:run' => '15 0 * * *',
            'ranks:evaluate' => '45 0 * * *',
            'fraud:scan' => '0 * * * *',
            'backup:clean' => '30 1 * * *',
            'backup:run' => '0 2 * * *',
            'backup:monitor' => '0 3 * * *',
            'health:scheduler-heartbeat' => '* * * * *',
            'health:queue-heartbeat' => '*/5 * * * *',
        ];

        foreach ($expected as $job => $cron) {
            $key = $events->keys()->first(fn (string $k) => str_contains($k, $job));
            $this->assertNotNull($key, "{$job} is not scheduled");
            $this->assertSame($cron, $events[$key], "{$job} runs at the wrong time");
        }
    }

    public function test_error_tracking_never_ships_personal_data()
    {
        $this->assertFalse((bool) config('sentry.send_default_pii'));
        $this->assertSame('never', config('sentry.max_request_body_size'));
    }
}
