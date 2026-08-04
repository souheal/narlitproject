<?php

namespace Tests\Feature\Admin\Concerns;

use App\Models\Article;
use App\Models\OrganizationProfile;
use App\Models\Payment;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\AdminRolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait InteractsWithCriticalAdminData
{
    protected function prepareCriticalAdminTest(): void
    {
        Config::set('permission.enforce_in_tests', true);
        Config::set('services.stripe.fake_checkout', true);
        Config::set('services.stripe.transfers_enabled', false);
        Cache::flush();

        foreach (['admin', 'subscriber', 'organization'] as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role],
                ['guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $this->seed(AdminRolePermissionSeeder::class);
    }

    protected function createAdminWithRole(string $role = 'super_admin', array $overrides = []): User
    {
        $adminRoleId = (int) DB::table('roles')->where('name', 'admin')->value('id');

        $user = User::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'role_id' => $adminRoleId,
            'full_name' => Str::of($role)->replace('_', ' ')->headline().' Tester',
            'username' => Str::of($role.'-'.Str::random(6))->replace('_', '-')->toString(),
            'email' => $role.'-'.Str::random(8).'@admin.test',
            'phone' => '+15550000000',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
            'is_active' => true,
            'first_login_mfa_completed_at' => now(),
            'mfa_enrolled_at' => now(),
            'failed_login_attempts' => 0,
        ], $overrides));

        $user->assignRole($role);

        return $user;
    }

    protected function createUserWithRole(string $role = 'subscriber', array $overrides = []): User
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id')
            ?? DB::table('roles')->insertGetId([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return User::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'role_id' => $roleId,
            'full_name' => Str::of($role)->headline().' Tester',
            'username' => $role.'-'.Str::random(8),
            'email' => $role.'-'.Str::random(8).'@member.test',
            'phone' => '+15551112222',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
            'is_active' => true,
            'first_login_mfa_completed_at' => now(),
            'mfa_enrolled_at' => null,
            'failed_login_attempts' => 0,
        ], $overrides));
    }

    protected function createActiveSubscription(User $user, array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_'.Str::random(12),
            'stripe_subscription_id' => 'sub_'.Str::random(12),
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
        ], $overrides));
    }

    protected function createRefundablePayment(User $user, ?Subscription $subscription = null, array $overrides = []): Payment
    {
        $subscription ??= $this->createActiveSubscription($user);

        return Payment::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'stripe_payment_intent' => 'pi_'.Str::random(12),
            'stripe_invoice_id' => 'in_'.Str::random(12),
            'amount' => '7.00',
            'stripe_fee' => '0.30',
            'net_amount' => '6.70',
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now()->subDay(),
        ], $overrides));
    }

    protected function createOrganizationProfile(array $overrides = []): OrganizationProfile
    {
        $owner = $this->createUserWithRole('organization', [
            'email' => 'org-'.Str::random(8).'@org.test',
        ]);

        return OrganizationProfile::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'organization_name' => 'Critical Aid',
            'tax_id' => Str::random(9),
            'certificate_file' => 'certificates/test.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
            'stripe_connect_account_id' => 'acct_'.Str::random(8),
            'payouts_enabled' => true,
            'charges_enabled' => true,
        ], $overrides));
    }

    protected function createArticle(OrganizationProfile $organization, array $overrides = []): Article
    {
        return Article::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'organization_profile_id' => $organization->id,
            'title' => 'Critical Article',
            'slug' => 'critical-article-'.Str::random(8),
            'excerpt' => 'Short excerpt',
            'content' => '<p>Full article body for admin moderation.</p>',
            'category' => 'Health',
            'status' => 'pending_review',
            'total_reads' => 0,
            'total_unique_reads' => 0,
            'total_points_generated' => 0,
        ], $overrides));
    }

    protected function createPendingPayoutBatch(OrganizationProfile $organization, array $overrides = []): PayoutBatch
    {
        $batch = PayoutBatch::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'batch_month' => now()->subMonth()->startOfMonth()->toDateString(),
            'total_pool' => '10.00',
            'total_distributed' => '10.00',
            'total_organizations' => 1,
            'status' => 'pending',
        ], $overrides));

        PayoutItem::query()->create([
            'payout_batch_id' => $batch->id,
            'organization_profile_id' => $organization->id,
            'engagement_score' => '10.0000',
            'payout_amount' => '10.00',
            'total_reads' => 1,
            'total_points' => 10,
            'transfer_status' => 'pending',
        ]);

        return $batch;
    }
}
