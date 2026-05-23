<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContext;

class RequestContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestContext $requestContext,
        private readonly string $appBaseUrl,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $parsed = parse_url($this->appBaseUrl);
        if (empty($parsed['host'])) {
            return;
        }

        $this->requestContext->setHost($parsed['host']);
        $this->requestContext->setScheme($parsed['scheme'] ?? 'https');
        $this->requestContext->setHttpPort(80);
        $this->requestContext->setHttpsPort(443);
    }
}
