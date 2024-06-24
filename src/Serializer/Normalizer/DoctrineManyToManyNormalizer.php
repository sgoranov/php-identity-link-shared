<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Serializer\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use sgoranov\PHPIdentityLinkShared\Serializer\Exception\InvalidUuidException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DoctrineManyToManyNormalizer implements NormalizerInterface, DenormalizerInterface
{
    const NUMBER_OF_ENTITIES_TO_FETCH = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): ArrayCollection
    {
        $collection = new ArrayCollection();
        foreach ($data as $uuid) {
            $entity = $this->entityManager->find(rtrim($type, '[]'), $uuid);
            if (!$entity) {
                $exception = new InvalidUuidException();
                $exception->setUuid($uuid);

                throw $exception;
            }

            $collection->add($entity);
        }

        return $collection;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        $supportedTypes = $this->getSupportedTypes($format);
        if (array_key_exists($type, $supportedTypes) && $supportedTypes[$type] && is_array($data)) {

            return true;
        }

        return false;
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        if (!($object instanceof PersistentCollection)) {
            throw new \Exception('Invalid data passed');
        }

        $entities = $object->slice(0, self::NUMBER_OF_ENTITIES_TO_FETCH + 1);

        $hasMore = count($entities) === self::NUMBER_OF_ENTITIES_TO_FETCH + 1;

        $entities = array_slice($entities, 0, self::NUMBER_OF_ENTITIES_TO_FETCH);
        $data = array_map(function($entity) {
            return $entity->getId();
        }, $entities);

        return [
            'data' => $data,
            'hasMore' => $hasMore,
        ];
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        if ($data instanceof PersistentCollection) {
            return true;
        }

        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $entities = [
            PersistentCollection::class => true,
        ];

        foreach ($metaData as $meta) {
            $entities[$meta->getName() . '[]'] = true;
        }

        return $entities;
    }
}