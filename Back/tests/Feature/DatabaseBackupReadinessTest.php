<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseBackupReadinessTest extends TestCase
{
    public function test_postgres_backup_template_uses_safe_native_atomic_patterns(): void
    {
        $script = file_get_contents(base_path('deployment/backups/narlit-postgres-backup.sh'));

        $this->assertStringContainsString('set -euo pipefail', $script);
        $this->assertStringContainsString('umask 077', $script);
        $this->assertStringContainsString('pg_dump', $script);
        $this->assertStringContainsString('--format=custom', $script);
        $this->assertStringContainsString('pg_restore --list', $script);
        $this->assertStringContainsString('TMP_FILE=', $script);
        $this->assertStringContainsString('FINAL_FILE=', $script);
        $this->assertStringContainsString('mv "${TMP_FILE}" "${FINAL_FILE}"', $script);
        $this->assertStringContainsString('flock -n 9', $script);
        $this->assertStringContainsString("-name 'narlit-postgres-*.dump'", $script);
        $this->assertStringContainsString('-maxdepth 1', $script);
        $this->assertStringNotContainsString('mysqldump', $script);
        $this->assertStringNotContainsString('DB_PASSWORD', $script);
        $this->assertStringNotContainsString('postgresql://', $script);
        $this->assertStringNotContainsString('/public', $script);
    }

    public function test_database_backup_artifacts_are_ignored_by_git(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));

        $this->assertStringContainsString('*.dump', $gitignore);
        $this->assertStringContainsString('*.sql', $gitignore);
        $this->assertStringContainsString('*.sql.gz', $gitignore);
        $this->assertStringContainsString('/backups', $gitignore);
    }

    public function test_laravel_scheduler_does_not_run_database_backups(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $this->assertStringNotContainsString('pg_dump', $schedule);
        $this->assertStringNotContainsString('backup', strtolower($schedule));
    }
}
