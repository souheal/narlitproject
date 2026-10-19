<?php

namespace Tests\Feature;

use App\Models\OrganizationDocument;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Notifications\Organization\OrganizationApprovedNotification;
use App\Notifications\Organization\OrganizationRejectedNotification;
use Database\Seeders\AdminRolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationReviewNotificationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.enforce_in_tests', true);
        $this->seed(AdminRolePermissionSeeder::class);
        Notification::fake();
    }

    public function test_approval_sends_one_email_to_verified_organization_owner_after_status_changes(): void
    {
        $profile = $this->organization();
        Sanctum::actingAs($this->admin('admin_content'));

        $this->postJson($this->approveUrl($profile), ['email' => 'attacker@example.test'])
            ->assertOk()
            ->assertJsonPath('data.organization.verification_status', 'approved');

        $this->assertSame('approved', $profile->refresh()->verification_status);
        $this->assertTrue($profile->user->refresh()->is_active);
        $this->assertDatabaseHas('admin_logs', ['action' => 'organization.approved', 'entity_id' => $profile->public_id]);
        Notification::assertSentOnDemand(OrganizationApprovedNotification::class, function ($notification, $channels, $notifiable): bool {
            return $notification->organizationName === 'Athar Foundation'
                && $notification->connection === 'database'
                && $notification->queue === 'mail'
                && $notifiable->routes['mail'] === 'organization@example.test';
        });
        Notification::assertSentOnDemandTimes(OrganizationApprovedNotification::class, 1);
        Notification::assertSentOnDemandTimes(OrganizationRejectedNotification::class, 0);

        $reviewedAt = $profile->reviewed_at;
        $this->postJson($this->approveUrl($profile))->assertOk();
        $this->assertEquals($reviewedAt, $profile->refresh()->reviewed_at);
        Notification::assertSentOnDemandTimes(OrganizationApprovedNotification::class, 1);
        $this->assertSame(1, DB::table('admin_logs')->where('action', 'organization.approved')->count());
    }

    public function test_rejection_sends_one_email_and_does_not_repeat_or_include_reason(): void
    {
        $profile = $this->organization();
        Sanctum::actingAs($this->admin('admin_content'));

        $this->postJson($this->rejectUrl($profile), [
            'reason' => 'Private admin note: document mismatch',
            'email' => 'attacker@example.test',
        ])->assertOk()->assertJsonPath('data.organization.verification_status', 'rejected');

        $this->assertSame('rejected', $profile->refresh()->verification_status);
        $this->assertFalse($profile->user->refresh()->is_active);
        $this->assertDatabaseHas('admin_logs', ['action' => 'organization.rejected', 'entity_id' => $profile->public_id]);
        Notification::assertSentOnDemand(OrganizationRejectedNotification::class, function ($notification, $channels, $notifiable): bool {
            return $notification->organizationName === 'Athar Foundation'
                && $notification->connection === 'database'
                && $notification->queue === 'mail'
                && $notifiable->routes['mail'] === 'organization@example.test';
        });
        Notification::assertSentOnDemandTimes(OrganizationRejectedNotification::class, 1);

        $this->postJson($this->rejectUrl($profile), ['reason' => 'A second private note'])->assertOk();
        $this->assertSame('Private admin note: document mismatch', $profile->refresh()->rejection_reason);
        Notification::assertSentOnDemandTimes(OrganizationRejectedNotification::class, 1);
        $this->assertSame(1, DB::table('admin_logs')->where('action', 'organization.rejected')->count());
    }

    public function test_email_templates_are_branded_escaped_and_exclude_private_fields(): void
    {
        $name = 'Athar <script>alert(1)</script> & Friends';

        foreach ([
            [new OrganizationApprovedNotification($name), 'Your Organization Has Been Approved — NarLit', 'Great news!'],
            [new OrganizationRejectedNotification($name), 'Update on Your Organization Application — NarLit', 'not been approved at this time'],
        ] as [$notification, $subject, $bodyFragment]) {
            $mail = $notification->toMail((object) []);
            $html = view('emails.organization.review-decision', $mail->viewData)->render();
            $plain = view('emails.organization.review-decision-text', $mail->viewData)->render();

            $this->assertSame($subject, $mail->subject);
            $this->assertSame('emails.organization.review-decision', $mail->view['html']);
            $this->assertSame('emails.organization.review-decision-text', $mail->view['text']);
            $this->assertStringContainsString('NarLit', $html);
            $this->assertStringContainsString('Athar &lt;script&gt;', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringContainsString($bodyFragment, $html);
            $this->assertStringContainsString('The NarLit Team', $plain);
            $this->assertStringContainsString('Hello '.$name.',', $plain);

            foreach (['Private admin note', 'tax_id', 'certificate_file', 'stripe_connect_account_id'] as $private) {
                $this->assertStringNotContainsString($private, $html.$plain);
            }
        }
    }

    public function test_readonly_admin_cannot_approve_or_reject(): void
    {
        $profile = $this->organization();
        Sanctum::actingAs($this->admin('admin_readonly'));

        $this->postJson($this->approveUrl($profile))->assertForbidden();
        $this->postJson($this->rejectUrl($profile), ['reason' => 'No'])->assertForbidden();

        $this->assertSame('pending', $profile->refresh()->verification_status);
        Notification::assertNothingSent();
    }

    public function test_organization_cannot_approve_or_reject_itself(): void
    {
        $profile = $this->organization();
        Sanctum::actingAs($profile->user);

        $this->postJson($this->approveUrl($profile))->assertForbidden();
        $this->postJson($this->rejectUrl($profile), ['reason' => 'No'])->assertForbidden();

        $this->assertSame('pending', $profile->refresh()->verification_status);
        Notification::assertNothingSent();
    }

    public function test_failed_approval_does_not_send_email(): void
    {
        $profile = $this->organization();
        $profile->documents()->delete();
        Sanctum::actingAs($this->admin('admin_content'));

        $this->postJson($this->approveUrl($profile))->assertStatus(422);

        $this->assertSame('pending', $profile->refresh()->verification_status);
        Notification::assertNothingSent();
    }

    public function test_rolled_back_review_does_not_send_email(): void
    {
        $profile = $this->organization();
        Sanctum::actingAs($this->admin('admin_content'));
        DB::statement("CREATE TRIGGER reject_admin_log BEFORE INSERT ON admin_logs BEGIN SELECT RAISE(ABORT, 'forced rollback'); END");

        $this->postJson($this->approveUrl($profile))->assertStatus(500);

        $this->assertSame('pending', $profile->refresh()->verification_status);
        $this->assertFalse($profile->user->refresh()->is_active);
        Notification::assertNothingSent();
    }

    public function test_notification_waits_for_outer_transaction_commit(): void
    {
        $profile = $this->organization();
        Sanctum::actingAs($this->admin('admin_content'));

        DB::beginTransaction();
        try {
            $this->postJson($this->approveUrl($profile))->assertOk();
            Notification::assertNothingSent();
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        Notification::assertSentOnDemandTimes(OrganizationApprovedNotification::class, 1);
    }

    public function test_queue_dispatch_failure_does_not_undo_committed_decision(): void
    {
        $profile = $this->organization();
        Sanctum::actingAs($this->admin('admin_content'));
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('simulated queue failure'));

        $this->postJson($this->approveUrl($profile))->assertOk();

        $this->assertSame('approved', $profile->refresh()->verification_status);
        $this->assertTrue($profile->user->refresh()->is_active);
        $this->assertDatabaseHas('admin_logs', ['action' => 'organization.approved', 'entity_id' => $profile->public_id]);
    }

    public function test_unverified_or_unrelated_owner_does_not_receive_email(): void
    {
        $profile = $this->organization(false);
        Sanctum::actingAs($this->admin('admin_content'));

        $this->postJson($this->rejectUrl($profile), ['reason' => 'Internal review'])->assertOk();
        Notification::assertNothingSent();

        $other = $this->organization();
        $other->user->forceFill(['role_id' => Role::findByName('admin_readonly', 'web')->id])->save();
        $this->postJson($this->rejectUrl($other), ['reason' => 'Internal review'])->assertOk();
        Notification::assertNothingSent();
    }

    private function organization(bool $verified = true): OrganizationProfile
    {
        $role = Role::findOrCreate('organization', 'web');
        $number = User::query()->count() + 1;
        $user = User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $role->id,
            'full_name' => 'Athar Foundation',
            'username' => 'organization_'.$number,
            'email' => $number === 1 ? 'organization@example.test' : 'organization_'.$number.'@example.test',
            'password' => 'Password123!',
            'email_verified_at' => $verified ? now() : null,
            'is_active' => false,
        ]);

        $profile = OrganizationProfile::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'organization_name' => 'Athar Foundation',
            'tax_id' => 'TAX-'.$number,
            'certificate_file' => 'private/certificate.pdf',
            'irs_verified' => true,
            'verification_status' => 'pending',
        ]);

        OrganizationDocument::create([
            'organization_profile_id' => $profile->id,
            'type' => 'certificate',
            'file_path' => 'private/certificate.pdf',
        ]);

        return $profile;
    }

    private function admin(string $roleName): User
    {
        $role = Role::findByName($roleName);
        $user = User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $role->id,
            'full_name' => 'Review Admin',
            'username' => 'review_admin',
            'email' => 'review-admin@example.test',
            'password' => 'Password123!',
            'email_verified_at' => now(),
            'mfa_enrolled_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    private function approveUrl(OrganizationProfile $profile): string
    {
        return '/api/v1/admin/organizations/'.$profile->public_id.'/approve';
    }

    private function rejectUrl(OrganizationProfile $profile): string
    {
        return '/api/v1/admin/organizations/'.$profile->public_id.'/reject';
    }
}
