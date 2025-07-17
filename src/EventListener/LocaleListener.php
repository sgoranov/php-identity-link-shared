<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

class LocaleListener
{
    private string $defaultLocale;

    public function __construct(string $defaultLocale = 'en')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $locale = $request->cookies->get('user_lang', $this->defaultLocale);
        $request->setLocale($locale);
    }
}