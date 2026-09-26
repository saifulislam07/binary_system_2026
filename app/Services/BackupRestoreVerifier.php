<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Config\Config as BackupConfig;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Proves a backup can actually be restored: takes the newest Spatie Backup
 * zip, imports its database dump into a scratch database next to the live
 * one, checks the restored data (ledgers balance, tree agrees with itself,
 * row counts are sane) and drops the scratch database again.
 *
 * Needs the `mysql` client (DB_DUMP_BINARY_PATH if it is not on PATH) and a
 * DB user allowed to CREATE/DROP the `<database>_restore_check` database —
 * run it on staging, or on production with such a user.
 */
class BackupRestoreVerifier
{
    public const CACHE_KEY = 'health:restore_check';

    private const CONNECTION = 'restore_check';

    /** Tables whose restored row count is compared with the live database. */
    private const KEY_TABLES = ['members', 'binary_nodes', 'wallets', 'wallet_transactions', 'commissions', 'sales', 'withdrawals', 'volume_lots'];

    /**
     * @return array{backup: string, created_at: string, checks: list<array{check: string, ok: bool, detail: string}>, ok: bool}
     */
    public function __construct(private BackupConfig $config) {}

    public function verify(?string $disk = null): array
    {
        // Same settings object backup:run uses, so both look in the same place.
        $disk ??= (string) ($this->config->backup->destination->disks[0] ?? 'backups');
        $backup = BackupDestination::create($disk, $this->config->backup->name)->newestBackup()
            ?? throw new RuntimeException("No backup found on disk [{$disk}].");

        $this->sweepStaleWorkDirs();
        $work = storage_path('app/backup-temp/restore-check-'.Str::lower(Str::random(8)));
        $live = (string) config('database.connections.mysql.database');
        $scratch = $live.'_restore_check';
        $checks = [];

        if (! is_dir($work) && ! mkdir($work, 0700, true)) {
            throw new RuntimeException("Cannot create {$work}.");
        }

        try {
            [$dump, $files] = $this->unpack($backup->stream(), $work);
            $checks[] = ['check' => 'Backup archive opens', 'ok' => true, 'detail' => "{$backup->path()} ({$files} stored files)"];

            DB::statement("DROP DATABASE IF EXISTS `{$scratch}`");
            DB::statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->import($dump, $scratch);
            $checks[] = ['check' => 'Database dump imports', 'ok' => true, 'detail' => "into {$scratch}"];

            config(['database.connections.'.self::CONNECTION => [...config('database.connections.mysql'), 'database' => $scratch]]);
            DB::purge(self::CONNECTION);
            $restored = DB::connection(self::CONNECTION);

            $checks = [...$checks, ...$this->inspect($restored)];
        } catch (Throwable $e) {
            $checks[] = ['check' => 'Restore', 'ok' => false, 'detail' => $e->getMessage()];
        } finally {
            DB::purge(self::CONNECTION);
            DB::statement("DROP DATABASE IF EXISTS `{$scratch}`");
            $this->removeDirectory($work);
        }

        $ok = collect($checks)->every(fn (array $c) => $c['ok']);
        Cache::forever(self::CACHE_KEY, ['at' => now()->toIso8601String(), 'ok' => $ok, 'backup' => $backup->path()]);

        return ['backup' => $backup->path(), 'created_at' => $backup->date()->toIso8601String(), 'checks' => $checks, 'ok' => $ok];
    }

    /**
     * @param  resource  $stream
     * @return array{0: string, 1: int} path of the extracted .sql, number of stored files
     */
    private function unpack($stream, string $work): array
    {
        $zipPath = "{$work}/backup.zip";
        $out = fopen($zipPath, 'wb') ?: throw new RuntimeException("Cannot write {$zipPath}.");
        stream_copy_to_stream($stream, $out);
        fclose($out);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The backup is not a readable zip archive.');
        }

        if (filled($this->config->backup->password)) {
            $zip->setPassword((string) $this->config->backup->password);
        }

