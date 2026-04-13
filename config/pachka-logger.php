<?php

return [

    // Pachka incoming webhook URL
    'webhook_url' => env('PACHKA_LOGGER_WEBHOOK_URL', ''),

    // Blade template for message formatting
    // Available: 'pachka-logging::standard', 'pachka-logging::minimal'
    'template' => env('PACHKA_LOGGER_TEMPLATE', 'pachka-logging::standard'),

    // HTTP request timeout in seconds
    'timeout' => env('PACHKA_LOGGER_TIMEOUT', 10),

    // Enable async (queue-based) message sending
    // When true, messages are formatted immediately but HTTP requests are sent via queue worker
    'async' => env('PACHKA_LOGGER_ASYNC', false),

    // Queue connection name (e.g. 'redis', 'sqs'). null = default connection from QUEUE_CONNECTION
    'queue_connection' => env('PACHKA_LOGGER_QUEUE_CONNECTION'),

    // Queue name (e.g. 'logs', 'notifications'). null = default queue
    'queue' => env('PACHKA_LOGGER_QUEUE'),

];
