<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Serializer\Exception;


use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class InvalidUuidException extends UnexpectedValueException
{

    private string $uuid;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }
}