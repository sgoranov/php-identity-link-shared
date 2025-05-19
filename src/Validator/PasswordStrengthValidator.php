<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Validator;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use ZxcvbnPhp\Zxcvbn;

class PasswordStrengthValidator extends ConstraintValidator
{
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordStrength) {
            throw new UnexpectedTypeException($constraint, PasswordStrength::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        // Check the configuration parameter
        if (!$this->params->get('password_validation_enabled')) {
            return;
        }

        $zxcvbn = new Zxcvbn();
        $strength = $zxcvbn->passwordStrength($value);

        if ($strength['score'] < 3) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ feedback }}', implode(' ', $strength['feedback']['suggestions']))
                ->addViolation();
        }
    }
}