<?php

return [
    'package_name' => env('GOOGLE_ANDROID_PACKAGE_NAME'),

    'service_account_path' => storage_path(
        'app/' . env('GOOGLE_APPLICATION_CREDENTIALS')
    ),

    // 5 minutes
    'max_age_ms' => (int) env('PLAY_INTEGRITY_MAX_AGE_MS', 300000),

    // Set true if you want to block sideloaded/unlicensed installs.
    'require_license' => filter_var(
        env('PLAY_INTEGRITY_REQUIRE_LICENSE', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];