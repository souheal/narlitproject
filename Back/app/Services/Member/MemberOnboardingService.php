<?php

namespace App\Services\Member;

use App\Models\User;
use App\Models\UserPreference;

class MemberOnboardingService
{
    public function complete(User $user, array $data): array
    {
        $prefs = UserPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'categories' => [],
                'followed_organizations' => [],
            ]
        );

        $categories = $data['categories'] ?? ($data['causes'] ?? null);
        if (is_array($categories)) {
            $prefs->categories = array_values(array_filter($categories, fn ($v) => is_string($v)));
        }

        $organizations = $data['organizations'] ?? ($data['follows'] ?? null);
        if (is_array($organizations)) {
            $prefs->followed_organizations = array_values(array_filter($organizations, fn ($v) => is_string($v)));
        }

        if (array_key_exists('monthly_reading_goal', $data)) {
            $prefs->monthly_reading_goal = max(1, (int) $data['monthly_reading_goal']);
        }

        if (array_key_exists('email_digest', $data) && is_string($data['email_digest'])) {
            $prefs->email_digest = $data['email_digest'];
            $prefs->digest_frequency = match ($data['email_digest']) {
                'off' => 'never',
                'daily' => 'daily',
                'monthly' => 'monthly',
                default => 'weekly',
            };
        }

        if (array_key_exists('push_achievements', $data)) {
            $prefs->push_achievements = (bool) $data['push_achievements'];
        }

        $prefs->onboarded_at = now();
        $prefs->save();

        return ['completed' => true];
    }
}
