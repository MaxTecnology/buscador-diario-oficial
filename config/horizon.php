<?php

$horizonQueues = array_values(array_filter(array_map(
    static fn (string $queue): string => trim($queue),
    explode(',', (string) env('HORIZON_QUEUES', 'default,pdf-heavy,pdf-processing'))
)));

if ($horizonQueues === []) {
    $horizonQueues = ['default', 'pdf-heavy', 'pdf-processing'];
}

return [

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', 'diario_horizon:'),

    'middleware' => ['web'],

    'waits' => [
        'redis:default' => (int) env('HORIZON_WAIT_DEFAULT', 60),
    ],

    'trim' => [
        'recent' => (int) env('HORIZON_TRIM_RECENT', 60),
        'pending' => (int) env('HORIZON_TRIM_PENDING', 60),
        'completed' => (int) env('HORIZON_TRIM_COMPLETED', 60),
        'recent_failed' => (int) env('HORIZON_TRIM_RECENT_FAILED', 10080),
        'failed' => (int) env('HORIZON_TRIM_FAILED', 10080),
        'monitored' => (int) env('HORIZON_TRIM_MONITORED', 10080),
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => (int) env('HORIZON_TRIM_SNAPSHOTS', 24),
    ],

    'fast_termination' => false,

    'memory_limit' => (int) env('HORIZON_MEMORY_LIMIT_MB', 2048),

    'defaults' => [
        'supervisor-main' => [
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => $horizonQueues,
            'balance' => env('HORIZON_BALANCE', 'auto'),
            'autoScalingStrategy' => 'time',
            'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 3),
            'minProcesses' => (int) env('HORIZON_MIN_PROCESSES', 1),
            'maxTime' => (int) env('HORIZON_MAX_TIME', 3600),
            'maxJobs' => (int) env('HORIZON_MAX_JOBS', 500),
            'memory' => (int) env('HORIZON_WORKER_MEMORY_MB', 2048),
            'tries' => (int) env('HORIZON_TRIES', 3),
            'timeout' => (int) env('HORIZON_TIMEOUT', 1800),
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-main' => [
                'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 3),
                'minProcesses' => (int) env('HORIZON_MIN_PROCESSES', 1),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-main' => [
                'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES_LOCAL', 2),
                'minProcesses' => 1,
            ],
        ],
    ],

];
