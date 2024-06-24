<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueEntryValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        $context = $this->context;
        $existingEntry = $this->entityManager->getRepository($context->getClassName())->findOneBy([
            $context->getPropertyName() => $value,
        ]);

        if ($existingEntry !== null) {

            $currentObject = $context->getObject();
            $currentId = $currentObject->getId();

            // Check if the existing entry belongs to the current object
            if ($existingEntry->getId() !== $currentId) {
                $context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $value)
                    ->setParameter('{{ existingEntry }}', $existingEntry->getId())
                    ->addViolation();
            }
        }
    }
}