<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Security;

use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenHandler implements AccessTokenHandlerInterface
{
    private string $uri;
    private string $issuer;
    private string $groupsClaim;
    private string $adminRole;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $factory,
    )
    {
    }

    public function setConfigurationParams(string $jwksUri, string $issuer, string $groupsClaim, string $adminRole): void
    {
        $this->uri = $jwksUri;
        $this->issuer = $issuer;
        $this->groupsClaim = $groupsClaim;
        $this->adminRole = $adminRole;
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $cache = new FilesystemAdapter(
            $namespace = 'JWKeySet',

            // the default lifetime (in seconds) for cache items that do not define their
            // own lifetime, with a value 0 causing items to be stored indefinitely (i.e.
            // until the files are deleted)
            $defaultLifetime = 3600,

            // the main cache directory (the application needs read-write permissions on it)
            // if none is specified, a directory is created inside the system temporary directory
            $directory = null
        );

        $keySet = new CachedKeySet(
            $this->uri,
            $this->client,
            $this->factory,
            $cache,
            null, // $expiresAfter int seconds to set the JWKS to expire
            true  // $rateLimit    true to enable rate limit of 10 RPS on lookup of invalid keys
        );

        try {
            $decoded = JWT::decode($accessToken, $keySet);

            // Get the current time
            $currentTime = time();

            // Check 'iat' claim
            if (isset($decoded->iat) && $decoded->iat > $currentTime) {
                throw new BadCredentialsException('JWT token is not yet valid.');
            }

            // Check 'nbf' claim
            if (isset($decoded->nbf) && $decoded->nbf > $currentTime) {
                throw new BadCredentialsException('JWT token is not yet valid.');
            }

            // Check 'exp' claim
            if (isset($decoded->exp) && $decoded->exp < $currentTime) {
                throw new BadCredentialsException('JWT token has expired.');
            }

            if ($this->issuer !== $decoded->iss) {
                throw new BadCredentialsException('JWT iss is not valid.');
            }

            $groups = [];
            if (isset($decoded->{$this->groupsClaim})) {
                $groups = explode(' ', $decoded->{$this->groupsClaim});
            }

            // Create user badge
            return new UserBadge($decoded->sub, function (string $userIdentifier, array $attribs): ?UserInterface {
                if (in_array($this->adminRole, $attribs['groups'], true)) {
                    return new User($userIdentifier, ['ROLE_ADMIN']);
                } else {
                    return new User($userIdentifier, []);
                }
            }, ['groups' => $groups]);

        } catch (\LogicException $e) {
            // errors having to do with environmental setup or malformed JWT Keys
            throw new BadCredentialsException('Invalid credentials.');
        } catch (\UnexpectedValueException $e) {
            // errors having to do with JWT signature and claims
            throw new BadCredentialsException('Invalid credentials.');
        }
    }
}