<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Controller;

use sgoranov\PHPIdentityLinkShared\Api\DTO\AbstractQueryRequest;
use sgoranov\PHPIdentityLinkShared\Serializer\Deserializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class QueryController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly Deserializer $deserializer,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function query(AbstractQueryRequest $request): Response
    {
        if (!$this->deserializer->deserialize($request)) {
            return $this->deserializer->respondWithError();
        }

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select($request->getAlias())
            ->from($request->getType(), $request->getAlias())
            ->setMaxResults($request->getLimit() + 1)
            ->setFirstResult($request->getOffset())
        ;

        if ($request->getQuery() !== null) {
            $queryBuilder->where($request->getQuery());
        }

        if ($request->getParameters() !== null) {
            $queryBuilder->setParameters($request->getParameters());
        }

        if ($request->getJoins() !== null) {
            foreach ($request->getJoins() as $alias => $join) {
                $queryBuilder->join($join, $alias);
            }
        }

        if ($request->getOrderBy() !== null) {
            foreach ($request->getOrderBy() as $sort => $order) {
                $queryBuilder->addOrderBy($sort, $order);
            }
        }

        try {
            $result = $queryBuilder->getQuery()->getResult();
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }

        $hasMore = false;
        if (count($result) === $request->getLimit() + 1) {
            $hasMore = true;
        }

        $response = [];
        $result = array_slice($result, 0, $request->getLimit());
        foreach ($result as $item) {
            $response[] = json_decode($this->serializer->serialize($item, 'json'));
        }

        return new JsonResponse([
            'response' => [
                'result' => $response,
                'hasMore' => $hasMore,
            ]
        ]);
    }
}