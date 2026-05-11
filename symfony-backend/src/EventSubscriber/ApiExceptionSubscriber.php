<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\ApiUnauthorizedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onException',
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof ApiUnauthorizedException) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['message' => 'No autorizado'],
            Response::HTTP_UNAUTHORIZED
        ));
    }
}
