<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\EventSubscriber;

use DomainException;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class JsonExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->shouldHandle($request)) {
            return;
        }

        $exception = $event->getThrowable();

        $statusCode = 500;
        $message = 'Internal server error';

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $this->sanitizeMessage($exception->getMessage(), $statusCode);
        } elseif ($exception instanceof DomainException || $exception instanceof InvalidArgumentException) {
            $statusCode = 400;
            $message = $this->sanitizeMessage($exception->getMessage(), $statusCode);
        }

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => $statusCode,
                'message' => $message,
            ],
        ], $statusCode));
    }

    private function shouldHandle(Request $request): bool
    {
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/api')) {
            return true;
        }

        $acceptHeader = $request->headers->get('Accept') ?? '';

        return str_contains($acceptHeader, 'application/json');
    }

    private function sanitizeMessage(string $message, int $statusCode): string
    {
        $trimmed = trim($message);

        if ('' === $trimmed) {
            return $statusCode >= 500 ? 'Internal server error' : 'Bad request';
        }

        return $trimmed;
    }
}