        $dumpEntry = null;
        $files = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));

            if (preg_match('#^db-dumps/.+\.sql(\.gz)?$#', $name)) {
                $dumpEntry = $i;
            } elseif (! str_ends_with($name, '/') && ! str_ends_with($name, '.gitignore')) {
                $files++;
            }
        }

        if ($dumpEntry === null) {
            throw new RuntimeException('The backup contains no database dump.');
        }

        $name = str_replace('\\', '/', (string) $zip->getNameIndex($dumpEntry));
        $contents = $zip->getStream((string) $zip->getNameIndex($dumpEntry)) ?: throw new RuntimeException('Cannot read the database dump (wrong BACKUP_ARCHIVE_PASSWORD?).');
        $sql = "{$work}/restore.sql";
        $target = fopen($sql, 'wb') ?: throw new RuntimeException("Cannot write {$sql}.");

        if (str_ends_with($name, '.gz')) {
            $raw = "{$work}/restore.sql.gz";
            file_put_contents($raw, $contents);
            $gz = gzopen($raw, 'rb') ?: throw new RuntimeException('Cannot decompress the database dump.');

            while (! gzeof($gz)) {
                fwrite($target, (string) gzread($gz, 1 << 20));
            }

            gzclose($gz);
        } else {
            stream_copy_to_stream($contents, $target);
        }

        // Close every handle: Windows won't delete open files during cleanup.
        fclose($contents);
        fclose($target);
        $zip->close();

        return [$sql, $files];
    }

    private function import(string $sqlFile, string $database): void
    {
        $config = config('database.connections.mysql');
        $binDir = (string) ($config['dump']['dump_binary_path'] ?? '');
        $mysql = ($binDir !== '' ? rtrim($binDir, '/\\').DIRECTORY_SEPARATOR : '').'mysql';

        $input = fopen($sqlFile, 'rb') ?: throw new RuntimeException("Cannot read {$sqlFile}.");
        $process = new Process(
            [$mysql, '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'], '--default-character-set=utf8mb4', $database],
            null,
            ['MYSQL_PWD' => (string) $config['password']], // keeps the password off the process list
            $input,
            1800,
        );
        $process->run();

        if (is_resource($input)) {
            fclose($input);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysql import failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    /**
     * @return list<array{check: string, ok: bool, detail: string}>
     */
    private function inspect(Connection $restored): array
    {
        $checks = [];

        $liveMigrations = DB::table('migrations')->count();
        $restoredMigrations = $restored->table('migrations')->count();
        $checks[] = [
            'check' => 'Schema is complete',
            'ok' => $restoredMigrations > 0 && $restoredMigrations <= $liveMigrations,
            'detail' => "{$restoredMigrations} of {$liveMigrations} migrations",
        ];

        foreach (self::KEY_TABLES as $table) {
            $live = DB::table($table)->count();
            $copy = $restored->table($table)->count();
            $checks[] = [
                'check' => "Rows in {$table}",
                // The backup is older than now, so it may hold fewer rows — never more, never none when live has some.
                'ok' => $copy <= $live && ($live === 0 || $copy > 0),
                'detail' => "{$copy} restored / {$live} live",
            ];
        }

        $badWallets = (int) $restored->selectOne(<<<'SQL'
            SELECT COUNT(*) AS n FROM wallets w
            WHERE w.balance <> (
                SELECT COALESCE(SUM(CASE WHEN t.direction = 'credit' THEN t.amount ELSE -t.amount END), 0)
                FROM wallet_transactions t WHERE t.wallet_id = w.id AND t.status <> 'voided'
            )
            SQL)->n;
        $checks[] = ['check' => 'Wallet balances equal their ledgers', 'ok' => $badWallets === 0, 'detail' => "{$badWallets} mismatched wallet(s)"];

        $badPlacements = (int) $restored->selectOne(<<<'SQL'
            SELECT COUNT(*) AS n FROM members m
            WHERE m.placement_parent_id IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM binary_nodes b
                WHERE b.member_id = m.placement_parent_id
                  AND ((m.placement_side = 'left' AND b.left_child_id = m.id) OR (m.placement_side = 'right' AND b.right_child_id = m.id))
            )
            SQL)->n;
        $checks[] = ['check' => 'Tree placements agree with binary_nodes', 'ok' => $badPlacements === 0, 'detail' => "{$badPlacements} mismatched placement(s)"];

        return $checks;
    }

    /**
     * Best effort: on Windows a just-closed file can stay locked for a
     * moment. Leftovers are swept by the next run (see sweepStaleWorkDirs()).
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $path = "{$dir}/{$entry}";
                is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
            }
        }

        if (! @rmdir($dir)) {
            gc_collect_cycles();
            usleep(250_000);
            @rmdir($dir);
        }
    }

    private function sweepStaleWorkDirs(): void
    {
        foreach (glob(storage_path('app/backup-temp/restore-check-*'), GLOB_ONLYDIR) ?: [] as $dir) {
            if (filemtime($dir) < time() - 3600) {
                $this->removeDirectory($dir);
            }
        }
    }
}
