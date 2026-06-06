<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature   = 'backup:database {--keep= : Override how many days of backups to retain}';
    protected $description = 'Dump the PostgreSQL database to storage/backups and prune old backups';

    public function handle(): int
    {
        $connection = config('database.default');

        if ($connection !== 'pgsql') {
            $this->error("backup:database only supports PostgreSQL. Current connection: $connection");
            return self::FAILURE;
        }

        $backupDir = storage_path('backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0750, true);
        }

        $filename  = 'db_' . now()->format('Y-m-d_His') . '.sql.gz';
        $filepath  = $backupDir . '/' . $filename;

        $host     = config('database.connections.pgsql.host');
        $port     = config('database.connections.pgsql.port', 5432);
        $database = config('database.connections.pgsql.database');
        $username = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        // pg_dump pipes through gzip
        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s --no-owner --no-acl %s | gzip > %s',
            escapeshellarg((string) $password),
            escapeshellarg((string) $host),
            escapeshellarg((string) $port),
            escapeshellarg((string) $username),
            escapeshellarg((string) $database),
            escapeshellarg($filepath),
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('pg_dump failed: ' . $process->getErrorOutput());
            return self::FAILURE;
        }

        $sizeMb = round(filesize($filepath) / 1024 / 1024, 2);
        $this->info("Backup created: backups/$filename ({$sizeMb} MB)");

        $this->pruneOldBackups($backupDir);

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $backupDir): void
    {
        $keepDays = (int) ($this->option('keep') ?? env('BACKUP_KEEP_DAYS', 30));
        $cutoff   = now()->subDays($keepDays)->getTimestamp();
        $pruned   = 0;

        foreach (glob($backupDir . '/db_*.sql.gz') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $pruned++;
            }
        }

        if ($pruned > 0) {
            $this->line("Pruned $pruned backup(s) older than $keepDays days.");
        }
    }
}
