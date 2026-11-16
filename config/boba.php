<?php

return [
    // Presentation timezone. Storage is always UTC.
    'display_timezone' => 'Africa/Lagos',

    'force_https' => (bool) env('BOBA_FORCE_HTTPS', false),

    'membership' => [
        // Confirmed policy 2026-10-11-v1: inclusive birth-date screening range.
        'screening_version' => '2026-10-11-v1',
        'birth_date_from' => '1980-01-01',
        'birth_date_to' => '1982-12-31',
        'status_token_days' => 90,
        // Applications are rate limited per network address.
        'applications_per_hour' => 5,
    ],

    'accounts' => [
        'activation_token_days' => 7,
        'reset_token_minutes' => 60,
        'password_min_length' => 12,
        'login_attempts_per_minute' => 5,
    ],

    'privacy_notice_version' => 'draft-2026-11',

    'backup' => [
        'public_key' => env('BOBA_BACKUP_PUBLIC_KEY'),
        // Outside the public webroot. Defaults to storage/app/private/backups.
        'path' => env('BOBA_BACKUP_PATH') ?: storage_path('app/private/backups'),
        // Operational tables whose contents are not needed after a restore.
        'schema_only_tables' => ['sessions', 'cache', 'cache_locks'],
    ],

    'uploads' => [
        'image_max_kb' => 5120,
        'image_max_pixels' => 6000,
        'document_max_kb' => 10240,
        'import_max_kb' => 2048,
        'import_max_rows' => 2000,
    ],
];
