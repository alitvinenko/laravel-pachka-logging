<?php

declare(strict_types=1);

namespace Pachka\Logging;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

class PachkaLogger
{
    /** @param array<string, mixed> $config */
    public function __invoke(array $config): Logger
    {
        $webhookUrl = $config['webhook_url'] ?? config('pachka-logger.webhook_url', '');
        $appName = config('app.name', 'Laravel');
        $appEnv = config('app.env', 'production');
        $level = Level::fromName($config['level'] ?? 'debug');

        $handler = new PachkaHandler(
            webhookUrl: $webhookUrl,
            appName: $appName,
            appEnv: $appEnv,
            level: $level,
        );

        return new Logger('pachka', [$handler], [
            new IntrospectionProcessor(skipClassesPartials: ['Illuminate\\', 'Monolog\\', 'NunoMaduro\\', 'Symfony\\']),
            new WebProcessor(),
        ]);
    }
}
