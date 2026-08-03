<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Security;

use Firebase\JWT\Key;

final class AccessTokenHandlerConfiguration
{
    public function __construct(
        private readonly string $issuer,
        private readonly string $audience,
        private readonly ?Key $publicKey = null,
        private readonly ?string $jwksUri = null,
    )
    {
        if ('' === $this->issuer) {
            throw new \InvalidArgumentException('The token issuer must not be empty.');
        }

        if ('' === $this->audience) {
            throw new \InvalidArgumentException('The token audience must not be empty.');
        }

        if (($this->publicKey === null) === ($this->jwksUri === null)) {
            throw new \InvalidArgumentException('Configure exactly one of a public key or a JWKS URI.');
        }

        if ('' === $this->jwksUri) {
            throw new \InvalidArgumentException('The JWKS URI must not be empty.');
        }
    }

    public function getPublicKey(): ?Key
    {
        return $this->publicKey;
    }

    public function getJwksUri(): ?string
    {
        return $this->jwksUri;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }
}
