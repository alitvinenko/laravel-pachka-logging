<?php

declare(strict_types=1);

namespace Pachka\Logging\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Monolog\Level;
use Monolog\Logger;
use Orchestra\Testbench\TestCase;
use Pachka\Logging\PachkaHandler;
use Pachka\Logging\PachkaLoggerServiceProvider;

class PachkaHandlerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PachkaLoggerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'TestApp');
        $app['config']->set('app.env', 'testing');
        $app['config']->set('pachka-logger.webhook_url', 'https://api.pachca.com/webhooks/incoming/test');
        $app['config']->set('pachka-logger.template', 'pachka-logging::minimal');
    }

    public function test_handler_sends_message_to_webhook(): void
    {
        // Arrange
        $history = [];
        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handlerStack]);

        $handler = new PachkaHandler(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            appName: 'TestApp',
            appEnv: 'testing',
            level: Level::Error,
        );

        // Use reflection to inject mock HTTP client
        $handler->setHttpClient($httpClient);

        $logger = new Logger('test', [$handler]);

        // Act
        $logger->error('Something went wrong', ['user_id' => 42]);

        // Assert
        $this->assertCount(1, $history);

        $request = $history[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('https://api.pachca.com/webhooks/incoming/test', (string) $request->getUri());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertArrayHasKey('message', $body);
        $this->assertStringContainsString('Something went wrong', $body['message']);
    }

    public function test_handler_does_not_send_below_level(): void
    {
        // Arrange
        $history = [];
        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handlerStack]);

        $handler = new PachkaHandler(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            appName: 'TestApp',
            appEnv: 'testing',
            level: Level::Error,
        );
        $handler->setHttpClient($httpClient);

        $logger = new Logger('test', [$handler]);

        // Act
        $logger->info('This should not be sent');

        // Assert
        $this->assertCount(0, $history);
    }

    public function test_handler_splits_long_messages(): void
    {
        // Arrange
        $history = [];
        $mock = new MockHandler([new Response(200), new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handlerStack]);

        $handler = new PachkaHandler(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            appName: 'TestApp',
            appEnv: 'testing',
        );
        $handler->setHttpClient($httpClient);

        $logger = new Logger('test', [$handler]);

        // Act — send a message longer than 4096 chars
        $longMessage = str_repeat('A', 5000);
        $logger->error($longMessage);

        // Assert — should be split into 2 requests
        $this->assertCount(2, $history);
    }

    public function test_handler_gracefully_handles_http_error(): void
    {
        // Arrange
        $mock = new MockHandler([new Response(500, [], 'Internal Server Error')]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $handler = new PachkaHandler(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            appName: 'TestApp',
            appEnv: 'testing',
        );
        $handler->setHttpClient($httpClient);

        $logger = new Logger('test', [$handler]);

        // Act & Assert — should not throw
        $logger->error('This should not crash');
        $this->assertTrue(true);
    }
}
