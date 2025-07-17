<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class LocaleController extends AbstractController
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        $availableLocales = $this->getAvailableLocales();
        if (!in_array($locale, $availableLocales, true)) {
            $locale = 'en';
        }

        $referer = $request->headers->get('referer') ?? $this->generateUrl('security_login');
        $response = new RedirectResponse($referer);

        $cookie = Cookie::create(
            'user_lang',
            $locale,
            strtotime('+1 year'),
            '/',
            $this->getCookieDomain($request->getHost())
        );
        $response->headers->setCookie($cookie);

        return $response;
    }

    private function getAvailableLocales(): array
    {
        return $this->cache->get('available_locales', function (ItemInterface $item) {

            $item->expiresAfter(3600 * 24); // Cache for 24 hours

            $finder = new Finder();
            $locales = [];

            $projectDir = $this->getParameter('kernel.project_dir');

            $dirsToScan = [
                $projectDir . '/translations',
                $projectDir . '/local_theme/translations',
            ];

            $finder->files()->in($dirsToScan)->name('/\.[a-zA-Z_]+\.yaml$/');

            foreach ($finder as $file) {
                if (preg_match('/\.(?P<locale>[a-zA-Z_]+)\.yaml$/', $file->getFilename(), $matches)) {
                    $locales[] = $matches['locale'];
                }
            }

            return array_unique($locales);
        });
    }

    private function getCookieDomain(string $host): ?string
    {
        // Handle localhost or IP addresses — no domain attribute
        if (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost') {
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) > 2) {
            array_shift($parts);
            return '.' . implode('.', $parts);
        }

        return $host;
    }
}