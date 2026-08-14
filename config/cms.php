<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anonymous session cookie
    |--------------------------------------------------------------------------
    |
    | Readers don't need an account to read articles (§9). This cookie holds
    | an opaque, server-issued session identifier used to correlate reading
    | and video engagement across a visit — and across visits, until it
    | expires — without collecting any personal information.
    |
    */

    'anonymous_session_cookie' => env('ANONYMOUS_SESSION_COOKIE', 'cmcphp_session'),

    'anonymous_session_lifetime_days' => 400, // ~13 months, browser cookie cap

    /*
    |--------------------------------------------------------------------------
    | Tracking ingestion
    |--------------------------------------------------------------------------
    */

    'tracking' => [
        'max_events_per_batch' => 50,
        'rate_limit_per_minute' => 120,
    ],
];
