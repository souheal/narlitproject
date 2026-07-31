<?php

namespace App\Services\Member;

use App\Models\User;
use App\Models\UserPreference;

class MemberPreferenceService
{
    public function show(User $user): array
    {
        $prefs = $this->ensure($user);

        return ['preferences' => $this->present($prefs)];
    }

    public function update(User $user, array $data): array
    {
        $prefs = $this->ensure($user);

        $fillable = [
            'categories',
            'followed_organizations',
            'email_notifications',
            'push_notifications',
            'email_new_articles',
            'email_new_from_supported',
            'email_impact_summary',
            'email_product_updates',
            'push_new_articles',
            'push_achievements',
            'push_payment_events',
            'digest_frequency',
            'email_digest',
            'theme',
            'language',
            'timezone',
            'monthly_reading_goal',
        ];

        foreach ($fillable as $key) {
            if (array_key_exists($key, $data)) {
                $prefs->{$key} = $data[$key];
            }
        }

        if (isset($data['email_digest']) && ! isset($data['digest_frequency'])) {
            $prefs->digest_frequency = $this->mapEmailDigestToFrequency($data['email_digest']);
        }

        $prefs->save();

        return ['preferences' => $this->present($prefs->refresh())];
    }

    public function ensure(User $user): UserPreference
    {
        return UserPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'categories' => [],
                'followed_organizations' => [],
            ]
        );
    }

    public function present(UserPreference $prefs): array
    {
        return [
            'categories' => $prefs->categories ?? [],
            'followed_organizations' => $prefs->followed_organizations ?? [],
            'email_notifications' => (bool) $prefs->email_notifications,
            'push_notifications' => (bool) $prefs->push_notifications,
            'email_digest' => $prefs->email_digest ?? 'weekly',
            'email_new_articles' => (bool) $prefs->email_new_articles,
            'email_new_from_supported' => (bool) $prefs->email_new_from_supported,
            'email_impact_summary' => (bool) $prefs->email_impact_summary,
            'email_product_updates' => (bool) $prefs->email_product_updates,
            'push_new_articles' => (bool) $prefs->push_new_articles,
            'push_achievements' => (bool) $prefs->push_achievements,
            'push_payment_events' => (bool) $prefs->push_payment_events,
            'digest_frequency' => $prefs->digest_frequency ?? 'weekly',
            'theme' => $prefs->theme ?? 'system',
            'language' => $prefs->language ?? 'en',
            'timezone' => $prefs->timezone ?? 'UTC',
            'monthly_reading_goal' => (int) $prefs->monthly_reading_goal,
        ];
    }

    protected function mapEmailDigestToFrequency(string $value): string
    {
        return match ($value) {
            'off' => 'never',
            'daily' => 'daily',
            'monthly' => 'monthly',
            default => 'weekly',
        };
    }
}
