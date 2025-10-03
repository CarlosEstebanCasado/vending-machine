<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\UI\Http;

use App\Shared\UI\Http\EventSubscriber\JsonExceptionSubscriber;
use DomainException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final class JsonExceptionSubscriberTest extends TestCase
{
    public function testItSubscribesToKernelException(): void
    {
        $events = JsonExceptionSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
    }

    public function testItReturnsJsonResponseForDomainExceptions(): void
    {
        $subscriber = new JsonExceptionSubscriber();
        $event = $this->createEvent('/api/test', new DomainException('Invalid payload'));

        $subscriber->onKernelException($event);

        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            [
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid payload',
                ],
            ],
            $body
        );
    }

    public function testItDoesNotOverrideNonApiRequests(): void
    {
        $subscriber = new JsonExceptionSubscriber();
        $event = $this->createEvent('/backoffice', new NotFoundHttpException('Not found'));

        $subscriber->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function createEvent(string $path, Throwable $throwable): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($path);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
