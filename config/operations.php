<?php

return [
    'alert_email' => env('OPERATIONS_ALERT_EMAIL'),
    'health_token' => env('OPERATIONS_HEALTH_TOKEN'),

    'health' => [
        'require_scheduler' => (bool) env('OPERATIONS_REQUIRE_SCHEDULER', false),
        'require_queue_worker' => (bool) env('OPERATIONS_REQUIRE_QUEUE_WORKER', false),
        'require_real_mail' => (bool) env('OPERATIONS_REQUIRE_REAL_MAIL', false),
        'heartbeat_ttl_seconds' => (int) env('OPERATIONS_HEARTBEAT_TTL_SECONDS', 180),
        'max_queue_age_seconds' => (int) env('OPERATIONS_MAX_QUEUE_AGE_SECONDS', 600),
        'max_failed_jobs' => (int) env('OPERATIONS_MAX_FAILED_JOBS', 0),
    ],

    'schedules' => [
        'stale_after_minutes' => (int) env('OPERATIONS_STALE_SCHEDULE_MINUTES', 10),
    ],
];
