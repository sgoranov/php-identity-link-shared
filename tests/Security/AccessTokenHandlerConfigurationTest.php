<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Tests\Security;

use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sgoranov\IdentityLinkShared\Security\AccessTokenHandlerConfiguration;

final class AccessTokenHandlerConfigurationTest extends TestCase
{
    public function testItCanUseAPublicKey(): void
    {
        $key = new Key('secret', 'HS256');
        $configuration = new AccessTokenHandlerConfiguration(
            issuer: 'identity-link',
            audience: 'identity-api',
            publicKey: $key,
        );

        self::assertSame($key, $configuration->getPublicKey());
        self::assertNull($configuration->getJwksUri());
        self::assertSame('identity-link', $configuration->getIssuer());
        self::assertSame('identity-api', $configuration->getAudience());
    }

    public function testItCanUseAJwksUri(): void
    {
        $configuration = new AccessTokenHandlerConfiguration(
            issuer: 'identity-link',
            audience: 'identity-api',
            jwksUri: 'https://identity.example/.well-known/jwks.json',
        );

        self::assertNull($configuration->getPublicKey());
        self::assertSame('https://identity.example/.well-known/jwks.json', $configuration->getJwksUri());
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function testItRejectsInvalidConfiguration(
        string $issuer,
        string $audience,
        ?Key $publicKey,
        ?string $jwksUri,
    ): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AccessTokenHandlerConfiguration($issuer, $audience, $publicKey, $jwksUri);
    }

    public static function invalidConfigurationProvider(): iterable
    {
        $key = new Key('secret', 'HS256');

        yield 'empty issuer' => ['', 'identity-api', $key, null];
        yield 'empty audience' => ['identity-link', '', $key, null];
        yield 'no verification source' => ['identity-link', 'identity-api', null, null];
        yield 'both verification sources' => ['identity-link', 'identity-api', $key, 'https://identity.example/jwks.json'];
        yield 'empty JWKS URI' => ['identity-link', 'identity-api', null, ''];
    }
}
