<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Security;

final class PasswordHashGenerator
{
    public static function create(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ["cost" => 10]);
    }
}