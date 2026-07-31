<?php

namespace App\Services\Member;

use App\Models\Achievement;
use App\Models\ArticleRead;
use App\Models\ImpactTransaction;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MemberAchievementService
{
    public function list(User $user): array
    {
        $completedPercent = (int) config('services.impact.completed_read_percent', 80);

        $achievements = Achievement::query()->orderBy('id')->get();
        $existing = UserAchievement::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('achievement_id');

        $completedReads = ArticleRead::query()
            ->where('user_id', $user->id)
            ->where('read_percent', '>=', $completedPercent)
            ->count();

        $organizationsSupported = ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->distinct('organization_profile_id')
            ->count('organization_profile_id');

        $totalDonated = (float) ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->sum('amount');

        $currentStreak = $this->currentStreak($user);
        $longestStreak = $this->longestStreak($user);

        $items = [];
        $earned = 0;
        $totalPoints = 0;

        foreach ($achievements as $achievement) {
            $progress = $this->progressFor($achievement, [
                'completed_reads' => $completedReads,
                'organizations' => $organizationsSupported,
                'donated' => $totalDonated,
                'streak' => $longestStreak,
            ]);
            $target = max(1, (int) $achievement->target);
            $isEarned = $progress >= $target;

            $userAchievement = $existing->get($achievement->id);
            if ($isEarned && $userAchievement === null) {
                $userAchievement = UserAchievement::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                    'progress' => $progress,
                    'unlocked_at' => now(),
                ]);
            } elseif ($userAchievement !== null && $userAchievement->progress !== $progress) {
                $userAchievement->progress = $progress;
                if ($isEarned && $userAchievement->unlocked_at === null) {
                    $userAchievement->unlocked_at = now();
                }
                $userAchievement->save();
            }

            if ($isEarned) {
                $earned++;
                $totalPoints += (int) $achievement->points;
            }

            $items[] = [
                'public_id' => $userAchievement?->public_id ?? (string) Str::uuid(),
                'key' => $achievement->key,
                'title' => $achievement->title,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'category' => $achievement->category ?? 'general',
                'earned' => $isEarned,
                'earned_at' => $userAchievement?->unlocked_at?->toIso8601String(),
                'unlocked_at' => $userAchievement?->unlocked_at?->toIso8601String(),
                'progress' => min($progress, $target),
                'goal' => $target,
                'target' => $target,
                'points' => (int) $achievement->points,
            ];
        }

        $total = count($items);
        $level = max(1, (int) floor($totalPoints / 100) + 1);
        $nextLevelPoints = $level * 100;

        return [
            'achievements' => $items,
            'totals' => [
                'earned' => $earned,
                'total' => $total,
                'points' => $totalPoints,
                'level' => $level,
                'next_level_points' => $nextLevelPoints,
                'current_streak' => $currentStreak,
                'longest_streak' => $longestStreak,
            ],
        ];
    }

    protected function progressFor(Achievement $achievement, array $stats): int
    {
        return match ($achievement->key) {
            'first_read', 'ten_reads' => (int) $stats['completed_reads'],
            'week_streak' => (int) $stats['streak'],
            'five_organizations' => (int) $stats['organizations'],
            'first_dollar' => (int) ($stats['donated'] >= 1 ? 1 : 0),
            default => 0,
        };
    }

    protected function currentStreak(User $user): int
    {
        $dates = ArticleRead::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn ($d): string => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        $streak = 0;
        $cursor = now()->toDateString();
        foreach ($dates as $date) {
            if ($date === $cursor) {
                $streak++;
                $cursor = Carbon::parse($cursor)->subDay()->toDateString();
            } else {
                break;
            }
        }

        return $streak;
    }

    protected function longestStreak(User $user): int
    {
        $dates = ArticleRead::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->pluck('created_at')
            ->map(fn ($d): string => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        $longest = 0;
        $current = 0;
        $prev = null;
        foreach ($dates as $date) {
            if ($prev !== null && Carbon::parse($prev)->addDay()->toDateString() === $date) {
                $current++;
            } else {
                $current = 1;
            }
            $longest = max($longest, $current);
            $prev = $date;
        }

        return $longest;
    }
}
