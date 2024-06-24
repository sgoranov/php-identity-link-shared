<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Serializer\Normalizer;

use Doctrine\ORM\EntityManagerInterface;
use sgoranov\PHPIdentityLinkShared\Serializer\Exception\InvalidUuidException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DoctrineManyToOneNormalizer implements NormalizerInterface, DenormalizerInterface
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): object
    {
        $entity = $this->entityManager->find($type, $data);

        if (!$entity) {
            $exception = new InvalidUuidException();
            $exception->setUuid($data);

            throw $exception;
        }

        return $entity;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        $supportedTypes = $this->getSupportedTypes($format);
        if (isset($supportedTypes[$type]) && $supportedTypes[$type] && !isset($context['object_to_populate'])) {

            return true;
        }

        return false;
    }

    public function normalize(mixed $object, string $format = null, array $context = []): string
    {
        return $object->getId();
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        $supportedTypes = $this->getSupportedTypes($format);

        // Rely on $context['cache_key'] to determine whether we are normalizing
        // an attribute from the main object or the main object itself.
        // In the case of an attribute, caching is enabled by default.
        if (is_object($data) && array_key_exists($data::class, $supportedTypes) &&
            $supportedTypes[$data::class] && isset($context['cache_key'])) {

            return true;
        }

        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $entities = [];
        foreach ($metaData as $meta) {
            $entities[$meta->getName()] = true;
        }

        return $entities;
    }
}