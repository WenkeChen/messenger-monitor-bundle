<?php

declare(strict_types=1);

namespace SymfonyCasts\MessengerMonitorBundle\Tests\FunctionalTests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\MessageBusInterface;
use SymfonyCasts\MessengerMonitorBundle\Tests\TestableMessage;
use SymfonyCasts\MessengerMonitorBundle\Tests\TestKernel;

/** @group functional */
final class NoFailureTransportTest extends AbstractFunctionalTests
{
    /** @var KernelBrowser */
    protected $client;
    /** @var MessageBusInterface $messageBus */
    protected $messageBus;

    protected static function createKernel(array $options = [])
    {
        return TestKernel::withMessengerConfig(
            [
                'transports' => [
                    'queue' => [
                        'dsn' => 'doctrine://default?queue_name=queue',
                        'retry_strategy' => ['max_retries' => 0],
                    ],
                ],
                'routing' => [
                    TestableMessage::class => 'queue',
                ],
            ]
        );
    }

    public function testNoFailureTransportDisplayed(): void
    {
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->assertSame(0, $crawler->filter('#failed-messages-list')->count());
        $this->assertStringNotContainsString('<h2>Failure transport</h2>', $crawler->filter('body')->html());
    }

    public function testRetryRouteLeadsToRedirectWithErrorMessage(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/failed-message/retry/123');
        self::assertResponseIsSuccessful();

        $this->assertAlertIsPresent($crawler, '.alert-danger', 'Action impossible: a failure transport should be configured first.');
    }

    public function testRejectRouteLeadsToRedirectWithErrorMessage(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/failed-message/reject/123');
        self::assertResponseIsSuccessful();

        $this->assertAlertIsPresent($crawler, '.alert-danger', 'Action impossible: a failure transport should be configured first.');
    }
}
