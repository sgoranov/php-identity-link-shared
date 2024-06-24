<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class User implements UserInterface
{

    public function __construct(
        private readonly string $username,
        private readonly array $roles,
    )
    {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
        // there is no sensitive data to remove
        // from the User object
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }
}