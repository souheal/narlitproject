<?php

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserService
{
    public function __construct(
        protected PasswordResetService $passwordResetService,
    ) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = User::query()
            ->select('users.*')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->addSelect('roles.name as role_name')
            ->selectSub($this->latestSubscriptionStatusSubquery(), 'subscription_status')
            ->withCount([
                'articleReads as completed_article_reads_count' => fn (Builder $read): Builder => $read
                    ->where('counted_for_payout', true),
            ])
            ->withSum('impactTransactions as impact_amount_sum', 'amount');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query->paginate($this->perPage($request));
    }

    public function details(string $publicId): User
    {
        $user = User::query()
            ->select('users.*')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->addSelect('roles.name as role_name')
            ->with([
                'latestSubscriptionForAdmin',
                'paymentsForAdmin',
            ])
            ->withCount([
                'articleReads as article_reads_count',
                'articleReads as completed_article_reads_count' => fn (Builder $read): Builder => $read
                    ->where('counted_for_payout', true),
            ])
            ->withSum('articleReads as article_reads_points_sum', 'points_earned')
            ->withSum('impactTransactions as impact_amount_sum', 'amount')
            ->where('users.public_id', $publicId)
            ->first();

        if ($user === null) {
            throw new ApiException('User was not found.', 404);
        }

        $user->setAttribute('paid_payments_sum', Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount'));
        $user->setAttribute('paid_payments_count', Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->count());
        $user->setAttribute('failed_payments_count', Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'failed')
            ->count());
        $user->setAttribute('organizations_supported_count', DB::table('impact_transactions')->where('user_id', $user->id)->distinct('organization_profile_id')->count('organization_profile_id'));
        $user->setAttribute('admin_action_history', DB::table('admin_logs')
            ->join('users as admins', 'admins.id', '=', 'admin_logs.admin_id')
            ->where('admin_logs.entity_type', 'user')
            ->where('admin_logs.entity_id', $user->public_id)
            ->latest('admin_logs.created_at')
            ->limit(10)
            ->get(['admin_logs.action', 'admin_logs.created_at', 'admins.full_name as admin_name']));

        return $user;
    }

    public function updateStatus(User $admin, string $publicId, bool $isActive, Request $request): User
    {
        $target = $this->findUser($publicId);

        if ($target->id === $admin->id && ! $isActive) {
            throw new ApiException('You cannot suspend your own active admin session.', 422);
        }

        return DB::transaction(function () use ($admin, $target, $isActive, $request): User {
            $target->forceFill([
                'is_active' => $isActive,
            ])->save();

            $this->log($admin, $target, $isActive ? 'user.activated' : 'user.suspended', $request);

            return $target->refresh();
        }, 3);
    }

    public function sendPasswordReset(User $admin, string $publicId, Request $request): void
    {
        $target = $this->findUser($publicId);

        DB::transaction(function () use ($admin, $target, $request): void {
            $this->passwordResetService->sendResetOtp($target->email);
            $this->log($admin, $target, 'user.password_reset_sent', $request);
        }, 3);
    }

    public function resetMfa(User $admin, string $publicId, Request $request): User
    {
        $target = $this->findUser($publicId);

        if ($target->id === $admin->id) {
            throw new ApiException('You cannot reset MFA for your own active admin session.', 422);
        }

        return DB::transaction(function () use ($admin, $target, $request): User {
            $target->forceFill([
                'phone_mfa_code' => null,
                'phone_mfa_expires_at' => null,
                'phone_mfa_verified_at' => null,
                'first_login_mfa_completed_at' => null,
            ])->save();

            $this->log($admin, $target, 'user.mfa_reset', $request);

            return $target->refresh();
        }, 3);
    }

    public function revokeTokens(User $admin, string $publicId, Request $request): int
    {
        $target = $this->findUser($publicId);

        if ($target->id === $admin->id) {
            throw new ApiException('You cannot revoke tokens for your own active admin session.', 422);
        }

        return DB::transaction(function () use ($admin, $target, $request): int {
            $count = $target->tokens()->count();
            $target->tokens()->delete();
            $this->log($admin, $target, 'user.tokens_revoked', $request, ['tokens_revoked' => $count]);

            return $count;
        }, 3);
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested->where('users.full_name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('roles.name', $request->query('role'));
        }

        if ($request->filled('account_status')) {
            $query->where('users.is_active', $request->query('account_status') === 'active');
        }

        if ($request->filled('email_verified')) {
            $request->boolean('email_verified')
                ? $query->whereNotNull('users.email_verified_at')
                : $query->whereNull('users.email_verified_at');
        }

        if ($request->filled('mfa_status')) {
            $request->query('mfa_status') === 'completed'
                ? $query->whereNotNull('users.first_login_mfa_completed_at')
                : $query->whereNull('users.first_login_mfa_completed_at');
        }

        if ($request->filled('registered_from')) {
            $query->whereDate('users.created_at', '>=', $request->query('registered_from'));
        }

        if ($request->filled('registered_to')) {
            $query->whereDate('users.created_at', '<=', $request->query('registered_to'));
        }

        if ($request->filled('subscription_status')) {
            $this->applySubscriptionStatusFilter($query, (string) $request->query('subscription_status'));
        }
    }

    protected function applySubscriptionStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'active') {
            $query->whereHas('subscriptions', fn (Builder $subscription): Builder => $subscription
                ->where('status', 'active')
                ->where('expires_at', '>', now()));

            return;
        }

        if ($status === 'inactive') {
            $query->whereDoesntHave('subscriptions', fn (Builder $subscription): Builder => $subscription
                ->where('status', 'active')
                ->where('expires_at', '>', now()));

            return;
        }

        $query->whereHas('subscriptions', fn (Builder $subscription): Builder => $subscription->where('status', $status));
    }

    protected function applySorting(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'date');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'name' => $query->orderBy('users.full_name', $direction),
            'last_login' => $query->orderBy('users.last_login_at', $direction),
            'status' => $query->orderBy('users.is_active', $direction),
            default => $query->orderBy('users.created_at', $direction),
        };

        $query->orderBy('users.id', 'desc');
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 15), 1), 100);
    }

    protected function latestSubscriptionStatusSubquery(): Builder
    {
        return Subscription::query()
            ->select('status')
            ->whereColumn('subscriptions.user_id', 'users.id')
            ->latest('started_at')
            ->limit(1);
    }

    protected function findUser(string $publicId): User
    {
        $user = User::query()->where('public_id', $publicId)->first();

        if ($user === null) {
            throw new ApiException('User was not found.', 404);
        }

        return $user;
    }

    protected function log(User $admin, User $target, string $action, Request $request, array $metadata = []): void
    {
        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => 'user',
            'entity_id' => $target->public_id,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => $metadata === [] ? null : json_encode($metadata),
            'created_at' => now(),
        ]);
    }
}
