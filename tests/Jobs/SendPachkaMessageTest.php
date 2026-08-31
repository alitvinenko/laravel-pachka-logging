<?php

declare(strict_types=1);

namespace Pachka\Logging\Tests\Jobs;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Queue\Job;
use Mockery;
use Orchestra\Testbench\TestCase;
use Pachka\Logging\Jobs\SendPachkaMessage;
use Pachka\Logging\PachkaLoggerServiceProvider;

class SendPachkaMessageTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PachkaLoggerServiceProvider::class];
    }

    protected function tearDown(): void
    {
        SendPachkaMessage::setHttpClient(null);
        parent::tearDown();
    }

    public function test_job_sends_http_post(): void
    {
        // Arrange
        $history = [];
        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handlerStack]);

        SendPachkaMessage::setHttpClient($httpClient);

        $job = new SendPachkaMessage(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            text: 'Test message',
            timeout: 5,
        );

        // Act
        $job->handle();

        // Assert
        $this->assertCount(1, $history);

        $request = $history[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('https://api.pachca.com/webhooks/incoming/test', (string) $request->getUri());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertEquals(['message' => 'Test message'], $body);
    }

    public function test_job_handles_http_failure_gracefully(): void
    {
        // Arrange
        $mock = new MockHandler([new Response(500, [], 'Internal Server Error')]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        SendPachkaMessage::setHttpClient($httpClient);

        $job = new SendPachkaMessage(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            text: 'Test message',
        );

        // Act & Assert — should not throw
        $job->handle();
        $this->assertTrue(true);
    }

    public function test_job_is_released_using_retry_after_seconds_on_429(): void
    {
        // Arrange
        $mock = new MockHandler([new Response(429, ['Retry-After' => '42'], 'Too Many Requests')]);
        $handlerStack = HandlerStack::create($mock);
        SendPachkaMessage::setHttpClient(new Client(['handler' => $handlerStack]));

        $job = new SendPachkaMessage(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            text: 'Test message',
        );

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(42);
        $queueJob->shouldNotReceive('fail');
        $job->setJob($queueJob);

        // Act
        $job->handle();

        // Assert
        $this->assertTrue(true);
    }

    public function test_job_accepts_http_date_in_retry_after(): void
    {
        // Arrange
        $retryAt = gmdate('D, d M Y H:i:s \G\M\T', time() + 30);
        $mock = new MockHandler([new Response(429, ['Retry-After' => $retryAt])]);
        $handlerStack = HandlerStack::create($mock);
        SendPachkaMessage::setHttpClient(new Client(['handler' => $handlerStack]));

        $job = new SendPachkaMessage(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            text: 'Test message',
        );

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(2);
        $queueJob->shouldReceive('release')->once()->with(Mockery::on(
            fn (int $delay): bool => $delay > 25 && $delay <= 30,
        ));
        $job->setJob($queueJob);

        // Act
        $job->handle();

        // Assert
        $this->assertTrue(true);
    }

    public function test_job_falls_back_to_default_delay_without_retry_after(): void
    {
        // Arrange
        $mock = new MockHandler([new Response(429)]);
        $handlerStack = HandlerStack::create($mock);
        SendPachkaMessage::setHttpClient(new Client(['handler' => $handlerStack]));

        $job = new SendPachkaMessage(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            text: 'Test message',
        );

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(10);
        $job->setJob($queueJob);

        // Act
        $job->handle();

        // Assert
        $this->assertTrue(true);
    }

    public function test_job_fails_after_five_rate_limited_attempts(): void
    {
        // Arrange
        $mock = new MockHandler([new Response(429, ['Retry-After' => '5'])]);
        $handlerStack = HandlerStack::create($mock);
        SendPachkaMessage::setHttpClient(new Client(['handler' => $handlerStack]));

        $job = new SendPachkaMessage(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            text: 'Test message',
        );

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(5);
        $queueJob->shouldNotReceive('release');
        $queueJob->shouldReceive('fail')->once();
        $job->setJob($queueJob);

        // Act
        $job->handle();

        // Assert
        $this->assertTrue(true);
    }

    public function test_job_does_not_retry_on_non_429_errors(): void
    {
        // Arrange
        $mock = new MockHandler([new Response(500)]);
        $handlerStack = HandlerStack::create($mock);
        SendPachkaMessage::setHttpClient(new Client(['handler' => $handlerStack]));

        $job = new SendPachkaMessage(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            text: 'Test message',
        );

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldNotReceive('release');
        $queueJob->shouldNotReceive('fail');
        $job->setJob($queueJob);

        // Act
        $job->handle();

        // Assert
        $this->assertTrue(true);
    }
}
