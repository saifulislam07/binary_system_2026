<?php

namespace App\Services;

use App\Enums\CommissionCycleStatus;
use App\Enums\MemberStatus;
use App\Models\CommissionCycle;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Operational health. The scheduler and the queue worker each leave a
 * heartbeat in the cache; backups and restore checks record when they last
 * succeeded. `/up` fails on the critical checks (uptime monitors watch it);
 * /admin/health shows all of them.
 */
class SystemHealth
{
    public const SCHEDULER = 'health:scheduler';

    public const QUEUE = 'health:queue';

    public const BACKUP = 'health:backup';

    public function recordSchedulerHeartbeat(): void
    {
        Cache::forever(self::SCHEDULER, now()->toIso8601String());
    }

    public function recordQueueHeartbeat(): void
    {
        Cache::forever(self::QUEUE, now()->toIso8601String());
    }

    public function recordBackup(): void
    {
        Cache::forever(self::BACKUP, now()->toIso8601String());
    }

    /**
     * @return list<array{check: string, ok: bool, critical: bool, detail: string}>
     */
    public function checks(bool $criticalOnly = false): array
    {
        $workers = (bool) config('business.health.require_workers');

        $checks = [
            $this->check('Database', true, function () {
                DB::select('SELECT 1');

                return [true, 'reachable'];
            }),
            $this->check('Cache', true, function () {
                Cache::put('health:probe', 'ok', 60);

                return [Cache::get('health:probe') === 'ok', 'read/write'];
            }),
            $this->heartbeat('Scheduler (cron)', self::SCHEDULER, (int) config('business.health.scheduler_max_minutes', 3), $workers),
            $this->heartbeat('Queue worker', self::QUEUE, (int) config('business.health.queue_max_minutes', 15), $workers),
        ];

        if ($criticalOnly) {
            return array_values(array_filter($checks, fn (array $c) => $c['critical']));
        }

        return [
            ...$checks,
            $this->check('Failed queue jobs', false, function () {
                $failed = DB::table('failed_jobs')->count();

                return [$failed === 0, $failed === 0 ? 'none' : "{$failed} failed — php artisan queue:failed"];
            }),
            $this->check('Commission cycle', false, fn () => $this->commissionCycle()),
            $this->heartbeat('Last backup', self::BACKUP, 26 * 60, false),
            $this->check('Last restore test', false, function () {
                $last = Cache::get(BackupRestoreVerifier::CACHE_KEY);

                if (! is_array($last)) {
                    return [false, 'never run — php artisan backup:verify-restore'];
                }

                $at = CarbonImmutable::parse((string) $last['at']);

                return [(bool) $last['ok'] && $at->gt(now()->subDays(8)), ($last['ok'] ? 'passed ' : 'FAILED ').$at->diffForHumans()];
            }),
        ];
    }

    /**
     * @return list<string> failed critical checks
     */
    public function criticalFailures(): array
    {
        return array_values(array_map(
            fn (array $c) => "{$c['check']}: {$c['detail']}",
            array_filter($this->checks(criticalOnly: true), fn (array $c) => ! $c['ok']),
        ));
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function commissionCycle(): array
    {
        $latest = CommissionCycle::query()->where('status', CommissionCycleStatus::Closed)->max('cycle_date');
        $allowedGap = config('business.commission_cycle') === 'weekly' ? 8 : 2;

        if ($latest === null) {
            $due = Member::query()->where('status', MemberStatus::Active)->where('activated_at', '<', now()->subDays($allowedGap))->exists();

            return [! $due, 'no cycle has run yet'];
        }

        $date = CarbonImmutable::parse((string) $latest);

        return [$date->gte(now()->subDays($allowedGap)->startOfDay()), 'last closed cycle '.$date->toDateString()];
    }

    /**
     * @return array{check: string, ok: bool, critical: bool, detail: string}
     */
    private function heartbeat(string $name, string $key, int $maxMinutes, bool $critical): array
    {
        return $this->check($name, $critical, function () use ($key, $maxMinutes) {
            $beat = Cache::get($key);

            if (! is_string($beat)) {
                return [false, 'never seen'];
            }

            $at = CarbonImmutable::parse($beat);

            return [$at->gt(now()->subMinutes($maxMinutes)), 'last seen '.$at->diffForHumans()];
        });
    }

    /**
     * @param  callable(): array{0: bool, 1: string}  $probe
     * @return array{check: string, ok: bool, critical: bool, detail: string}
     */
    private function check(string $name, bool $critical, callable $probe): array
    {
        try {
            [$ok, $detail] = $probe();
        } catch (Throwable $e) {
            [$ok, $detail] = [false, $e->getMessage()];
        }

        return ['check' => $name, 'ok' => $ok, 'critical' => $critical, 'detail' => $detail];
    }
}
