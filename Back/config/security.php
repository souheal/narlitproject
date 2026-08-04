<?php

return [
    'admin_rate_limits' => [
        'read_per_minute' => (int) env('ADMIN_READ_RATE_PER_MINUTE', 120),
        'destructive_per_minute' => (int) env('ADMIN_DESTRUCTIVE_RATE_PER_MINUTE', 10),
        'audit_export_per_hour' => (int) env('ADMIN_AUDIT_EXPORT_RATE_PER_HOUR', 3),
        'analytics_per_minute' => (int) env('ADMIN_ANALYTICS_RATE_PER_MINUTE', 30),
        'settings_update_per_minute' => (int) env('ADMIN_SETTINGS_UPDATE_RATE_PER_MINUTE', 5),
    ],
];
