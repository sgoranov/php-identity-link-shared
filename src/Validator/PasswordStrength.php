<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class PasswordStrength extends Constraint
{
    public string $message = 'The password is too weak. {{ feedback }}';

    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}