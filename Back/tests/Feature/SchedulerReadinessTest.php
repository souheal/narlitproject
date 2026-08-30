<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerReadinessTest extends TestCase
{
    public function test_expected_maintenance_commands_are_scheduled_once_with_overlap_protection(): void
    {
        $events = collect(app(Schedule::class)->events());

        $idempotencyEvents = $events->filter(fn ($event): bool => str_contains((string) $event->command, 'idempotency:cleanup'));
        $sanctumEvents = $events->filter(fn ($event): bool => str_contains((string) $event->command, 'sanctum:cleanup-expired-tokens'));
        $payoutEvents = $events->filter(fn ($event): bool => str_contains((string) $event->command, 'payout'));

        $this->assertCount(1, $idempotencyEvents);
        $this->assertSame('0 * * * *', $idempotencyEvents->first()->expression);
        $this->assertTrue($idempotencyEvents->first()->withoutOverlapping);

        $this->assertCount(1, $sanctumEvents);
        $this->assertSame('0 0 * * *', $sanctumEvents->first()->expression);
        $this->assertTrue($sanctumEvents->first()->withoutOverlapping);

        $this->assertCount(0, $payoutEvents);
    }

    public function test_schedule_list_reports_registered_maintenance_commands(): void
    {
        $exitCode = Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('idempotency:cleanup', $output);
        $this->assertStringContainsString('sanctum:cleanup-expired-tokens', $output);
        $this->assertStringNotContainsString('payout', strtolower($output));
    }
}
