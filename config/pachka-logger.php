<?php

return [

    // Pachka incoming webhook URL
    'webhook_url' => env('PACHKA_LOGGER_WEBHOOK_URL', ''),

    // Blade template for message formatting
    // Available: 'pachka-logging::standard', 'pachka-logging::minimal'
    'template' => env('PACHKA_LOGGER_TEMPLATE', 'pachka-logging::standard'),

    // HTTP request timeout in seconds
    'timeout' => env('PACHKA_LOGGER_TIMEOUT', 10),

];
