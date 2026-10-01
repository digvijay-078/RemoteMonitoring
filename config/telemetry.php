<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telemetry Data Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to retain historical device telemetry rows in MySQL.
    | The `monitor:prune-telemetry` command purges rows older than this threshold.
    |
    */
    'retention_days' => (int) env('TELEMETRY_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Ingestion Limits
    |--------------------------------------------------------------------------
    |
    | Maximum number of events accepted in a single batch upload request.
    |
    */
    'max_batch_size' => 100,

    /*
    |--------------------------------------------------------------------------
    | Metadata Payload Limit
    |--------------------------------------------------------------------------
    |
    | Maximum byte size for the arbitrary metadata JSON field per event.
    |
    */
    'max_metadata_bytes' => 1024,

    /*
    |--------------------------------------------------------------------------
    | Live Broadcast Threshold (Seconds)
    |--------------------------------------------------------------------------
    |
    | Events captured within this many seconds of server receipt are considered
    | fresh and broadcast to the Admin realtime visualizer. Older catch-up
    | events from offline drains are persisted without broadcast storming.
    |
    */
    'live_broadcast_threshold_seconds' => 15,
];
