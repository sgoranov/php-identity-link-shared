<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Serializer;

use Psr\Log\LoggerInterface;
use sgoranov\PHPIdentityLinkShared\Serializer\Exception\InvalidUuidException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Deserializer
{
    private string $error;

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function deserialize(Object $object, array $groups = []): bool
    {
        try {
            $data = $this->requestStack->getCurrentRequest()->getContent();

            $options = [
                AbstractNormalizer::OBJECT_TO_POPULATE => $object,
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
            ];

            if (!empty($groups)) {
                $options[AbstractNormalizer::GROUPS] = $groups;
            }

            $this->serializer->deserialize($data, $object::class, 'json', $options);

            $constraintViolationList = $this->validator->validate($object, null, $groups);
            if ($constraintViolationList->count() > 0) {

                // show only the first error
                foreach ($constraintViolationList as $error) {
                    $this->error = sprintf('Invalid %s. %s', $error->getPropertyPath(), $error->getMessage());
                    break;
                }

                return false;
            }

            return true;

        } catch (InvalidUuidException $e) {

            $this->error = sprintf('Invalid UUID %s passed.' , $e->getUuid());

        } catch (ExtraAttributesException $e) {

            $this->error = $e->getMessage();

        } catch (NotNormalizableValueException $e) {

            $this->error = sprintf("The %s property must be of type %s, but %s was provided.",
                $e->getPath(), implode('|', $e->getExpectedTypes()), $e->getCurrentType());

        } catch (\Exception $e) {

            $this->logger->error('Deserialization error ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            $this->error = 'Error while deserializing the data.';
        }

        return false;
    }

    public function respondWithError(): JsonResponse
    {
        return new JsonResponse([
            'error' => $this->error
        ], Response::HTTP_BAD_REQUEST);
    }
}