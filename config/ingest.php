<?php

return [
    'webhook_secret' => env('INGEST_WEBHOOK_SECRET'),
    'require_signature' => (bool) env('INGEST_REQUIRE_SIGNATURE', true),
    'signature_tolerance_seconds' => (int) env('INGEST_SIGNATURE_TOLERANCE_SECONDS', 300),
    'verify_object_hash' => (bool) env('INGEST_VERIFY_OBJECT_HASH', false),
    'notify_on_enqueue' => (bool) env('INGEST_NOTIFY_ON_ENQUEUE', true),
    'system_user_id' => env('INGEST_SYSTEM_USER_ID'),
    'system_user_email' => env('INGEST_SYSTEM_USER_EMAIL', 'admin@admin.com'),
    'queue' => env('INGEST_QUEUE'),
];

