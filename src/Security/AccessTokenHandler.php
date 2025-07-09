<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Security;

use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
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
    private string $issuer = 'identity-link';
    private string $groupsClaim = 'groups';
    private string $adminRole = 'administrator';
    private ?Key $jwtPublicKey = null;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $factory,
    )
    {
    }

    public function setJwtPublicKey(string $path, string $algorithm = 'RS256'): void
    {
        $this->jwtPublicKey = new Key($path, $algorithm);
    }

    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    public function setIssuer(string $issuer): void
    {
        $this->issuer = $issuer;
    }

    public function setGroupsClaim(string $groupsClaim): void
    {
        $this->groupsClaim = $groupsClaim;
    }

    public function setAdminRole(string $adminRole): void
    {
        $this->adminRole = $adminRole;
    }

    public static function decode($accessToken, $keySet): object
    {
        try {
            $decoded = JWT::decode($accessToken, $keySet);

            // Get the current time
            $currentTime = time();

            // Check 'iat' claim
            if (isset($decoded->iat) && (int) $decoded->iat > $currentTime) {
                throw new BadCredentialsException('JWT token is not yet valid.');
            }

            // Check 'nbf' claim
            if (isset($decoded->nbf) && (int) $decoded->nbf > $currentTime) {
                throw new BadCredentialsException('JWT token is not yet valid.');
            }

            // Check 'exp' claim
            if (isset($decoded->exp) && (int) $decoded->exp < $currentTime) {
                throw new BadCredentialsException('JWT token has expired.');
            }

            return $decoded;

        } catch (\LogicException $e) {
            // errors having to do with environmental setup or malformed JWT Keys
            throw new BadCredentialsException('Invalid credentials.');
        } catch (\UnexpectedValueException $e) {
            // errors having to do with JWT signature and claims
            throw new BadCredentialsException('Invalid credentials.');
        }
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $key = $this->jwtPublicKey ?: $this->loadKeyFromJWKS();
        $decoded = $this->decode($accessToken, $key);

        if (isset($decoded->iss) && $this->issuer !== $decoded->iss) {
            throw new BadCredentialsException('JWT iss is not valid.');
        }

        $groups = [];
        if (isset($decoded->{$this->groupsClaim})) {
            $groups = $decoded->{$this->groupsClaim};
        }

        if (!empty($decoded->sub)) {
            $identifier = $decoded->sub;
        } else {
            $identifier = $decoded->aud;
        }

        // Create user badge
        return new UserBadge($identifier, function (string $userIdentifier, array $attribs)  use ($decoded): ?UserInterface {
            if (in_array($this->adminRole, $attribs['groups'], true)) {
                $user = new User($userIdentifier, ['ROLE_ADMIN']);
            } else {
                $user = new User($userIdentifier, []);
            }

            $user->setAccessToken($decoded);

            return $user;
        }, ['groups' => $groups]);
    }

    private function loadKeyFromJWKS(): CachedKeySet
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

        return new CachedKeySet(
            $this->uri,
            $this->client,
            $this->factory,
            $cache,
            null, // $expiresAfter int seconds to set the JWKS to expire
            true  // $rateLimit    true to enable rate limit of 10 RPS on lookup of invalid keys
        );
    }
}