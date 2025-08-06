<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RequestContext;

class XForwardedPrefixListener
{
    public function __construct(
        private readonly RequestContext $requestContext
    )
    {
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        // abort — potentially spoofed header
        if (!$request->isFromTrustedProxy()) {
            return;
        }

        $prefix = $request->headers->get('X-Forwarded-Prefix');
        if ($prefix) {
            $this->requestContext->setBaseUrl($prefix);
        }
    }
}