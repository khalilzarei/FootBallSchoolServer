<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Football School API',
        'debug' => true,
        'timezone' => 'UTC',
        'token_lifetime_days' => 30,
        'media_max_upload_size_mb' => 200,
    ],

    'chat' => [
        'node_internal_url' => 'http://127.0.0.1:6001/internal/broadcast',
        'internal_secret' => 'CHANGE_THIS_STRONG_SECRET',
    ],
];